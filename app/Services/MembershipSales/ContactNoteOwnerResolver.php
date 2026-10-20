<?php

namespace App\Services\MembershipSales;

use App\Models\ContactNote;
use App\Models\ContactProfile;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ContactNoteOwnerResolver
{
    public function forPerson(Person $person, int $gymId): ?User
    {
        return $this->forPhone((string) $person->phone, $gymId);
    }

    public function forPhone(string $phone, int $gymId, bool $lockForUpdate = false): ?User
    {
        $phones = ContactNotePhone::variants($phone);
        if ($phones === []) {
            return null;
        }

        $profile = ContactProfile::query()
            ->with('user')
            ->where('gym_id', $gymId)
            ->where('phone_normalized', ContactNotePhone::canonical($phone))
            ->when($lockForUpdate, fn ($query) => $query->lockForUpdate())
            ->first();
        // A related phone shares the group's owner. The newest group note also
        // corrects profiles migrated from the old first-note ownership rule.
        $latestNote = ContactNote::query()
            ->with('user')
            ->when($profile, fn ($query) => $query->whereHas('profile', fn ($profiles) => $profiles
                ->where('gym_id', $gymId)->where('group_uuid', $profile->group_uuid)))
            ->when(! $profile, fn ($query) => $query
                ->whereIn(DB::raw(ContactNotePhone::normalizedSql('phone_number')), $phones)
                ->whereHas('user', fn ($users) => $users->where('gym_id', $gymId)))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->when($lockForUpdate, fn ($query) => $query->lockForUpdate())
            ->first();

        $owner = $latestNote?->user ?? $profile?->user;

        return $owner?->active && (int) $owner->gym_id === $gymId
            && $owner->hasRole('sales_manager') ? $owner : null;
    }
}
