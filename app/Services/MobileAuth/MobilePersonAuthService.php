<?php

namespace App\Services\MobileAuth;

use App\Interfaces\MobileAuth\MobilePersonAuthInterface;
use App\Models\Person;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MobilePersonAuthService
{
    public function __construct(protected MobilePersonAuthInterface $people)
    {
    }

    public function login(string $email, string $password, ?string $fcmToken, ?string $deviceName): array
    {
        $person = $this->people->findActiveVisitorByEmail($email);

        if (!$person || !Hash::check($password, $person->password)) {
            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        if ($fcmToken) {
            $person = $this->people->updateFcmToken($person, $fcmToken);
        }

        $token = $person->createToken($deviceName ?: 'fittracker-mobile', ['mobile'])->plainTextToken;

        return [
            'person' => $person,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ];
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
