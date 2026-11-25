<?php

namespace App\Repositories\MobileAuth;

use App\Interfaces\MobileAuth\MobilePersonAuthInterface;
use App\Models\Person;
use App\Services\MembershipSales\ContactNotePhone;
use Illuminate\Support\Facades\DB;

class MobilePersonAuthRepository implements MobilePersonAuthInterface
{
    public function findByPhone(string $phone, bool $lock = false): ?Person
    {
        // Include tombstones so registration cannot recreate a deleted identity.
        return Person::withTrashed()
            ->whereIn(DB::raw(ContactNotePhone::normalizedSql('phone')), ContactNotePhone::variants($phone))
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first();
    }

    public function updateFcmToken(Person $person, string $fcmToken): Person
    {
        $person->update(['fcm_token' => $fcmToken]);

        return $person->fresh();
    }
}
