<?php

namespace App\Services\MembershipSales;

use App\Models\ContactNote;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContactNoteOwnershipService
{
    public function __construct(private readonly ContactNoteOwnerResolver $owners) {}

    public function create(User $manager, string $phone, string $note): ContactNote
    {
        $phone = trim($phone);
        if (ContactNotePhone::variants($phone) === []) {
            throw ValidationException::withMessages([
                'phone_number' => __('validation.required', ['attribute' => 'phone_number']),
            ]);
        }

        if (! $manager->hasRole('sales_manager') || $manager->gym_id === null) {
            throw ValidationException::withMessages([
                'phone_number' => __('backend.membership_sales.invalid_sales_manager'),
            ]);
        }

        return DB::transaction(function () use ($manager, $phone, $note): ContactNote {
            $gymId = (int) $manager->gym_id;
            Gym::query()->whereKey($gymId)->lockForUpdate()->firstOrFail();
            $owner = $this->owners->forPhone($phone, $gymId, lockForUpdate: true);

            if ($owner !== null && $owner->id !== $manager->id) {
                throw ValidationException::withMessages([
                    'phone_number' => __('backend.membership_sales.contact_note_manager_required'),
                ]);
            }

            return ContactNote::query()->create([
                'user_id' => $manager->id,
                'phone_number' => $phone,
                'note' => $note,
            ]);
        });
    }
}
