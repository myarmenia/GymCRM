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

            $this->broadcastEntryAttempt((int) $clientId, $payload);

            return $this->deniedResponse('denied', 'invalid_entry_code');
        }

        $ownerType = $resolved['owner_type'];
        $owner = $resolved['owner'];
        $action = $this->detectNextAction($ownerType, $owner->id, (int) $clientId);
        $selectedMembership = null;
        $selectedMemberships = collect();

        if (
            $ownerType === 'person' &&
            $action === 'entry' &&
            !$this->hasActiveSubscription($owner, (int) $clientId, $detectedAt)
        ) {
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

        if ($ownerType === 'person' && $action === 'entry') {
            $selectedMembership = $this->resolveAutomaticMembershipForEntry($owner, (int) $clientId, $detectedAt, $action);
            $selectedMemberships = $selectedMembership ? collect([$selectedMembership]) : collect();
        }

        if ($ownerType === 'person' && $action === 'exit') {
            $selectedMemberships = $this->resolveMembershipsForExit($owner, (int) $clientId);
            $selectedMembership = $selectedMemberships->first();
        }

        if (
            $ownerType === 'person' &&
            $action === 'entry' &&
            $selectedMembership === null &&
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

        if ($ownerType === 'person' && $action === 'entry' && $selectedMembership) {
            $selectedMembership = $this->consumeMembershipVisit($selectedMembership);
            $selectedMemberships = collect([$selectedMembership]);
        }

        $attendance = DB::transaction(function () use (
            $owner,
            $clientId,
            $entryCode,
            $detectedAt,
            $data,
            $action,
            $selectedMemberships,
        ): AttendanceSheet {
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
            ]);

            if ($selectedMemberships->isNotEmpty()) {
                $attendance->personMemberships()->sync($selectedMemberships->pluck('id')->all());
            }

            return $attendance;
        });

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
        return $this->validMembershipsForTurnstile($person, $clientId, $referenceTime)->isNotEmpty();
    }

    public function finalizeTurnstileMembershipSelection(array $membershipIds, User $user, array $context): array
    {
        $membershipIds = array_values(array_unique(array_filter(
            array_map('intval', $membershipIds),
            fn (int $membershipId) => $membershipId > 0,
        )));

        if ($membershipIds === []) {
            throw ValidationException::withMessages(['membership_ids' => 'Select at least one membership.']);
        }

        if (($context['action'] ?? null) !== 'entry') {
            throw ValidationException::withMessages(['action' => 'Only entry actions can be finalized from turnstile selection.']);
        }

        $detectedAt = isset($context['detected_at'])
            ? Carbon::parse($context['detected_at'], self::LOCAL_TIMEZONE)
            : now(self::LOCAL_TIMEZONE);

        return DB::transaction(function () use ($membershipIds, $user, $context, $detectedAt): array {
            $memberships = PersonMembership::query()
                ->with(['person', 'membershipPlan.translations', 'membershipPlan.MembershipCategory.translations'])
                ->whereIn('id', $membershipIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if (count($membershipIds) !== $memberships->count()) {
                throw ValidationException::withMessages(['membership_ids' => 'One or more selected memberships do not exist.']);
            }

            $selected = collect($membershipIds)->map(fn (int $id) => $memberships->get($id));
            $first = $selected->first();

            foreach ($selected as $membership) {
                if ($membership->person_id !== $first->person_id || $membership->gym_id !== $first->gym_id) {
                    throw ValidationException::withMessages(['membership_ids' => 'Selected memberships must belong to the same person and gym.']);
                }

                if (!$user->hasRole('owner') && (int) $membership->gym_id !== (int) $user->gym_id) {
                    abort(403, 'You are not allowed to register this entry.');
                }

                if (!$this->membershipIsValidForTurnstile($membership, $detectedAt)) {
                    throw ValidationException::withMessages(['membership_ids' => 'Every selected membership must be active and have visits remaining.']);
                }

                if ($membership->status === 'waiting') {
                    $membership->update(['status' => 'active', 'activated_at' => now(self::LOCAL_TIMEZONE)]);
                }
            }

            $selected = $selected
                ->map(fn (PersonMembership $membership) => $this->consumeMembershipVisit($membership->fresh([
                    'person', 'membershipPlan.translations', 'membershipPlan.MembershipCategory.translations',
                ])))
                ->values();
            $primaryMembership = $selected->first();

            $attendance = $this->attendanceSheetRepository->create([
                'relation_id' => $primaryMembership->person_id,
                'relation_type' => Person::class,
                'gym_id' => $primaryMembership->gym_id,
                'entry_code' => $context['entry_code'] ?? null,
                'date' => $detectedAt,
                'type' => $context['scan_type'] ?? null,
                'direction' => 'entry',
                'online' => $context['online'] ?? null,
                'local_ip' => $context['local_ip'] ?? null,
                'mac' => $context['mac'] ?? null,
            ]);
            $attendance->personMemberships()->sync($selected->pluck('id')->all());

            $membershipPayloads = $selected
                ->map(fn (PersonMembership $membership) => $this->membershipPayload($membership))
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

        return $person->memberships()
            ->with([
                'membershipPlan.translations',
                'membershipPlan.MembershipCategory.translations',
            ])
            ->where('gym_id', $clientId)
            ->whereIn('status', ['active', 'waiting'])
            ->where(function ($query) use ($referenceDate) {
                $query->whereNull('start_date')
                    ->orWhereDate('start_date', '<=', $referenceDate);
            })
            ->where(function ($query) use ($referenceDate) {
                $query->where(function ($validAtQuery) use ($referenceDate) {
                    $validAtQuery->whereNotNull('valid_at')
                        ->whereDate('valid_at', '>=', $referenceDate);
                })->orWhere(function ($endDateQuery) use ($referenceDate) {
                    $endDateQuery->whereNull('valid_at')
                        ->whereDate('end_date', '>=', $referenceDate);
                });
            })
            ->where(function ($query) use ($referenceTime) {
                $query->whereNull('expired_at')
                    ->orWhere('expired_at', '>=', $referenceTime);
            })
            ->where(function ($query) {
                $query->whereNull('visits_left')
                    ->orWhere('visits_left', '>', 0);
            })
            ->orderByDesc('id')
            ->get();
    }

    private function membershipIsValidForTurnstile(PersonMembership $membership, Carbon $referenceTime): bool
    {
        if (!in_array($membership->status, ['waiting', 'active'], true)) {
            return false;
        }

        if ($membership->start_date && $membership->start_date->gt($referenceTime->copy()->startOfDay())) {
            return false;
        }

        $validUntil = $membership->valid_at ?? $membership->end_date;
        if ($validUntil && $validUntil->lt($referenceTime->copy()->startOfDay())) {
            return false;
        }

        if ($membership->expired_at && $membership->expired_at->lt($referenceTime)) {
            return false;
        }

        return $membership->visits_left === null || (int) $membership->visits_left > 0;
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

        return [
            'requires_manager_selection' => $selectionMemberships->count() > 1,
            'has_multiple_plans' => $distinctPlanIds->count() > 1,
            'has_multiple_categories' => $distinctCategoryIds->count() > 1,
            'active_memberships' => collect($activeMemberships)->map(
                fn (PersonMembership $membership) => $this->membershipPayload($membership)
            )->values()->all(),
            'waiting_memberships' => collect($waitingMemberships)->map(
                fn (PersonMembership $membership) => $this->membershipPayload($membership)
            )->values()->all(),
            'selectable_memberships' => $selectionMemberships->map(
                fn (PersonMembership $membership) => $this->membershipPayload($membership)
            )->values()->all(),
        ];
    }

    private function resolveAutomaticMembershipForEntry(Person $person, int $clientId, Carbon $referenceTime, string $action): ?PersonMembership
    {
        if ($action !== 'entry') {
            return null;
        }

        $memberships = $this->validMembershipsForTurnstile($person, $clientId, $referenceTime);
        $selectionMemberships = $memberships->values();

        if ($selectionMemberships->count() !== 1) {
            return null;
        }

        $membership = $selectionMemberships->first();

        if (!$membership) {
            return null;
        }

        if ($membership->status === 'waiting') {
            $membership->update([
                'status' => 'active',
                'activated_at' => now(self::LOCAL_TIMEZONE),
            ]);

            $membership = $membership->fresh([
                'membershipPlan.translations',
                'membershipPlan.MembershipCategory.translations',
            ]);
        }

        return $membership;
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
            ->where('person_id', $person->id)
            ->where('gym_id', $clientId)
            ->whereIn('status', ['active', 'waiting'])
            ->values();
    }

    private function requiresManagerMembershipSelection(Person $person, int $clientId, Carbon $referenceTime): bool
    {
        $memberships = $this->validMembershipsForTurnstile($person, $clientId, $referenceTime);

        return $memberships->count() > 1;
    }

    private function consumeMembershipVisit(PersonMembership $membership): PersonMembership
    {
        if ($membership->visits_left === null) {
            return $membership;
        }

        if ((int) $membership->visits_left <= 0) {
            throw ValidationException::withMessages([
                'membership' => 'No visits left for this membership.',
            ]);
        }

        $membership->update([
            'visits_used' => (int) $membership->visits_used + 1,
            'visits_left' => (int) $membership->visits_left - 1,
        ]);

        return $membership->fresh([
            'membershipPlan.translations',
            'membershipPlan.MembershipCategory.translations',
        ]);
    }

    private function membershipPayload(PersonMembership $membership): array
    {
        $plan = $membership->membershipPlan;
        $category = $plan?->MembershipCategory;

        return [
            'id' => $membership->id,
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
