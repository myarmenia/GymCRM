<?php

namespace App\Repositories\Turnstile;
use App\Events\EntryCodeCreated;
use App\Interfaces\Turnstile\CheckEntryCodeInterface;
use App\Interfaces\Turnstile\ClientIdFromTurnstileInterface;
use App\Models\EntryCode;
use App\Models\Gym;
use App\Models\Turnstile;


class TurnstileRepository implements ClientIdFromTurnstileInterface, CheckEntryCodeInterface
{

    public function getClientId($mac):mixed
    {
        $turnstile = Turnstile::where('mac', $mac)->first();
        
        return $turnstile != null ? $turnstile->gym_id : false;
    }

    public function checkEntryCode($request_entry_code, $gym_id, $type, $auto_add = 0):object
    {

        $message = 'success';
        $result = false;
        $allowedEntryCodeType = Gym::query()
            ->whereKey($gym_id)
            ->value('entry_code_type') ?? 'rfId';

        $entry_code = EntryCode::where([
            'token' => $request_entry_code,
            'gym_id' => $gym_id
        ])->first();

        if (!$entry_code) {
            if ($gym_id && $auto_add) {

                $entryCode = EntryCode::create([
                    'token' => $request_entry_code,
                    'type' => $allowedEntryCodeType,
                    'gym_id' => $gym_id,
                    'activation' => 0
                ]);

                // $entryCode = EntryCode::where(['gym_id' => $gym_id, 'activation' => 0, 'status' => 1, 'token' => $request_entry_code])->first();

                $message = 'The code was not activated.';
                event(new EntryCodeCreated($gym_id, $entryCode));

            }

            $message = 'Code not found.';

        } elseif ($entry_code->type !== $allowedEntryCodeType) {
             $message = 'The entry code type is not allowed for this gym.';

        } elseif ($entry_code->status != 1) {
             $message = 'The code is not active.';

        } elseif ($entry_code->activation != 1) {
             $message = 'The code was not activated.';

        }
        else{
            $result = $entry_code != null ? $entry_code : false;

        }

        return (object) [
            'message' => $message,
            'result' => $result
        ];
    }
}
