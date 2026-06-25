<?php

namespace App\Services\Turnstile;

use App\Events\TurnstileEntryDetected;
use App\Helpers\MyHelper;
use App\Interfaces\AttendanceSheets\AttendanceSheetInterface;
use App\Interfaces\Turnstile\CheckEntryCodeInterface;
use App\Interfaces\Turnstile\ClientIdFromTurnstileInterface;
use App\Models\EntryReport;
use App\Models\Person;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EntryExitSystemService
{
    private const LOCAL_TIMEZONE = 'Asia/Yerevan';

    public function __construct(
        protected ClientIdFromTurnstileInterface $turnstileRepository,
        protected CheckEntryCodeInterface $checkEntryCodeRepository,
        protected AttendanceSheetInterface $attendanceSheetRepository
    ) {}

    public function ees($data)
    {
        $clientId = $this->turnstileRepository->getClientId($data->mac);

        if (!$clientId) {
            Log::info('ees_invalid_mac', ['mac' => $data->mac ?? null]);

            $this->createEntryReport([
                'client_id' => null,
                'entry_code' => $data->entry_code ?? null,
                'action' => 'unknown',
                'status' => 'denied',
                'reason' => 'invalid_mac',
                'access_allowed' => false,
                'mac' => $data->mac ?? null,
                'detected_at' => now(self::LOCAL_TIMEZONE),
                'payload' => ['request' => (array) $data],
            ]);

            return $this->deniedResponse('invalid mac', 'invalid_mac');
        }

        [$entryCode, $timestamp] = $this->parseEntryCode($data->entry_code);

        $entryCode = $this->normalizeEntryCode(
            $entryCode,
            $data->entry_code_type ?? null
        );

        $deviceTime = $this->resolveDeviceTime($timestamp);

        $detectedAt = $deviceTime ?? now(self::LOCAL_TIMEZONE);

        $resolved = $this->resolveEntryCodeOwner(
            $entryCode,
            (int) $clientId,
            $data->type ?? null,
            $data->auto_add ?? 0
        );

        if (!$resolved) {
            $payload = $this->makeSocketPayload([
                'status' => 'denied',
                'access_allowed' => false,
                'owner_type' => null,
                'reason' => 'invalid_entry_code',
                'message' => 'Invalid entry code',
                'entry_code' => $entryCode,
                'client_id' => $clientId,
                'mac' => $data->mac ?? null,
                'detected_at' => $detectedAt->toDateTimeString(),
            ]);

            $this->createEntryReport([
                'client_id' => $clientId,
                'entry_code' => $entryCode,
                'owner_type' => null,
                'owner_id' => null,
                'action' => 'unknown',
                'status' => 'denied',
                'reason' => 'invalid_entry_code',
                'access_allowed' => false,
                'mac' => $data->mac ?? null,
                'device_time' => $deviceTime,
                'detected_at' => $detectedAt,
                'payload' => $payload,
            ]);

            $this->broadcastEntryAttempt((int) $clientId, $payload);

            return $this->deniedResponse('denied', 'invalid_entry_code');
        }

        $ownerType = $resolved['owner_type'];
        $owner = $resolved['owner'];
        $action = $this->detectNextAction($ownerType, $owner->id, (int) $clientId);

        if ($ownerType === 'person' && !$this->hasActiveSubscription($owner, (int) $clientId, $detectedAt)) {
            $payload = $this->makeSocketPayload([
                'status' => 'denied',
                'access_allowed' => false,
                'owner_type' => 'person',
                'reason' => 'subscription_expired',
                'message' => 'Մուտքը մերժված է․ aboniment-ի ժամկետը լրացել է կամ active aboniment չկա',
                'person' => $this->personPayload($owner),
                'entry_code' => $entryCode,
                'client_id' => $clientId,
                'mac' => $data->mac ?? null,
                'detected_at' => $detectedAt->toDateTimeString(),
            ]);

            $this->createEntryReport([
                'client_id' => $clientId,
                'entry_code' => $entryCode,
                'owner_type' => $ownerType,
                'owner_id' => $owner->id,
                'action' => $action,
                'status' => 'denied',
                'reason' => 'subscription_expired',
                'access_allowed' => false,
                'mac' => $data->mac ?? null,
                'device_time' => $deviceTime,
                'detected_at' => $detectedAt,
                'payload' => $payload,
            ]);

            $this->broadcastEntryAttempt((int) $clientId, $payload);

            Log::info('ees_entry_attempt', [
                'client_id' => $clientId,
                'entry_code' => $entryCode,
                'owner_type' => $ownerType,
                'owner_id' => $owner->id,
                'status' => 'denied',
                'reason' => 'subscription_expired',
            ]);

            return $this->deniedResponse('denied', 'subscription_expired', $ownerType, $action);
        }

        $attendance = $this->attendanceSheetRepository->create([
            'relation_id' => $owner->id,
            'relation_type' => get_class($owner),
            'entry_code' => $entryCode,
            'date' => $detectedAt,
            'type' => $data->type ?? null,
            'direction' => $action,
            'online' => $data->online ?? null,
            'local_ip' => $data->local_ip ?? null,
            'mac' => $data->mac ?? null,
        ]);

        $payload = $this->makeSocketPayload([
            'status' => 'success',
            'access_allowed' => true,
            'owner_type' => $ownerType,
            'action' => $action,
            'message' => $ownerType === 'user' ? 'User entry allowed' : 'Person entry allowed',
            'person' => $ownerType === 'person' ? $this->personPayload($owner) : null,
            'user' => $ownerType === 'user' ? $this->userPayload($owner) : null,
            'entry_code' => $entryCode,
            'client_id' => $clientId,
            'mac' => $data->mac ?? null,
            'attendance_id' => $attendance->id,
            'date' => $attendance->date,
            'detected_at' => $detectedAt->toDateTimeString(),
        ]);

        $this->createEntryReport([
            'client_id' => $clientId,
            'entry_code' => $entryCode,
            'owner_type' => $ownerType,
            'owner_id' => $owner->id,
            'action' => $action,
            'status' => 'success',
            'reason' => 'success',
            'access_allowed' => true,
            'mac' => $data->mac ?? null,
            'device_time' => $deviceTime,
            'detected_at' => $detectedAt,
            'payload' => $payload,
        ]);

        $this->broadcastEntryAttempt((int) $clientId, $payload);

        Log::info('ees_entry_attempt', [
            'client_id' => $clientId,
            'entry_code' => $entryCode,
            'owner_type' => $ownerType,
            'owner_id' => $owner->id,
            'status' => 'success',
            'reason' => 'success',
        ]);

        return (object) [
            'message' => 'success',
            'result' => [
                'access_allowed' => true,
                'status' => 'success',
                'owner_type' => $ownerType,
                'action' => $action,
            ],
        ];
    }

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

    private function resolveDeviceTime(mixed $timestamp): ?Carbon
    {
        if (!$timestamp) {
            return null;
        }

        if (is_numeric($timestamp)) {
            $timestamp = (int) $timestamp;
            $timestamp = $timestamp > 9999999999
                ? (int) floor($timestamp / 1000)
                : $timestamp;

            return Carbon::createFromTimestamp($timestamp, self::LOCAL_TIMEZONE);
        }

        try {
            return Carbon::parse((string) $timestamp, self::LOCAL_TIMEZONE);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveEntryCodeOwner(?string $entryCode, int $clientId, ?string $type, mixed $autoAdd): ?array
    {
        if (!$entryCode) {
            return null;
        }

        $check = $this->checkEntryCodeRepository
            ->checkEntryCode($entryCode, $clientId, $type, $autoAdd);

        if (!$check->result) {
            return null;
        }

        $permission = $check->result
            ->entryPermissions()
            ->with('relation')
            ->where('status', 1)
            ->first();

        $owner = $permission?->relation;

        if (!$owner) {
            return null;
        }

        if ($owner instanceof User && (int) $owner->gym_id === $clientId) {
            return [
                'owner_type' => 'user',
                'owner' => $owner,
                'entry_code' => $check->result,
            ];
        }

        if (
            $owner instanceof Person &&
            (
                $owner->gyms()->where('gyms.id', $clientId)->exists() ||
                $owner->memberships()->where('gym_id', $clientId)->exists()
            )
        ) {
            return [
                'owner_type' => 'person',
                'owner' => $owner,
                'entry_code' => $check->result,
            ];
        }

        return null;
    }

    private function hasActiveSubscription(Person $person, int $clientId, Carbon $referenceTime): bool
    {
        $referenceDate = $referenceTime->copy()->timezone(self::LOCAL_TIMEZONE)->toDateString();

        return $person->memberships()
            ->where('gym_id', $clientId)
            ->whereIn('status', ['active', 'waiting'])
            ->where(function ($query) use ($referenceDate) {
                $query->whereNull('start_date')
                    ->orWhereDate('start_date', '<=', $referenceDate);
            })
            ->where(function ($query) use ($referenceDate) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $referenceDate);
            })
            ->where(function ($query) use ($referenceTime) {
                $query->whereNull('expired_at')
                    ->orWhere('expired_at', '>=', $referenceTime);
            })
            ->where(function ($query) {
                $query->whereNull('visits_left')
                    ->orWhere('visits_left', '>', 0);
            })
            ->exists();
    }

    private function detectNextAction(?string $ownerType, ?int $ownerId, int $clientId): string
    {
        if (!$ownerType || !$ownerId) {
            return 'unknown';
        }

        $lastReport = EntryReport::query()
            ->where('client_id', $clientId)
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->where('status', 'success')
            ->latest()
            ->first();

        return $lastReport?->action === 'entry' ? 'exit' : 'entry';
    }

    private function createEntryReport(array $payload): EntryReport
    {
        return EntryReport::create($payload);
    }

    private function broadcastEntryAttempt(int $clientId, array $payload): void
    {
        event(new TurnstileEntryDetected($clientId, $payload));
    }

    private function makeSocketPayload(array $payload): array
    {
        $payload['person'] ??= null;
        $payload['user'] ??= null;
        $payload['owner'] ??= $payload['person'] ?? $payload['user'] ?? null;

        return $payload;
    }

    private function personPayload(Person $person): array
    {
        return [
            'id' => $person->id,
            'name' => $person->name,
            'surname' => $person->surname ?? null,
            'phone' => $person->phone ?? null,
            'email' => $person->email ?? null,
            'type' => $person->type ?? null,
            'image' => $person->image ?? null,
        ];
    }

    private function userPayload(User $user): array
    {
        $user->loadMissing('roles');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'surname' => $user->surname ?? null,
            'email' => $user->email ?? null,
            'role' => $user->roles?->first()?->name,
        ];
    }

    private function deniedResponse(
        string $message,
        string $reason,
        ?string $ownerType = null,
        string $action = 'unknown'
    ): object {
        return (object) [
            'message' => $message,
            'result' => [
                'access_allowed' => false,
                'status' => 'denied',
                'reason' => $reason,
                'owner_type' => $ownerType,
                'action' => $action,
            ],
        ];
    }
}
