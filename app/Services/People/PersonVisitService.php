<?php

namespace App\Services\People;

use App\Models\AttendanceSheet;
use App\Models\Person;
use App\Models\PersonMembership;
use App\Models\TrainerCommission;
use App\Models\User;
use App\Services\TrainerMonthlySalaries\TrainerMonthlySalaryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PersonVisitService
{
    private const LOCAL_TIMEZONE = 'Asia/Yerevan';

    public function __construct(
        private readonly TrainerMonthlySalaryService $trainerMonthlySalaryService,
    ) {
    }

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
            ->whereIn('status', ['waiting', 'active', 'expired'])
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->where('gym_id', $user->gym_id);
            })
            ->orderByDesc('id')
            ->get();

        $guestMemberships = app(GuestEntryService::class)->linkedMemberships(
            $person,
            $user->hasRole('owner') ? null : (int) $user->gym_id,
        );
        $memberships = $person->type === 'guest'
            ? $guestMemberships->values()
            : $memberships->concat($guestMemberships)->unique('id')->values();

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
        ?int $membershipId,
        string $manualDateTime,
    ): AttendanceSheet
    {
        $user = Auth::user();
        $person = $this->personQueryForUser($user)->findOrFail($personId);

        if ($person->is_blocked) {
            throw ValidationException::withMessages([
                'membership_id' => 'This person is blocked and cannot enter.',
            ]);
        }

        $now = Carbon::createFromFormat('Y-m-d\TH:i', $manualDateTime, self::LOCAL_TIMEZONE);

        if ($action === 'entry') {
            return DB::transaction(function () use ($person, $user, $membershipId, $now): AttendanceSheet {
                // Lock the person as well as the membership so concurrent manual/API
                // attempts cannot create more than one entry for the same local day.
                $person = Person::query()->lockForUpdate()->findOrFail($person->id);

                if ($person->is_blocked) {
                    throw ValidationException::withMessages([
                        'membership_id' => 'This person is blocked and cannot enter.',
                    ]);
                }

                $membership = $this->entryMembership($person, $user, $membershipId, $now);

                if ($this->personAlreadyEnteredOnSelectedDate($person, $now)) {
                    throw ValidationException::withMessages([
                        'membership_id' => 'This person already has an entry recorded for this day.',
                    ]);
                }

                $membership = $this->activateWaitingMembership($membership, $now);
                $membership = $membership->person_id === $person->id
                    ? $this->consumeVisitIfNeeded($membership, $now)
                    : app(GuestEntryService::class)->consumeVisit($person, $membership, $now, true);
                $attendance = AttendanceSheet::create([
                    'relation_id' => $person->id,
                    'relation_type' => Person::class,
                    'gym_id' => $membership->gym_id,
                    'entry_code' => 'manual',
                    'date' => $now,
                    'type' => 'manual',
                    'direction' => 'entry',
                    'online' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $attendance->personMemberships()->sync([$membership->id]);
                $this->generateTrainerSalaryForEntry($membership, $now);

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
            $attendance->personMemberships()->sync($lastEntry->personMemberships->modelKeys());

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
            ->where(function ($query) use ($person) {
                if ($person->type === 'guest') {
                    $query->whereHas('guests', function ($guestQuery) use ($person) {
                        $guestQuery->where('guest_id', $person->id)
                            ->whereColumn('guests.person_id', 'person_memberships.person_id');
                    });

                    return;
                }

                $query->where('person_id', $person->id)
                    ->orWhereHas('guests', function ($guestQuery) use ($person) {
                        $guestQuery->where('guest_id', $person->id)
                            ->whereColumn('guests.person_id', 'person_memberships.person_id');
                    });
            })
            ->whereIn('status', ['waiting', 'active', 'expired'])
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->where('gym_id', $user->gym_id);
            })
            ->lockForUpdate()
            ->first();

        if (!$membership) {
            throw ValidationException::withMessages([
                'membership_id' => 'Selected membership is not available for this person.',
            ]);
        }

        if ($membership->person_id !== $person->id
            && !app(GuestEntryService::class)->isLinked($person, $membership)) {
            throw ValidationException::withMessages([
                'membership_id' => 'This guest is not linked to the selected membership.',
            ]);
        }

        $selectedDate = $now->copy()->timezone(self::LOCAL_TIMEZONE)->toDateString();

        if ($membership->start_date && Carbon::parse($membership->start_date)->toDateString() > $selectedDate) {
            throw ValidationException::withMessages([
                'membership_id' => 'This membership has not started yet.',
            ]);
        }

        // Membership expiration is determined only by valid_at.
        if ($membership->valid_at && Carbon::parse($membership->valid_at, self::LOCAL_TIMEZONE)->toDateString() < $selectedDate) {
            throw ValidationException::withMessages([
                'membership_id' => 'This membership is already expired.',
            ]);
        }

        return $membership;
    }

    protected function activateWaitingMembership(PersonMembership $membership, Carbon $now): PersonMembership
    {
        if (!in_array($membership->status, ['waiting', 'expired'], true)) {
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

    protected function consumeVisitIfNeeded(PersonMembership $membership, Carbon $entryAt): PersonMembership
    {
        if ($membership->visits_left === null) {
            return $membership;
        }

        if ($this->membershipAlreadyEnteredOnDate($membership, $entryAt)) {
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

    protected function membershipAlreadyEnteredOnDate(PersonMembership $membership, Carbon $entryAt): bool
    {
        $dayStart = $entryAt->copy()->timezone(self::LOCAL_TIMEZONE)->startOfDay();
        $dayEnd = $dayStart->copy()->addDay();

        return AttendanceSheet::query()
            ->where('relation_type', Person::class)
            ->where('relation_id', $membership->person_id)
            ->where('direction', 'entry')
            ->where('date', '>=', $dayStart)
            ->where('date', '<', $dayEnd)
            ->whereHas('personMemberships', fn ($query) => $query->where('person_memberships.id', $membership->id))
            ->exists();
    }

    protected function personAlreadyEnteredOnSelectedDate(Person $person, Carbon $selectedEntryAt): bool
    {
        return AttendanceSheet::query()
            ->where('relation_type', Person::class)
            ->where('relation_id', $person->id)
            ->where('direction', 'entry')
            ->whereDate('created_at', $selectedEntryAt->copy()->timezone(self::LOCAL_TIMEZONE)->toDateString())
            ->exists();
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

    private function generateTrainerSalaryForEntry(PersonMembership $membership, Carbon $entryAt): void
    {
        if (! $membership->trainer_id) {
            return;
        }

        TrainerCommission::query()
            ->where('person_membership_id', $membership->id)
            ->where('trainer_id', $membership->trainer_id)
            ->whereNull('generation_stopped_at')
            ->get()
            ->each(fn (TrainerCommission $commission) => $this->trainerMonthlySalaryService
                ->generateForCommission($commission, $entryAt));
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
