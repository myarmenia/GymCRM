<?php

namespace App\Interfaces\Turnstile;

interface ClientIdFromTurnstileInterface
{
    /**
     * Resolve the gym assigned to a physical turnstile.
     *
     * The MAC address is the only trusted gym context in an EES request.
     */
    public function getClientId(string $mac): ?int;
}
