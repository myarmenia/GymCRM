<?php

namespace App\Services\MembershipSales;

use App\Models\ContactNote;
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

        // The first matching contact note establishes the customer's sales manager.
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
