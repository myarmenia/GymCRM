<?php

namespace App\Interfaces\MobileAuth;

use App\Models\Person;

interface MobilePersonAuthInterface
{
    public function findByPhone(string $phone, bool $lock = false): ?Person;

    public function updateFcmToken(Person $person, string $fcmToken): Person;
}
