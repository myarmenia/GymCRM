<?php

namespace App\Interfaces\MobileAuth;

use App\Models\Person;

interface MobilePersonAuthInterface
{
    public function findActiveVisitorByEmail(string $email): ?Person;

    public function updateFcmToken(Person $person, string $fcmToken): Person;
}
