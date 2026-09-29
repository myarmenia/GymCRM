<?php

namespace App\Services\Turnstile;

use App\Events\TurnstileEntryDetected;
use App\Helpers\MyHelper;
use App\Interfaces\AttendanceSheets\AttendanceSheetInterface;
use App\Interfaces\Turnstile\CheckEntryCodeInterface;
use App\Interfaces\Turnstile\ClientIdFromTurnstileInterface;
use App\Models\AttendanceSheet;
use App\Models\Person;
use App\Models\PersonMembership;
use App\Models\User;
use App\Services\People\GuestEntryService;
use Carbon\Carbon;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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

            return $this->deniedResponse('invalid mac', 'invalid_mac');
        }

        [$entryCode, $timestamp] = $this->parseEntryCode($data->entry_code);
        $entryCodeCandidates = $this->entryCodeCandidates(
            $entryCode,
            $data->entry_code_type ?? null
        );

        $deviceTime = $this->resolveDeviceTime($timestamp);

        $detectedAt = $deviceTime ?? now(self::LOCAL_TIMEZONE);
        $action = 'entry';

        $resolved = $this->resolveEesEntryCodeOwner(
            $entryCodeCandidates,
            (int) $clientId,
            $data->type ?? null,
            $data->auto_add ?? 0
        );

        if (!$resolved) {
            $payload = $this->makeSocketPayload([
                'status' => 'denied',
                'access_allowed' => false,
                'owner_type' => null,
                'action' => $action,
                'reason' => 'invalid_entry_code',
                'message' => 'Invalid entry code',
                'entry_code' => $entryCode,
                'client_id' => $clientId,
                'mac' => $data->mac ?? null,
                'detected_at' => $detectedAt->toDateTimeString(),
            ]);

            $this->broadcastEntryAttempt((int) $clientId, $payload);

            return $this->deniedResponse('denied', 'invalid_entry_code');
        }

        // Persist and broadcast the token that was actually found for this
        // turnstile's gym, rather than a possible binary representation sent
        // by the device.
        $entryCode = $resolved['entry_code']->token;
        $ownerType = $resolved['owner_type'];
        $owner = $resolved['owner'];
        $selectedMembership = null;
        $selectedMemberships = collect();

        if ($ownerType === 'person' && $action === 'entry' && $owner->is_blocked) {
            $payload = $this->makeSocketPayload([
                'status' => 'denied',
                'access_allowed' => false,
                'owner_type' => 'person',
                'action' => $action,
                'reason' => 'person_blocked',
                'message' => 'Person is blocked and cannot enter.',
                'person' => $this->personPayload($owner),
                'entry_code' => $entryCode,
                'client_id' => $clientId,
                'mac' => $data->mac ?? null,
                'detected_at' => $detectedAt->toDateTimeString(),
            ]);

            $this->broadcastEntryAttempt((int) $clientId, $payload);

            return $this->deniedResponse('denied', 'person_blocked', $ownerType, $action);
        }
        if (
            $ownerType === 'person' &&
            $action === 'entry' &&
            !$this->hasActiveSubscription($owner, (int) $clientId, $detectedAt)
        ) {
            $payload = $this->makeSocketPayload([
                'status' => 'denied',
                'access_allowed' => false,
                'owner_type' => 'person',
                'action' => $action,
                'reason' => 'subscription_expired',
                'message' => __('backend_messages.entry_denied_membership_has_expired_or_there_no_active_membership'),
                'person' => $this->personPayload($owner),
                'entry_code' => $entryCode,
                'client_id' => $clientId,
                'mac' => $data->mac ?? null,
                'detected_at' => $detectedAt->toDateTimeString(),
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

        if ($this->isDuplicatePersonEntryOnDetectedDate($owner, $action, $detectedAt)) {
            return $this->duplicateEntryResponse($owner, $action, $clientId, $entryCode, $detectedAt, [
                'mac' => $data->mac ?? null,
                'scan_type' => $data->type ?? null,
                'online' => $data->online ?? null,
                'local_ip' => $data->local_ip ?? null,
                'detected_at' => $detectedAt->toDateTimeString(),
            ]);
        }

        if ($ownerType === 'person' && $action === 'entry') {
            $selectedMemberships = $this->resolveAutomaticMembershipsForEntry(
                $owner,
                (int) $clientId,
                $detectedAt,
                $action,
            );
            $selectedMembership = $selectedMemberships->first();
        }

        if (
            $ownerType === 'person' &&
            $action === 'entry' &&
            $selectedMemberships->isEmpty() &&
            $this->requiresManagerMembershipSelection($owner, (int) $clientId, $detectedAt)
        ) {
            $payload = $this->makeSocketPayload([
                'status' => 'success',
                'access_allowed' => true,
                'owner_type' => 'person',
                'action' => $action,
                'message' => 'Person entry allowed',
                'person' => $this->personPayload($owner),
                'membership_activation_context' => $this->membershipSelectionContext($owner, (int) $clientId, $detectedAt),
                'pending_attendance_selection' => true,
                'entry_code' => $entryCode,
                'client_id' => $clientId,
                'mac' => $data->mac ?? null,
                'scan_type' => $data->type ?? null,
                'online' => $data->online ?? null,
                'local_ip' => $data->local_ip ?? null,
                'detected_at' => $detectedAt->toDateTimeString(),
            ]);

            $this->broadcastEntryAttempt((int) $clientId, $payload);

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

        $duplicateEntry = false;
        $attendance = DB::transaction(function () use (
            $owner,
            $clientId,
            $entryCode,
            $detectedAt,
            $data,
            $action,
            &$selectedMemberships,
            &$duplicateEntry,
        ): ?AttendanceSheet {
            if ($owner instanceof Person) {
                $owner = Person::query()->lockForUpdate()->findOrFail($owner->id);

                if ($this->isDuplicatePersonEntryOnDetectedDate($owner, $action, $detectedAt)) {
                    $duplicateEntry = true;

                    return null;
                }
            }

            if ($owner instanceof Person && $action === 'entry' && $selectedMemberships->isNotEmpty()) {
                $selectedMemberships = $selectedMemberships
                    ->map(fn(PersonMembership $membership) => $membership->person_id === $owner->id
                        ? $this->consumeMembershipVisit($membership, $detectedAt)
                        : app(GuestEntryService::class)->consumeVisit($owner, $membership, $detectedAt))
                    ->values();
            }

            $attendance = $this->attendanceSheetRepository->create([
                'relation_id' => $owner->id,
                'relation_type' => get_class($owner),
                'gym_id' => $clientId,
                'entry_code' => $entryCode,
                'date' => $detectedAt,
                'type' => $data->type ?? null,
                'direction' => $action,
                'online' => $data->online ?? null,
                'local_ip' => $data->local_ip ?? null,
                'mac' => $data->mac ?? null,
                'created_at' => $detectedAt,
                'updated_at' => $detectedAt,
            ]);

            if ($selectedMemberships->isNotEmpty()) {
                $attendance->personMemberships()->sync($selectedMemberships->pluck('id')->all());
            }

            return $attendance;
        });

        if ($duplicateEntry) {
            return $this->duplicateEntryResponse($owner, $action, $clientId, $entryCode, $detectedAt, [
                'mac' => $data->mac ?? null,
                'scan_type' => $data->type ?? null,
                'online' => $data->online ?? null,
                'local_ip' => $data->local_ip ?? null,
                'detected_at' => $detectedAt->toDateTimeString(),
            ]);
        }

        $selectedMembership = $selectedMemberships->first();

        $payload = $this->makeSocketPayload([
            'status' => 'success',
            'access_allowed' => true,
            'owner_type' => $ownerType,
            'action' => $action,
            'message' => $ownerType === 'user' ? 'User entry allowed' : 'Person entry allowed',
            'person' => $ownerType === 'person' ? $this->personPayload($owner) : null,
            'user' => $ownerType === 'user' ? $this->userPayload($owner) : null,
            'membership_activation_context' => $ownerType === 'person'
                ? $this->membershipSelectionContext($owner, (int) $clientId, $detectedAt)
                : null,
            'entry_code' => $entryCode,
            'client_id' => $clientId,
            'mac' => $data->mac ?? null,
            'scan_type' => $data->type ?? null,
            'selected_membership' => $selectedMembership ? $this->membershipPayload($selectedMembership) : null,
            'selected_memberships' => $selectedMemberships
                ->map(fn(PersonMembership $membership) => $this->membershipPayload($membership))
                ->values()
                ->all(),
            'attendance_id' => $attendance->id,
            'date' => $attendance->date,
            'detected_at' => $detectedAt->toDateTimeString(),
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

    /**
     * Registers a scan coming from a keyboard-wedge RFID reader on the people page.
     * This intentionally does not use the public turnstile EES request or its MAC lookup.
     */
    public function manualScan(User $operator, string $rawEntryCode, string $direction): object
    {
        abort_unless(
            $operator->hasRole('manager'),
            403,
            'You are not allowed to register a manual scan.'
        );

        $clientId = (int) $operator->gym_id;
        if ($clientId <= 0) {
            throw ValidationException::withMessages([
                'gym' => 'A gym must be assigned before using the manual scanner.',
            ]);
        }

        $action = match ($direction) {
            'enter' => 'entry',
            'exit' => 'exit',
            default => throw ValidationException::withMessages(['direction' => 'Direction must be enter or exit.']),
        };

        [$entryCode, $timestamp] = $this->parseEntryCode($rawEntryCode);
        $entryCode = $this->normalizeEntryCode($entryCode, 'standart');
        $detectedAt = $this->resolveDeviceTime($timestamp) ?? now(self::LOCAL_TIMEZONE);
        $resolved = $this->resolveEntryCodeOwner($entryCode, $clientId, 'rfId', false);

        if (!$resolved) {
            $payload = $this->makeSocketPayload([
                'status' => 'denied',
                'access_allowed' => false,
                'owner_type' => null,
                'reason' => 'invalid_entry_code',
                'message' => 'Invalid entry code',
                'entry_code' => $entryCode,
                'client_id' => $clientId,
                'scan_type' => 'rfId',
                'manual_scan' => true,
                'detected_at' => $detectedAt->toDateTimeString(),
            ]);

            $this->broadcastEntryAttempt($clientId, $payload);

            return $this->deniedResponse('denied', 'invalid_entry_code', null, $action);
        }

        $ownerType = $resolved['owner_type'];
        $owner = $resolved['owner'];
        $selectedMemberships = collect();

        if ($ownerType === 'person' && $action === 'entry' && $owner->is_blocked) {
            $payload = $this->makeSocketPayload([
                'status' => 'denied',
                'access_allowed' => false,
                'owner_type' => 'person',
                'action' => $action,
                'reason' => 'person_blocked',
                'message' => 'Person is blocked and cannot enter.',
                'person' => $this->personPayload($owner),
                'entry_code' => $entryCode,
                'client_id' => $clientId,
                'scan_type' => 'rfId',
                'manual_scan' => true,
                'detected_at' => $detectedAt->toDateTimeString(),
            ]);

            $this->broadcastEntryAttempt($clientId, $payload);

            return $this->deniedResponse('denied', 'person_blocked', $ownerType, $action);
        }

        if ($ownerType === 'person' && $action === 'entry' && !$this->hasActiveSubscription($owner, $clientId, $detectedAt)) {
            $payload = $this->makeSocketPayload([
                'status' => 'denied',
                'access_allowed' => false,
                'owner_type' => 'person',
                'reason' => 'subscription_expired',
                'message' => 'Subscription expired or unavailable.',
                'person' => $this->personPayload($owner),
                'entry_code' => $entryCode,
                'client_id' => $clientId,
                'scan_type' => 'rfId',
                'manual_scan' => true,
                'detected_at' => $detectedAt->toDateTimeString(),
            ]);

            $this->broadcastEntryAttempt($clientId, $payload);

            return $this->deniedResponse('denied', 'subscription_expired', $ownerType, $action);
        }

        if ($this->isDuplicatePersonEntryOnDetectedDate($owner, $action, $detectedAt)) {
            return $this->duplicateEntryResponse($owner, $action, $clientId, $entryCode, $detectedAt, [
                'scan_type' => 'rfId',
                'manual_scan' => true,
                'detected_at' => $detectedAt->toDateTimeString(),
            ]);
        }

        if ($ownerType === 'person' && $action === 'entry') {
            $selectedMemberships = $this->resolveAutomaticMembershipsForEntry(
                $owner,
                $clientId,
                $detectedAt,
                $action,
            );
        }

        if ($ownerType === 'person' && $action === 'exit') {
            $selectedMemberships = $this->resolveMembershipsForExit($owner, $clientId);
        }

        if (
            $ownerType === 'person' &&
            $action === 'entry' &&
            $selectedMemberships->isEmpty() &&
            $this->requiresManagerMembershipSelection($owner, $clientId, $detectedAt)
        ) {
            $payload = $this->makeSocketPayload([
                'status' => 'success',
                'access_allowed' => true,
                'owner_type' => 'person',
                'action' => $action,
                'message' => 'Person entry allowed',
                'person' => $this->personPayload($owner),
                'membership_activation_context' => $this->membershipSelectionContext($owner, $clientId, $detectedAt),
                'pending_attendance_selection' => true,
                'entry_code' => $entryCode,
                'client_id' => $clientId,
                'scan_type' => 'rfId',
                'manual_scan' => true,
                'detected_at' => $detectedAt->toDateTimeString(),
            ]);

            $this->broadcastEntryAttempt($clientId, $payload);

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

        $duplicateEntry = false;
        $attendance = DB::transaction(function () use (
            $owner,
            $clientId,
            $entryCode,
            $detectedAt,
            $action,
            &$selectedMemberships,
            &$duplicateEntry,
        ): ?AttendanceSheet {
            if ($owner instanceof Person) {
                $owner = Person::query()->lockForUpdate()->findOrFail($owner->id);

                if ($this->isDuplicatePersonEntryOnDetectedDate($owner, $action, $detectedAt)) {
                    $duplicateEntry = true;

                    return null;
                }
            }

            if ($owner instanceof Person && $action === 'entry' && $selectedMemberships->isNotEmpty()) {
                $selectedMemberships = $selectedMemberships
                    ->map(fn(PersonMembership $membership) => $membership->person_id === $owner->id
                        ? $this->consumeMembershipVisit($membership, $detectedAt)
                        : app(GuestEntryService::class)->consumeVisit($owner, $membership, $detectedAt))
                    ->values();
            }

            $attendance = $this->attendanceSheetRepository->create([
                'relation_id' => $owner->id,
                'relation_type' => get_class($owner),
                'gym_id' => $clientId,
                'entry_code' => $entryCode,
                'date' => $detectedAt,
                'type' => 'rfId',
                'direction' => $action,
                'created_at' => $detectedAt,
                'updated_at' => $detectedAt,
            ]);

            if ($selectedMemberships->isNotEmpty()) {
                $attendance->personMemberships()->sync($selectedMemberships->pluck('id')->all());
            }

            return $attendance;
        });

        if ($duplicateEntry) {
            return $this->duplicateEntryResponse($owner, $action, $clientId, $entryCode, $detectedAt, [
                'scan_type' => 'rfId',
                'manual_scan' => true,
                'detected_at' => $detectedAt->toDateTimeString(),
            ]);
        }

        $selectedMembership = $selectedMemberships->first();
        $payload = $this->makeSocketPayload([
            'status' => 'success',
            'access_allowed' => true,
            'owner_type' => $ownerType,
            'action' => $action,
            'message' => $ownerType === 'user' ? 'User entry allowed' : 'Person entry allowed',
            'person' => $ownerType === 'person' ? $this->personPayload($owner) : null,
            'user' => $ownerType === 'user' ? $this->userPayload($owner) : null,
            'membership_activation_context' => $ownerType === 'person'
                ? $this->membershipSelectionContext($owner, $clientId, $detectedAt)
                : null,
            'entry_code' => $entryCode,
            'client_id' => $clientId,
            'scan_type' => 'rfId',
            'manual_scan' => true,
            'selected_membership' => $selectedMembership ? $this->membershipPayload($selectedMembership) : null,
            'selected_memberships' => $selectedMemberships
                ->map(fn(PersonMembership $membership) => $this->membershipPayload($membership))
                ->values()
                ->all(),
            'attendance_id' => $attendance->id,
            'date' => $attendance->date,
            'detected_at' => $detectedAt->toDateTimeString(),
        ]);

        $this->broadcastEntryAttempt($clientId, $payload);

        Log::info('manual_entry_scan', [
            'client_id' => $clientId,
            'entry_code' => $entryCode,
            'owner_type' => $ownerType,
            'owner_id' => $owner->id,
            'action' => $action,
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

    /**
     * A turnstile can send either an already stored token (for example "1")
     * or a binary RFID payload. Always try the literal token first: this is
     * essential when different gyms use the same short code. The converted
     * fallback preserves support for existing RFID readers.
     */
    private function entryCodeCandidates(?string $entryCode, ?string $entryCodeType): array
    {
        $entryCode = trim((string) $entryCode);

        if ($entryCode === '') {
            return [];
        }

        $normalized = $this->normalizeEntryCode($entryCode, $entryCodeType);

        return array_values(array_unique(array_filter([
            $entryCode,
            $normalized,
        ], fn (?string $candidate) => $candidate !== null && $candidate !== '')));
    }

    private function resolveEesEntryCodeOwner(array $entryCodeCandidates, int $clientId, ?string $type, mixed $autoAdd): ?array
    {
        foreach ($entryCodeCandidates as $entryCode) {
            $resolved = $this->resolveEntryCodeOwner($entryCode, $clientId, $type, 0);

            if ($resolved) {
                return $resolved;
            }
        }

        if (!$autoAdd || $entryCodeCandidates === []) {
            return null;
        }

        // Keep the legacy auto-add behavior for binary readers, while never
        // creating a literal code before all valid lookup candidates fail.
        return $this->resolveEntryCodeOwner(
            $entryCodeCandidates[array_key_last($entryCodeCandidates)],
            $clientId,
            $type,
            $autoAdd,
        );
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
        return $this->validMembershipsForTurnstile($person, $clientId, $referenceTime)->isNotEmpty();
    }

    public function finalizeTurnstileMembershipSelection(array $membershipIds, User $user, array $context): array
    {
        $membershipIds = array_values(array_unique(array_filter(
            array_map('intval', $membershipIds),
            fn(int $membershipId) => $membershipId > 0,
        )));

        if ($membershipIds === []) {
            throw ValidationException::withMessages(['membership_ids' => 'Select at least one membership.']);
        }

        $guestId = (int) ($context['guest_id'] ?? 0);
        if ($guestId > 0 && count($membershipIds) !== 1) {
            throw ValidationException::withMessages(['membership_ids' => 'Select exactly one membership for a guest entry.']);
        }

        if (($context['action'] ?? null) !== 'entry') {
            throw ValidationException::withMessages(['action' => 'Only entry actions can be finalized from turnstile selection.']);
        }

        $detectedAt = isset($context['detected_at'])
            ? Carbon::parse($context['detected_at'], self::LOCAL_TIMEZONE)
            : now(self::LOCAL_TIMEZONE);

        return DB::transaction(function () use ($membershipIds, $user, $context, $detectedAt, $guestId): array {
            $firstMembership = PersonMembership::query()
                ->whereKey($membershipIds[0])
                ->first();

            if (!$firstMembership) {
                throw ValidationException::withMessages(['membership_ids' => 'One or more selected memberships do not exist.']);
            }

            // Every entry flow locks the person before memberships. This keeps the
            // daily-entry check atomic and avoids a lock-order conflict with manual entry.
            $person = Person::query()->lockForUpdate()->findOrFail($guestId ?: $firstMembership->person_id);

            $memberships = PersonMembership::query()
                ->with(['person', 'membershipPlan.translations', 'membershipPlan.MembershipCategory.translations'])
                ->whereIn('id', $membershipIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if (count($membershipIds) !== $memberships->count()) {
                throw ValidationException::withMessages(['membership_ids' => 'One or more selected memberships do not exist.']);
            }

            $selected = collect($membershipIds)->map(fn(int $id) => $memberships->get($id));
            $first = $selected->first();

            if ($person->is_blocked) {
                throw ValidationException::withMessages([
                    'membership_ids' => 'This person is blocked and cannot enter.',
                ]);
            }

            foreach ($selected as $membership) {
                if ($membership->gym_id !== $first->gym_id
                    || ($guestId === 0 && $membership->person_id !== $first->person_id)
                    || ($guestId > 0 && !app(GuestEntryService::class)->isLinked($person, $membership))) {
                    throw ValidationException::withMessages(['membership_ids' => 'Selected memberships must belong to the same person and gym.']);
                }

                if (!$user->hasRole('owner') && (int) $membership->gym_id !== (int) $user->gym_id) {
                    abort(403, 'You are not allowed to register this entry.');
                }

                if (!$this->membershipIsValidForTurnstile($membership, $detectedAt)) {
                    throw ValidationException::withMessages(['membership_ids' => 'Every selected membership must be active and have visits remaining.']);
                }
            }

            if ($this->isDuplicatePersonEntryOnDetectedDate($person, 'entry', $detectedAt)) {
                throw ValidationException::withMessages([
                    'membership_ids' => 'This person already has an entry recorded for this day.',
                ]);
            }

            $selected->whereIn('status', ['waiting', 'expired'])
                ->each(fn(PersonMembership $membership) => $membership->update([
                    'status' => 'active',
                    'activated_at' => now(self::LOCAL_TIMEZONE),
                ]));

            $selected = $selected
                ->map(fn(PersonMembership $membership) => $guestId > 0
                    ? app(GuestEntryService::class)->consumeVisit($person, $membership, $detectedAt)
                    : $this->consumeMembershipVisit($membership->fresh([
                    'person',
                    'membershipPlan.translations',
                    'membershipPlan.MembershipCategory.translations',
                ]), $detectedAt))
                ->values();
            $primaryMembership = $selected->first();

            $attendance = $this->attendanceSheetRepository->create([
                'relation_id' => $person->id,
                'relation_type' => Person::class,
                'gym_id' => $primaryMembership->gym_id,
                'entry_code' => $context['entry_code'] ?? null,
                'date' => $detectedAt,
                'type' => $context['scan_type'] ?? null,
                'direction' => 'entry',
                'online' => $context['online'] ?? null,
                'local_ip' => $context['local_ip'] ?? null,
                'mac' => $context['mac'] ?? null,
                'created_at' => $detectedAt,
                'updated_at' => $detectedAt,
            ]);
            $attendance->personMemberships()->sync($selected->pluck('id')->all());

            $membershipPayloads = $selected
                ->map(fn(PersonMembership $membership) => $this->membershipPayload($membership))
                ->all();

            return [
                'attendance_id' => $attendance->id,
                'membership' => $membershipPayloads[0],
                'memberships' => $membershipPayloads,
            ];
        });
    }

    private function detectNextAction(?string $ownerType, ?int $ownerId, int $clientId): string
    {
        if (!$ownerType || !$ownerId) {
            return 'unknown';
        }

        $relationType = $ownerType === 'person' ? Person::class : User::class;
        $lastAttendance = AttendanceSheet::query()
            ->where('gym_id', $clientId)
            ->where('relation_type', $relationType)
            ->where('relation_id', $ownerId)
            ->latest('date')
            ->latest('id')
            ->first();

        return $lastAttendance?->direction === 'entry' ? 'exit' : 'entry';
    }

    private function isDuplicatePersonEntryOnDetectedDate(User|Person $owner, string $action, Carbon $detectedAt): bool
    {
        if (!$owner instanceof Person || $action !== 'entry') {
            return false;
        }

        return AttendanceSheet::query()
            ->where('relation_type', Person::class)
            ->where('relation_id', $owner->id)
            ->where('direction', 'entry')
            ->whereDate('created_at', $detectedAt->copy()->timezone(self::LOCAL_TIMEZONE)->toDateString())
            ->exists();
    }

    private function duplicateEntryResponse(
        Person $person,
        string $action,
        int $clientId,
        ?string $entryCode,
        Carbon $detectedAt,
        array $details = [],
    ): object {
        $usedMemberships = $this->membershipsUsedForPersonEntryOnDetectedDate($person, $detectedAt);

        Log::info('duplicate_person_entry_ignored', [
            'client_id' => $clientId,
            'entry_code' => $entryCode,
            'owner_type' => 'person',
            'owner_id' => $person->id,
            'action' => $action,
        ]);

        // The manager receives an information-only modal, without membership
        // selection or an action that could create a second daily entry.
        $this->broadcastEntryAttempt($clientId, $this->makeSocketPayload(array_merge([
            'status' => 'duplicate',
            'access_allowed' => true,
            'owner_type' => 'person',
            'action' => $action,
            'reason' => 'entry_already_recorded_today',
            'message' => 'This person already has an entry recorded for this day.',
            'person' => $this->personPayload($person),
            'membership_activation_context' => null,
            'selected_membership' => $usedMemberships->first(),
            'selected_memberships' => $usedMemberships->all(),
            'entry_code' => $entryCode,
            'client_id' => $clientId,
            'duplicate_entry' => true,
        ], $details)));

        return (object) [
            'message' => 'entry_already_recorded_today',
            'result' => [
                'access_allowed' => true,
                'status' => 'duplicate',
                'reason' => 'entry_already_recorded_today',
                'owner_type' => 'person',
                'action' => $action,
            ],
        ];
    }

    private function membershipsUsedForPersonEntryOnDetectedDate(Person $person, Carbon $detectedAt)
    {
        return AttendanceSheet::query()
            ->with([
                'personMemberships.person:id,name,surname',
                'personMemberships.membershipPlan.translations',
                'personMemberships.membershipPlan.MembershipCategory.translations',
            ])
            ->where('relation_type', Person::class)
            ->where('relation_id', $person->id)
            ->where('direction', 'entry')
            ->whereDate('created_at', $detectedAt->copy()->timezone(self::LOCAL_TIMEZONE)->toDateString())
            ->latest('created_at')
            ->latest('id')
            ->get()
            ->flatMap(fn(AttendanceSheet $attendance) => $attendance->personMemberships)
            ->unique('id')
            ->map(fn(PersonMembership $membership) => $this->membershipPayload($membership))
            ->values();
    }

    private function broadcastEntryAttempt(int $clientId, array $payload): void
    {
        try {
            event(new TurnstileEntryDetected($clientId, $payload));
        } catch (BroadcastException $exception) {
            Log::warning('turnstile_broadcast_failed', [
                'client_id' => $clientId,
                'message' => $exception->getMessage(),
            ]);
        }
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
            'birth_date' => $person->birth_date ?? null,
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

    private function validMembershipsForTurnstile(Person $person, int $clientId, Carbon $referenceTime)
    {
        $referenceDate = $referenceTime->copy()->timezone(self::LOCAL_TIMEZONE)->toDateString();

        if ($person->type === 'guest') {
            return app(GuestEntryService::class)->availableMemberships($person, $clientId, $referenceTime);
        }

        $memberships = $person->memberships()
            ->with([
                'membershipPlan.translations',
                'membershipPlan.MembershipCategory.translations',
            ])
            ->where('gym_id', $clientId)
            // An old expiration process may leave status as expired.  For entry
            // validity, valid_at is the sole expiration source of truth.
            ->whereIn('status', ['active', 'waiting', 'expired'])
            ->where(function ($query) use ($referenceDate) {
                $query->whereNull('start_date')
                    ->orWhereDate('start_date', '<=', $referenceDate);
            })
            ->where(function ($query) use ($referenceDate) {
                $query->whereNull('valid_at')
                    ->orWhereDate('valid_at', '>=', $referenceDate);
            })
            ->orderByDesc('id')
            ->get()
            ->filter(fn(PersonMembership $membership) => $this->membershipHasVisitAvailableForEntry($membership, $referenceTime))
            ->values();

        return $memberships->isNotEmpty()
            ? $memberships
            : app(GuestEntryService::class)->availableMemberships($person, $clientId, $referenceTime);
    }

    private function membershipIsValidForTurnstile(PersonMembership $membership, Carbon $referenceTime): bool
    {
        if (!in_array($membership->status, ['waiting', 'active', 'expired'], true)) {
            return false;
        }

        if ($membership->start_date && $membership->start_date->gt($referenceTime->copy()->startOfDay())) {
            return false;
        }

        if ($membership->valid_at && $membership->valid_at->lt($referenceTime->copy()->startOfDay())) {
            return false;
        }

        return $this->membershipHasVisitAvailableForEntry($membership, $referenceTime);
    }

    private function membershipSelectionContext(Person $person, int $clientId, Carbon $referenceTime): ?array
    {
        $memberships = $this->validMembershipsForTurnstile($person, $clientId, $referenceTime);

        if ($memberships->isEmpty()) {
            return null;
        }

        $activeMemberships = $memberships->where('status', 'active')->values()->all();
        $waitingMemberships = $memberships->where('status', 'waiting')->values()->all();
        $distinctPlanIds = $memberships
            ->pluck('membership_plan_id')
            ->filter()
            ->unique()
            ->values();
        $distinctCategoryIds = $memberships
            ->pluck('membershipPlan.membership_category_id')
            ->filter()
            ->unique()
            ->values();
        $selectionMemberships = $memberships->values();
        $guestEntry = $selectionMemberships->first()?->person_id !== $person->id;

        return [
            'requires_manager_selection' => $selectionMemberships->count() > ($guestEntry ? 1 : 2),
            'guest_entry' => $guestEntry,
            'has_multiple_plans' => $distinctPlanIds->count() > 1,
            'has_multiple_categories' => $distinctCategoryIds->count() > 1,
            'active_memberships' => collect($activeMemberships)->map(
                fn(PersonMembership $membership) => $this->membershipPayload($membership)
            )->values()->all(),
            'waiting_memberships' => collect($waitingMemberships)->map(
                fn(PersonMembership $membership) => $this->membershipPayload($membership)
            )->values()->all(),
            'selectable_memberships' => $selectionMemberships->map(
                fn(PersonMembership $membership) => $this->membershipPayload($membership)
            )->values()->all(),
        ];
    }

    private function resolveAutomaticMembershipsForEntry(Person $person, int $clientId, Carbon $referenceTime, string $action)
    {
        if ($action !== 'entry') {
            return collect();
        }

        $memberships = $this->validMembershipsForTurnstile($person, $clientId, $referenceTime);
        $selectionMemberships = $memberships->values();

        // One or two valid memberships are registered automatically together.
        // With three or more, the manager must choose the memberships to use.
        $guestEntry = $selectionMemberships->first()?->person_id !== $person->id;
        if ($selectionMemberships->isEmpty() || $selectionMemberships->count() > ($guestEntry ? 1 : 2)) {
            return collect();
        }

        return $selectionMemberships
            ->map(function (PersonMembership $membership) {
                if (in_array($membership->status, ['waiting', 'expired'], true)) {
                    $membership->update([
                        'status' => 'active',
                        'activated_at' => now(self::LOCAL_TIMEZONE),
                    ]);
                }

                return $membership->fresh([
                    'membershipPlan.translations',
                    'membershipPlan.MembershipCategory.translations',
                ]);
            })
            ->values();
    }

    private function resolveMembershipsForExit(Person $person, int $clientId)
    {
        $lastEntryAttendance = AttendanceSheet::query()
            ->with('personMemberships')
            ->where('relation_id', $person->id)
            ->where('relation_type', Person::class)
            ->where('gym_id', $clientId)
            ->where('direction', 'entry')
            ->whereHas('personMemberships')
            ->latest('date')
            ->latest('id')
            ->first();

        if (!$lastEntryAttendance) {
            return collect();
        }

        return $lastEntryAttendance->personMemberships
            ->filter(fn (PersonMembership $membership) => $membership->person_id === $person->id
                || app(GuestEntryService::class)->isLinked($person, $membership))
            ->where('gym_id', $clientId)
            ->whereIn('status', ['active', 'waiting'])
            ->values();
    }

    private function requiresManagerMembershipSelection(Person $person, int $clientId, Carbon $referenceTime): bool
    {
        $memberships = $this->validMembershipsForTurnstile($person, $clientId, $referenceTime);

        $guestEntry = $memberships->first()?->person_id !== $person->id;

        return $memberships->count() > ($guestEntry ? 1 : 2);
    }

    private function consumeMembershipVisit(PersonMembership $membership, Carbon $entryAt): PersonMembership
    {
        if ($membership->visits_left !== null && $this->membershipAlreadyEnteredOnDate($membership, $entryAt)) {
            return $membership;
        }

        if ($membership->visits_left !== null && (int) $membership->visits_left <= 0) {
            throw ValidationException::withMessages([
                'membership' => 'No visits left for this membership.',
            ]);
        }

        $usage = [
            'visits_used' => (int) $membership->visits_used + 1,
        ];

        if ($membership->visits_left !== null) {
            $usage['visits_left'] = (int) $membership->visits_left - 1;
        }

        $membership->update($usage);

        return $membership->fresh([
            'membershipPlan.translations',
            'membershipPlan.MembershipCategory.translations',
        ]);
    }

    private function membershipHasVisitAvailableForEntry(PersonMembership $membership, Carbon $entryAt): bool
    {
        return $membership->visits_left === null
            || (int) $membership->visits_left > 0
            || $this->membershipAlreadyEnteredOnDate($membership, $entryAt);
    }

    private function membershipAlreadyEnteredOnDate(PersonMembership $membership, Carbon $entryAt): bool
    {
        $dayStart = $entryAt->copy()->timezone(self::LOCAL_TIMEZONE)->startOfDay();
        $dayEnd = $dayStart->copy()->addDay();

        return AttendanceSheet::query()
            ->where('relation_type', Person::class)
            ->where('relation_id', $membership->person_id)
            ->where('direction', 'entry')
            ->where('date', '>=', $dayStart)
            ->where('date', '<', $dayEnd)
            ->whereHas('personMemberships', fn($query) => $query->where('person_memberships.id', $membership->id))
            ->exists();
    }

    private function membershipPayload(PersonMembership $membership): array
    {
        $plan = $membership->membershipPlan;
        $category = $plan?->MembershipCategory;

        return [
            'id' => $membership->id,
            'person_id' => $membership->person_id,
            'membership_owner_name' => $membership->relationLoaded('person')
                ? trim(($membership->person?->name ?? '').' '.($membership->person?->surname ?? ''))
                : null,
            'status' => $membership->status,
            'start_date' => optional($membership->start_date)->toDateString() ?? $membership->start_date,
            'valid_at' => optional($membership->valid_at)->toDateString() ?? $membership->valid_at,
            'end_date' => optional($membership->end_date)->toDateString() ?? $membership->end_date,
            'membership_plan_id' => $membership->membership_plan_id,
            'membership_plan_name' => $plan?->name,
            'membership_category_id' => $plan?->membership_category_id,
            'membership_category_name' => $category?->name,
            'visits_left' => $membership->visits_left,
        ];
    }
}
