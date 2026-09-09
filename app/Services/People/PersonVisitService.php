<?php

namespace App\Services\People;

use App\Models\AttendanceSheet;
use App\Models\Person;
use App\Models\PersonMembership;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            ->with('personMemberships.membershipPlan.translations')
            ->where('relation_type', Person::class)
            ->where('relation_id', $person->id)
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->where('gym_id', $user->gym_id);
            })
            ->latest('date')
            ->latest('id')
            ->limit(10)
            ->get();

        $lastAttendance = AttendanceSheet::query()
            ->with('personMemberships.membershipPlan.translations')
            ->where('relation_type', Person::class)
            ->where('relation_id', $person->id)
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->where('gym_id', $user->gym_id);
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

            return DB::transaction(function () use ($person, $membership, $now): AttendanceSheet {
                $attendance = AttendanceSheet::create([
                    'relation_id' => $person->id,
                    'relation_type' => Person::class,
                    'gym_id' => $membership->gym_id,
                    'entry_code' => 'manual',
                    'date' => $now,
                    'type' => 'manual',
                    'direction' => 'entry',
                    'online' => 1,
                ]);
                $attendance->personMemberships()->sync([$membership->id]);

                return $attendance;
            });
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

        return DB::transaction(function () use ($person, $lastEntry, $now): AttendanceSheet {
            $attendance = AttendanceSheet::create([
                'relation_id' => $person->id,
                'relation_type' => Person::class,
                'gym_id' => $lastEntry->gym_id,
                'entry_code' => 'manual',
                'date' => $now,
                'type' => 'manual',
                'direction' => 'exit',
                'online' => 1,
            ]);
            $attendance->personMemberships()->sync($lastEntry->personMemberships()->pluck('id')->all());

            return $attendance;
        });
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
                $query->where('gym_id', $user->gym_id);
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
                $query->where('gym_id', $user->gym_id);
            })
            ->latest('date')
            ->latest('id')
            ->first();
    }

    protected function lastEntryAttendance(Person $person, ?Carbon $beforeOrAt = null): ?AttendanceSheet
    {
        $user = Auth::user();

        return AttendanceSheet::query()
            ->with('personMemberships')
            ->where('relation_type', Person::class)
            ->where('relation_id', $person->id)
            ->where('direction', 'entry')
            ->whereHas('personMemberships')
            ->when($beforeOrAt, function ($query) use ($beforeOrAt) {
                $query->where('date', '<=', $beforeOrAt);
            })
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->where('gym_id', $user->gym_id);
            })
            ->latest('date')
            ->latest('id')
            ->first();
    }

}
