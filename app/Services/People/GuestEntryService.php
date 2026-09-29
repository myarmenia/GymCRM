<?php

namespace App\Services\People;

use App\Models\Guest;
use App\Models\AttendanceSheet;
use App\Models\Person;
use App\Models\PersonMembership;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class GuestEntryService
{
    private const LOCAL_TIMEZONE = 'Asia/Yerevan';

    public function linkedMemberships(Person $guest, ?int $gymId = null): Collection
    {
        return PersonMembership::query()
            ->with([
                'person:id,name,surname,is_blocked',
                'membershipPlan.translations',
                'membershipPlan.MembershipCategory.translations',
                'gym:id,name',
            ])
            ->whereHas('guests', function ($query) use ($guest) {
                $query->where('guest_id', $guest->id)
                    ->whereColumn('guests.person_id', 'person_memberships.person_id');
            })
            ->when($gymId, fn ($query) => $query->where('gym_id', $gymId))
            ->whereIn('status', ['waiting', 'active', 'expired'])
            ->orderByDesc('id')
            ->get();
    }

    public function availableMemberships(Person $guest, int $gymId, Carbon $entryAt): Collection
    {
        $date = $entryAt->copy()->timezone(self::LOCAL_TIMEZONE)->toDateString();

        return $this->linkedMemberships($guest, $gymId)
            ->filter(fn (PersonMembership $membership) =>
                $membership->person !== null
                && !$membership->person->is_blocked
                && $this->isWithinGuestLimit($guest, $membership, $date)
                && (!$membership->start_date || $membership->start_date->toDateString() <= $date)
                && (!$membership->valid_at || $membership->valid_at->toDateString() >= $date)
                && ($membership->visits_left === null
                    || (int) $membership->visits_left > 0
                    || $this->alreadyEnteredOnDate($guest, $membership, $date)))
            ->values();
    }

    private function alreadyEnteredOnDate(Person $guest, PersonMembership $membership, string $date): bool
    {
        return AttendanceSheet::query()
            ->where('relation_type', Person::class)
            ->where('relation_id', $guest->id)
            ->where('direction', 'entry')
            ->whereDate('created_at', $date)
            ->whereHas('personMemberships', fn ($query) => $query->whereKey($membership->id))
            ->exists();
    }

    public function isLinked(Person $guest, PersonMembership $membership): bool
    {
        return Guest::query()
            ->where('guest_id', $guest->id)
            ->where('person_id', $membership->person_id)
            ->where('person_membership_id', $membership->id)
            ->exists();
    }

    public function isWithinGuestLimit(Person $guest, PersonMembership $membership, ?string $date = null): bool
    {
        if (! $this->isLinked($guest, $membership)) {
            return false;
        }

        // guest_left limits actual guest entries, never the number of guest
        // people attached to a membership. A second scan on the same day is
        // allowed through to the duplicate-entry response without using a
        // second guest entry.
        return $membership->guest_left === null
            || (int) $membership->guest_left > 0
            || ($date !== null && $this->alreadyEnteredOnDate($guest, $membership, $date));
    }

    public function hasExhaustedGuestEntryLimit(Person $guest, int $gymId, Carbon $entryAt): bool
    {
        if ($guest->type !== 'guest') {
            return false;
        }

        $date = $entryAt->copy()->timezone(self::LOCAL_TIMEZONE)->toDateString();

        return $this->linkedMemberships($guest, $gymId)->contains(
            fn (PersonMembership $membership) => $membership->person !== null
                && ! $membership->person->is_blocked
                && (! $membership->start_date || $membership->start_date->toDateString() <= $date)
                && (! $membership->valid_at || $membership->valid_at->toDateString() >= $date)
                && $membership->guest_left !== null
                && (int) $membership->guest_left <= 0
                && ! $this->alreadyEnteredOnDate($guest, $membership, $date)
        );
    }

    public function consumeVisit(
        Person $guest,
        PersonMembership $selected,
        Carbon $entryAt,
        bool $consumeGuestEntry = false,
    ): PersonMembership
    {
        $membership = PersonMembership::query()
            ->with('person:id,is_blocked')
            ->lockForUpdate()
            ->findOrFail($selected->id);
        $date = $entryAt->copy()->timezone(self::LOCAL_TIMEZONE)->toDateString();

        if (!$this->isLinked($guest, $membership)
            || !in_array($membership->status, ['waiting', 'active', 'expired'], true)
            || !$membership->person
            || $membership->person->is_blocked
            || ($membership->start_date && $membership->start_date->toDateString() > $date)
            || ($membership->valid_at && $membership->valid_at->toDateString() < $date)) {
            throw ValidationException::withMessages([
                'membership_id' => 'This guest is not covered by a valid membership.',
            ]);
        }

        if ($membership->visits_left !== null && (int) $membership->visits_left <= 0) {
            throw ValidationException::withMessages([
                'membership_id' => 'No guest visits left for this membership.',
            ]);
        }

        if ($consumeGuestEntry && $membership->guest_left !== null && (int) $membership->guest_left <= 0) {
            throw ValidationException::withMessages([
                'membership_id' => 'No guest entries left for this membership.',
            ]);
        }

        $updates = [
            'visits_used' => (int) $membership->visits_used + 1,
            'visits_left' => $membership->visits_left === null
                ? null
                : (int) $membership->visits_left - 1,
        ];

        if ($consumeGuestEntry) {
            $updates['guest_used'] = (int) $membership->guest_used + 1;
            $updates['guest_left'] = $membership->guest_left === null
                ? null
                : (int) $membership->guest_left - 1;
        }

        $membership->update($updates);

        return $membership->fresh([
            'person:id,name,surname',
            'membershipPlan.translations',
            'membershipPlan.MembershipCategory.translations',
        ]);
    }
}
