<?php

namespace App\Services\Turnstile;

use App\Helpers\MyHelper;
use App\Interfaces\AttendanceSheets\AttendanceSheetInterface;
use App\Interfaces\Turnstile\CheckEntryCodeInterface;
use App\Interfaces\Turnstile\ClientIdFromTurnstileInterface;
use Illuminate\Support\Facades\Log;

class EntryExitSystemService
{
    public function __construct(
        protected ClientIdFromTurnstileInterface $turnstileRepository,
        protected CheckEntryCodeInterface $checkEntryCodeRepository,
        protected AttendanceSheetInterface $attendanceSheetRepository
    ) {
    }

    public function ees($data)
    {
        $clientId = $this->turnstileRepository->getClientId($data->mac);

        if (!$clientId) {
            Log::info('invalid_mac', ['mac' => $data->mac]);

            return (object) [
                'message' => 'invalid mac',
                'result' => null
            ];
        }

        [$entryCode, $timestamp] = $this->parseEntryCode($data->entry_code);

        $entryCode = $this->normalizeEntryCode(
            $entryCode,
            $data->entry_code_type ?? null
        );

        $check = $this->checkEntryCodeRepository
            ->checkEntryCode($entryCode, $clientId, $data->type, $data->auto_add ?? 0);

        if (!$check->result) {
            return (object) [
                'message' => $check->message,
                'result' => null
            ];
        }

        $entryCodeModel = $check->result;

        $owner = $this->resolveOwner($entryCodeModel);

        if (!$owner) {
            return (object) [
                'message' => 'owner not found',
                'result' => null
            ];
        }

        $payload = [
            'relation_id' => $owner->id,
            'relation_type' => get_class($owner),
            'entry_code' => $entryCode,
            'date' => $timestamp ? date('Y-m-d H:i:s', $timestamp) : now(),
        ];

        $result = $this->attendanceSheetRepository->create($payload);

        return (object) [
            'message' => 'success',
            'result' => $result
        ];
    }

    /*
    |------------------------------------------------
    | Helpers (разделили логику)
    |------------------------------------------------
    */

    private function parseEntryCode(string $raw): array
    {
        $parts = explode('#', $raw);

        return [
            $parts[0] ?? null,
            $parts[1] ?? null,
        ];
    }

    private function normalizeEntryCode($code, $type): ?string
    {
        if (!$code) {
            return null;
        }

        return $type === 'standart'
            ? $code
            : MyHelper::binaryToDecimal($code);
    }

    private function resolveOwner($entryCodeModel)
    {
        return $entryCodeModel->entryPermissions()
            ->with('relation')
            ->first()?->relation;
    }
}
