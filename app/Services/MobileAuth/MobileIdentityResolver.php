<?php

namespace App\Services\MobileAuth;

class MobileIdentityResolver
{
    public function prepare(string $phone): void
    {
        // GymCRM receives shared identities from the primary sync worker.
        // New identities are created only by the primary mobile API.
    }
}
