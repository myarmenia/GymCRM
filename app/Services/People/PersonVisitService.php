<?php

namespace App\Services\People;

use App\Models\AttendanceSheet;
use App\Models\EntryReport;
use App\Models\Person;
use App\Models\PersonMembership;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PersonVisitService
{
    private const LOCAL_TIMEZONE = 'Asia/Yerevan';

    public function pageData(int $personId): array
    {
        $user = Auth::user();
        $person = $this->personQueryForUser($user)
            ->with(['gyms'])
            ->findOrFail($personId);

        $memberships = PersonMembership::query()
            ->with([
                'membershipPlan.translations',
                'membershipPlan.MembershipCategory.translations',
                'gym:id,name',
            ])
            ->where('person_id', $person->id)
            ->whereIn('status', ['waiting', 'active'])
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->where('gym_id', $user->gym_id);
            })
            ->orderByDesc('id')
            ->get();

        $recentAttendances = AttendanceSheet::query()
            ->with('membershipPlan.translations')
            ->where('relation_type', Person::class)
            ->where('relation_id', $person->id)
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->whereHas('membershipPlan', function ($membershipPlanQuery) use ($user) {
                    $membershipPlanQuery->where('gym_id', $user->gym_id);
                });
            })
            ->latest('date')
            ->latest('id')
            ->limit(10)
            ->get();

        $lastAttendance = AttendanceSheet::query()
            ->with('membershipPlan.translations')
            ->where('relation_type', Person::class)
            ->where('relation_id', $person->id)
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->whereHas('membershipPlan', function ($membershipPlanQuery) use ($user) {
                    $membershipPlanQuery->where('gym_id', $user->gym_id);
                });
            })
            ->latest('date')
            ->latest('id')
            ->first();

        return [
            'person' => $person,
            'memberships' => $memberships,
            'recentAttendances' => $recentAttendances,
            'lastAttendance' => $lastAttendance,
        ];
    }

    public function storeManualVisit(
        int $personId,
        string $action,
        ?int $membershipId = null,
        string $manualDateTime,
    ): AttendanceSheet
    {
        $user = Auth::user();
        $person = $this->personQueryForUser($user)->findOrFail($personId);
        $now = Carbon::createFromFormat('Y-m-d\TH:i', $manualDateTime, self::LOCAL_TIMEZONE);

        if ($action === 'entry') {
            $membership = $this->entryMembership($person, $user, $membershipId, $now);
            $membership = $this->activateWaitingMembership($membership, $now);
            $membership = $this->consumeVisitIfNeeded($membership);

            $attendance = AttendanceSheet::create([
                'relation_id' => $person->id,
                'relation_type' => Person::class,
                'membership_plan_id' => $membership->membership_plan_id,
                'entry_code' => 'manual',
                'date' => $now,
                'type' => 'manual',
                'direction' => 'entry',
                'online' => 1,
            ]);

            $this->createManualEntryReport(
                person: $person,
                membership: $membership,
                attendance: $attendance,
                action: 'entry',
                detectedAt: $now,
            );

            return $attendance;
        }

        $lastAttendanceBeforeOrAt = $this->lastAttendanceBeforeOrAt($person, $now);
        $lastEntry = $this->lastEntryAttendance($person, $now);

        if (!$lastEntry) {
            throw ValidationException::withMessages([
                'action' => 'Exit cannot be added because no previous entry was found.',
            ]);
        }

        if ($lastAttendanceBeforeOrAt && $lastAttendanceBeforeOrAt->direction === 'exit') {
            throw ValidationException::withMessages([
                'action' => 'Before this date and time the last recorded visit is already an exit.',
            ]);
        }

        $attendance = AttendanceSheet::create([
            'relation_id' => $person->id,
            'relation_type' => Person::class,
            'membership_plan_id' => $lastEntry->membership_plan_id,
            'entry_code' => 'manual',
            'date' => $now,
            'type' => 'manual',
            'direction' => 'exit',
            'online' => 1,
        ]);

        $membership = $this->membershipForAttendancePlan($person, $lastEntry->membership_plan_id, $user);

        $this->createManualEntryReport(
            person: $person,
            membership: $membership,
            attendance: $attendance,
            action: 'exit',
            detectedAt: $now,
        );

        return $attendance;
    }

    protected function personQueryForUser(User $user)
    {
        return Person::query()->when(!$user->hasRole('owner'), function ($query) use ($user) {
            $query->whereHas('gyms', function ($gymQuery) use ($user) {
                $gymQuery->where('gyms.id', $user->gym_id);
            });
        });
    }

    protected function entryMembership(Person $person, User $user, ?int $membershipId, Carbon $now): PersonMembership
    {
        if (!$membershipId) {
            throw ValidationException::withMessages([
                'membership_id' => 'Membership selection is required for manual entry.',
            ]);
        }

        $membership = PersonMembership::query()
            ->with([
                'membershipPlan.translations',
                'membershipPlan.MembershipCategory.translations',
            ])
            ->where('id', $membershipId)
            ->where('person_id', $person->id)
            ->whereIn('status', ['waiting', 'active'])
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->where('gym_id', $user->gym_id);
            })
            ->first();

        if (!$membership) {
            throw ValidationException::withMessages([
                'membership_id' => 'Selected membership is not available for this person.',
            ]);
        }

        $today = $now->toDateString();

        if ($membership->start_date && Carbon::parse($membership->start_date)->toDateString() > $today) {
            throw ValidationException::withMessages([
                'membership_id' => 'This membership has not started yet.',
            ]);
        }

        $validUntil = $membership->valid_at ?: $membership->end_date;

        if ($validUntil && Carbon::parse($validUntil)->toDateString() < $today) {
            throw ValidationException::withMessages([
                'membership_id' => 'This membership is already expired.',
            ]);
        }

        if ($membership->expired_at && Carbon::parse($membership->expired_at, self::LOCAL_TIMEZONE)->lt($now)) {
            throw ValidationException::withMessages([
                'membership_id' => 'This membership is already expired.',
            ]);
        }

        return $membership;
    }

    protected function activateWaitingMembership(PersonMembership $membership, Carbon $now): PersonMembership
    {
        if ($membership->status !== 'waiting') {
            return $membership;
        }

        $membership->update([
            'status' => 'active',
            'activated_at' => $now,
        ]);

        return $membership->fresh([
            'membershipPlan.translations',
            'membershipPlan.MembershipCategory.translations',
        ]);
    }

    protected function consumeVisitIfNeeded(PersonMembership $membership): PersonMembership
    {
        if ($membership->visits_left === null) {
            return $membership;
        }

        if ((int) $membership->visits_left <= 0) {
            throw ValidationException::withMessages([
                'membership_id' => 'No visits left for this membership.',
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

    protected function lastAttendance(Person $person): ?AttendanceSheet
    {
        $user = Auth::user();

        return AttendanceSheet::query()
            ->where('relation_type', Person::class)
            ->where('relation_id', $person->id)
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->whereHas('membershipPlan', function ($membershipPlanQuery) use ($user) {
                    $membershipPlanQuery->where('gym_id', $user->gym_id);
                });
            })
            ->latest('date')
            ->latest('id')
            ->first();
    }

    protected function lastAttendanceBeforeOrAt(Person $person, Carbon $beforeOrAt): ?AttendanceSheet
    {
        $user = Auth::user();

        return AttendanceSheet::query()
            ->where('relation_type', Person::class)
            ->where('relation_id', $person->id)
            ->where('date', '<=', $beforeOrAt)
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->whereHas('membershipPlan', function ($membershipPlanQuery) use ($user) {
                    $membershipPlanQuery->where('gym_id', $user->gym_id);
                });
            })
            ->latest('date')
            ->latest('id')
            ->first();
    }

    protected function lastEntryAttendance(Person $person, ?Carbon $beforeOrAt = null): ?AttendanceSheet
    {
        $user = Auth::user();

        return AttendanceSheet::query()
            ->where('relation_type', Person::class)
            ->where('relation_id', $person->id)
            ->where('direction', 'entry')
            ->whereNotNull('membership_plan_id')
            ->when($beforeOrAt, function ($query) use ($beforeOrAt) {
                $query->where('date', '<=', $beforeOrAt);
            })
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->whereHas('membershipPlan', function ($membershipPlanQuery) use ($user) {
                    $membershipPlanQuery->where('gym_id', $user->gym_id);
                });
            })
            ->latest('date')
            ->latest('id')
            ->first();
    }

    protected function membershipForAttendancePlan(Person $person, ?int $membershipPlanId, User $user): ?PersonMembership
    {
        if (!$membershipPlanId) {
            return null;
        }

        return PersonMembership::query()
            ->with([
                'membershipPlan.translations',
                'membershipPlan.MembershipCategory.translations',
            ])
            ->where('person_id', $person->id)
            ->where('membership_plan_id', $membershipPlanId)
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->where('gym_id', $user->gym_id);
            })
            ->latest('id')
            ->first();
    }

    protected function createManualEntryReport(
        Person $person,
        ?PersonMembership $membership,
        AttendanceSheet $attendance,
        string $action,
        Carbon $detectedAt,
    ): EntryReport {
        return EntryReport::create([
            'client_id' => $membership?->gym_id ?? Auth::user()?->gym_id,
            'entry_code' => 'manual',
            'owner_type' => 'person',
            'owner_id' => $person->id,
            'action' => $action,
            'status' => 'success',
            'reason' => 'success',
            'access_allowed' => true,
            'mac' => null,
            'device_time' => $detectedAt,
            'detected_at' => $detectedAt,
            'payload' => [
                'status' => 'success',
                'access_allowed' => true,
                'owner_type' => 'person',
                'action' => $action,
                'source' => 'visit_management_manual',
                'person' => $this->personPayload($person),
                'selected_membership' => $membership ? $this->membershipPayload($membership) : null,
                'attendance_id' => $attendance->id,
                'date' => $attendance->date,
                'entry_code' => 'manual',
                'client_id' => $membership?->gym_id ?? Auth::user()?->gym_id,
                'detected_at' => $detectedAt->toDateTimeString(),
            ],
        ]);
    }

    protected function personPayload(Person $person): array
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

    protected function membershipPayload(PersonMembership $membership): array
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
