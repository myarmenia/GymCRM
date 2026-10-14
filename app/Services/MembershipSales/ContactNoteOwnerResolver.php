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
        if ($profile) {
            return $profile->user?->active && (int) $profile->user->gym_id === $gymId
                && $profile->user->hasRole('sales_manager')
                ? $profile->user : null;
        }

        // Legacy notes without a profile retain the original first-note rule.
        return ContactNote::query()
            ->with('user')
            ->whereIn(DB::raw(ContactNotePhone::normalizedSql('phone_number')), $phones)
            ->whereHas('user', fn ($query) => $query
                ->whereHas('roles', fn ($roles) => $roles->where('name', 'sales_manager'))
                ->where('active', true)
                ->where('gym_id', $gymId))
            ->orderBy('created_at')
            ->orderBy('id')
            ->when($lockForUpdate, fn ($query) => $query->lockForUpdate())
            ->first()?->user;
    }
}
