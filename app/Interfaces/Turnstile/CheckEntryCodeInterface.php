<?php

namespace App\Interfaces\Turnstile;

interface CheckEntryCodeInterface
{
    public function checkEntryCode($entry_code, $client_id, $type, $auto_add);
}
