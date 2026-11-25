<?php

namespace App\Services\MobileAuth;

use App\Interfaces\MobileAuth\MobilePersonAuthInterface;
use App\Models\Person;
use App\Services\MobileNotifications\MobilePushNotificationService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class MobilePersonAuthService
{
    public function __construct(
        protected MobilePersonAuthInterface $people,
        protected MobilePushNotificationService $notifications,
        protected MobileIdentityResolver $identities,
    ) {}

    public function login(string $phone, string $password, ?string $fcmToken, ?string $deviceName): array
    {
        $this->identities->prepare($phone);

        return DB::transaction(function () use ($phone, $password, $fcmToken, $deviceName): array {
            $person = $this->people->findByPhone($phone, true);
            $this->verifyPassword($person, $password, 'phone');

            return $this->issueToken($person, $fcmToken, $deviceName);
        });
    }

    public function register(array $attributes): array
    {
        $this->identities->prepare($attributes['phone']);

        try {
            return DB::transaction(function () use ($attributes): array {
                $person = $this->people->findByPhone($attributes['phone'], true);
                $created = $person === null;
                if ($person) {
                    $this->verifyPassword($person, $attributes['password'], 'phone');
                } else {
                    abort_unless(config('mobile.registration_enabled'), 503, 'New mobile accounts must be registered through the primary mobile API.');
                    $data = Validator::make($attributes, [
                        'name' => ['required', 'string', 'max:255'],
                        'birth_date' => ['required', 'date', 'before_or_equal:today'],
                        'phone' => ['required', 'string'],
                        'password' => ['required', 'string', 'min:8', 'max:4096'],
                    ])->validate();
                    $data['password'] = Hash::make($data['password']);
                    $person = Person::create($data);
                }

                return [
                    ...$this->issueToken($person, $attributes['fcm_token'] ?? null, $attributes['device_name'] ?? null),
                    'created' => $created,
                ];
            });
        } catch (UniqueConstraintViolationException $exception) {
            // A concurrent registration may have won the unique-phone race.
            $person = $this->people->findByPhone($attributes['phone']);
            if (! $person) {
                throw $exception;
            }

            return [...$this->login($attributes['phone'], $attributes['password'], $attributes['fcm_token'] ?? null, $attributes['device_name'] ?? null), 'created' => false];
        }
    }

    public function changePassword(Person $person, string $currentPassword, string $newPassword): void
    {
        $this->identities->prepare((string) $person->phone);
        $currentToken = $person->currentAccessToken();

        DB::connection($person->getConnectionName())->transaction(function () use ($person, $currentPassword, $newPassword, $currentToken): void {
            $locked = Person::on($person->getConnectionName())->lockForUpdate()->findOrFail($person->id);
            $this->verifyPassword($locked, $currentPassword, 'current_password');
            if (Hash::check($newPassword, $locked->password)) {
                throw ValidationException::withMessages(['password' => [__('validation.different', ['attribute' => 'password', 'other' => 'current_password'])]]);
            }
            $locked->update(['password' => Hash::make($newPassword)]);
            $locked->tokens()
                ->when($currentToken instanceof PersonalAccessToken, fn ($query) => $query->whereKeyNot($currentToken->getKey()))
                ->delete();
        });
    }

    private function verifyPassword(?Person $person, string $password, string $field): void
    {
        if (! $person || $person->trashed() || ! $person->password || ! Hash::check($password, $person->password)) {
            throw ValidationException::withMessages([$field => [trans('auth.failed')]]);
        }
    }

    private function issueToken(Person $person, ?string $fcmToken, ?string $deviceName): array
    {
        $local = ['mobile_deleted' => false];
        if ($fcmToken) {
            $local['fcm_token'] = $fcmToken;
        }
        $person->update($local);
        $token = $person->createToken($deviceName ?: 'fittracker-mobile', ['mobile'])->plainTextToken;
        $this->notifications->sendFirstLogin($person);

        return ['person' => $person, 'access_token' => $token, 'token_type' => 'Bearer'];
    }

    public function logout(Person $person): void
    {
        $person->currentAccessToken()?->delete();
    }

    public function updateFcmToken(Person $person, string $fcmToken): Person
    {
        return $this->people->updateFcmToken($person, $fcmToken);
    }
}
