<?php

namespace App\Repositories\MobileAuth;

use App\Interfaces\MobileAuth\MobilePersonAuthInterface;
use App\Models\Person;

class MobilePersonAuthRepository implements MobilePersonAuthInterface
{
    public function findActiveVisitorByEmail(string $email): ?Person
    {
        return Person::query()
            ->where('email', $email)
            ->where('type', 'visitor')
            ->where('mobile_deleted', false)
            ->first();
    }

    public function updateFcmToken(Person $person, string $fcmToken): Person
    {
        $person->update(['fcm_token' => $fcmToken]);

        return $person->fresh();
    }
}
