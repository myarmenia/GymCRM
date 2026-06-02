<?php

namespace App\Interfaces\Turnstile;

interface ClientIdFromTurnstileInterface
{
    public function getClientId(array $mac);
}
