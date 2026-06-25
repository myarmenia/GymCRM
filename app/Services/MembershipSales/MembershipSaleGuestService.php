<?php

namespace App\Services\MembershipSales;

use App\Interfaces\MembershipSales\MembershipSaleInterface;
use App\Models\EntryCode;
use App\Models\EntryPermission;
use App\Models\Guest;
use App\Models\MembershipSale;
use App\Models\Person;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MembershipSaleGuestService
{
    public function __construct(
        protected MembershipSaleInterface $membershipSaleRepository,
    ) {
    }

    public function guestPageData(int $id): array
    {
        $membershipSale = $this->getById($id);
        $personMembership = $this->activePersonMembershipForGuests($membershipSale);

        if (!$personMembership) {
            throw ValidationException::withMessages([
                'person_membership_id' => $this->guestRequiresActiveMembershipMessage(),
            ]);
        }

        $personMembership->load([
            'person',
            'membershipPlan.translations',
            'guests.guest',
        ]);

        $guestSummary = $this->guestSummary($personMembership);

        return [
            'membershipSale' => $membershipSale,
            'personMembership' => $personMembership,
            'guests' => $personMembership->guests,
            'entryCodes' => $this->availableEntryCodes($membershipSale->gym_id),
            ...$guestSummary,
        ];
    }

    public function storeGuest(int $id, array $data): void
    {
        $phoneLock = Cache::lock('membership-sale-guest-phone:' . sha1((string) $data['phone']), 10);
        $phoneLock->block(5);

        DB::beginTransaction();

        try {
            $membershipSale = $this->getById($id);
            $personMembership = $this->activePersonMembershipForGuests($membershipSale);

            if (!$personMembership) {
                throw ValidationException::withMessages([
                    'person_membership_id' => $this->guestRequiresActiveMembershipMessage(),
                ]);
            }

            $summary = $this->guestSummary($personMembership);

            if ($summary['remainingGuestCount'] <= 0) {
                throw ValidationException::withMessages([
                    'guest_id' => $this->guestLimitReachedMessage(),
                ]);
            }

            $guestPerson = $this->findOrCreateGuestPerson($data, $membershipSale);

            $alreadyAdded = $personMembership
                ->guests()
                ->where('guest_id', $guestPerson->id)
                ->exists();

            if ($alreadyAdded) {
                throw ValidationException::withMessages([
                    'guest_id' => $this->guestAlreadyAddedMessage(),
                ]);
            }

            Guest::query()->create([
                'guest_id' => $guestPerson->id,
                'person_id' => $personMembership->person_id,
                'person_membership_id' => $personMembership->id,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        } finally {
            $phoneLock->release();
        }
    }

    public function lookupGuestPerson(int $id, string $phone): array
    {
        $membershipSale = $this->getById($id);

        $person = Person::query()
            ->where('phone', $phone)
            ->first();

        if (!$person) {
            return [
                'person' => null,
                'error' => null,
                'is_owner' => false,
            ];
        }

        if ((int) $person->id === (int) $membershipSale->person_id) {
            return [
                'person' => null,
                'error' => $this->ownerCannotBeGuestMessage(),
                'is_owner' => true,
            ];
        }

        $entryCode = $this->personEntryCodePayload($person);

        return [
            'person' => [
                'id' => $person->id,
                'name' => $person->name,
                'surname' => $person->surname,
                'email' => $person->email,
                'phone' => $person->phone,
                'birth_date' => $person->birth_date ? (string) $person->birth_date : null,
                'gender' => $person->gender,
                'type' => $person->type,
                'entry_code_id' => $entryCode['id'] ?? null,
                'entry_code' => $entryCode,
            ],
            'error' => null,
            'is_owner' => false,
        ];
    }

    protected function getById(int $id): MembershipSale
    {
        $user = Auth::user();

        return $this->membershipSaleRepository
            ->query()
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->where('gym_id', $user->gym_id);
            })
            ->findOrFail($id);
    }

    protected function activePersonMembershipForGuests(MembershipSale $membershipSale)
    {
        return $membershipSale
            ->personMemberships()
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('start_date')
                    ->orWhereDate('start_date', '<=', today());
            })
            ->where(function ($query) {
                $query->whereNull('valid_at')
                    ->orWhereDate('valid_at', '>=', today());
            })
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', today());
            })
            ->first();
    }

    protected function guestSummary($personMembership): array
    {
        $allowedGuestCount = (int) ($personMembership->guest_used ?? 0);
        $usedGuestCount = (int) $personMembership->guests()->count();

        return [
            'allowedGuestCount' => $allowedGuestCount,
            'usedGuestCount' => $usedGuestCount,
            'remainingGuestCount' => max((int) ($personMembership->guest_left ?? 0), 0),
        ];
    }

    protected function findOrCreateGuestPerson(array $data, MembershipSale $membershipSale): Person
    {
        $guest = Person::query()
            ->where('phone', $data['phone'])
            ->lockForUpdate()
            ->first();

        if ($guest) {
            if ((int) $guest->id === (int) $membershipSale->person_id) {
                throw ValidationException::withMessages([
                    'phone' => $this->ownerCannotBeGuestMessage(),
                ]);
            }

            if (!empty($data['email'])) {
                $emailExists = Person::query()
                    ->where('email', $data['email'])
                    ->where('id', '!=', $guest->id)
                    ->exists();

                if ($emailExists) {
                    throw ValidationException::withMessages([
                        'email' => $this->duplicatePersonEmailMessage(),
                    ]);
                }
            }

            $guest->update([
                'name' => $data['name'],
                'surname' => $data['surname'] ?? null,
                'email' => $data['email'] ?? $guest->email,
                'birth_date' => $data['birth_date'] ?? null,
                'gender' => $data['gender'] ?? null,
            ]);

            $this->syncGuestEntryCode($guest, (int) $data['entry_code_id'], $membershipSale->gym_id);
        } else {
            if (!empty($data['email']) && Person::query()->where('email', $data['email'])->exists()) {
                throw ValidationException::withMessages([
                    'email' => $this->duplicatePersonEmailMessage(),
                ]);
            }

            $guest = Person::query()->create([
                'name' => $data['name'],
                'surname' => $data['surname'] ?? null,
                'email' => $data['email'] ?? 'guest-' . Str::uuid() . '@guest.local',
                'password' => Hash::make(Str::random(16)),
                'phone' => $data['phone'],
                'type' => 'guest',
                'birth_date' => $data['birth_date'] ?? null,
                'gender' => $data['gender'] ?? null,
                'mobile_deleted' => false,
                'fcm_token' => null,
            ]);

            $this->syncGuestEntryCode($guest, (int) $data['entry_code_id'], $membershipSale->gym_id);
        }

        if ($membershipSale->gym_id) {
            $guest->gyms()->syncWithoutDetaching([$membershipSale->gym_id]);
        }

        return $guest;
    }

    protected function currentPersonEntryPermission(Person $person): ?EntryPermission
    {
        return $person
            ->entryPermissions()
            ->with('entryCode.gym:id,name')
            ->where('status', true)
            ->latest('id')
            ->first()
            ?? $person
                ->entryPermissions()
                ->with('entryCode.gym:id,name')
                ->latest('id')
                ->first();
    }

    protected function personEntryCodePayload(Person $person): ?array
    {
        $entryPermission = $this->currentPersonEntryPermission($person);
        $entryCode = $entryPermission?->entryCode;

        if (!$entryCode) {
            return null;
        }

        return [
            'id' => $entryCode->id,
            'token' => $entryCode->token,
            'gym_id' => $entryCode->gym_id,
            'type' => $entryCode->type,
            'gym' => $entryCode->gym ? [
                'id' => $entryCode->gym->id,
                'name' => $entryCode->gym->name,
            ] : null,
        ];
    }

    protected function syncGuestEntryCode(Person $guest, int $entryCodeId, ?int $gymId): void
    {
        $currentEntryPermission = $this->currentPersonEntryPermission($guest);
        $currentEntryCodeId = $currentEntryPermission?->entry_code_id;

        if ((int) $currentEntryCodeId === $entryCodeId) {
            return;
        }

        $entryCode = $this->availableEntryCode($entryCodeId, $gymId);

        if ($currentEntryCodeId) {
            EntryCode::query()
                ->whereKey($currentEntryCodeId)
                ->update(['activation' => false]);
        }

        $guest->entryPermissions()->delete();

        EntryPermission::query()->create([
            'entry_code_id' => $entryCode->id,
            'relation_type' => Person::class,
            'relation_id' => $guest->id,
            'status' => 1,
        ]);

        $entryCode->update(['activation' => true]);
    }

    protected function availableEntryCodes(?int $gymId)
    {
        return EntryCode::query()
            ->where('gym_id', $gymId)
            ->where('status', true)
            ->where('activation', false)
            ->with('gym:id,name')
            ->orderBy('id', 'desc')
            ->get(['id', 'token', 'gym_id', 'type']);
    }

    protected function availableEntryCode(int $entryCodeId, ?int $gymId): EntryCode
    {
        $entryCode = EntryCode::query()
            ->where('id', $entryCodeId)
            ->where('gym_id', $gymId)
            ->where('status', true)
            ->where('activation', false)
            ->first();

        if (!$entryCode) {
            throw ValidationException::withMessages([
                'entry_code_id' => $this->entryCodeUnavailableMessage(),
            ]);
        }

        return $entryCode;
    }

    protected function ownerCannotBeGuestMessage(): string
    {
        return 'Չեք կարող նույն հաճախորդին ավելացնել որպես հյուր իր սեփական աբոնեմենտին։';
    }

    protected function guestRequiresActiveMembershipMessage(): string
    {
        return 'Հյուր ավելացնել հնարավոր է միայն ակտիվ աբոնեմենտի համար։';
    }

    protected function guestLimitReachedMessage(): string
    {
        return 'Հյուրերի թույլատրելի քանակը սպառված է։';
    }

    protected function guestAlreadyAddedMessage(): string
    {
        return 'Այս հյուրը արդեն ավելացված է տվյալ աբոնեմենտին։';
    }

    protected function duplicatePersonEmailMessage(): string
    {
        return 'Այս էլ. հասցեով անձ արդեն գոյություն ունի։';
    }

    protected function entryCodeUnavailableMessage(): string
    {
        return 'Ընտրված մուտքի կոդը հասանելի չէ։ Ստեղծիր';
    }
}
