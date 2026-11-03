<?php

namespace App\Services\MembershipSales;

use App\Models\ContactNote;
use App\Models\ContactProfile;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContactNoteOwnershipService
{
    public function __construct(
        private readonly ContactNoteOwnerResolver $owners,
        private readonly ContactProfileService $profiles,
    ) {}

    public function create(User $manager, string $phone, string $note, array $details = [], ?User $writer = null): ContactNote
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
        abort_unless($writer === null || (int) $writer->gym_id === (int) $manager->gym_id, 403);

        return DB::transaction(function () use ($manager, $phone, $note, $details, $writer): ContactNote {
            $gymId = (int) $manager->gym_id;
            Gym::query()->whereKey($gymId)->lockForUpdate()->firstOrFail();
            $owner = $this->owners->forPhone($phone, $gymId, lockForUpdate: true);

            if ($owner !== null && $owner->id !== $manager->id) {
                throw ValidationException::withMessages([
                    'phone_number' => __('backend.membership_sales.contact_note_manager_required'),
                ]);
            }

            $profile = $this->profiles->forNote($manager, $phone, $details);
            return ContactNote::query()->create([
                'user_id' => $manager->id,
                'writer_id' => $writer?->id ?? $manager->id,
                'contact_profile_id' => $profile->id,
                'phone_number' => $phone,
                'note' => $note,
            ]);
        });
    }

    public function addToProfile(User $writer, ContactProfile $profile, string $note): ContactNote
    {
        abort_unless($writer->gym_id !== null
            && (int) $profile->gym_id === (int) $writer->gym_id, 403);

        return DB::transaction(function () use ($writer, $profile, $note): ContactNote {
            Gym::query()->whereKey($writer->gym_id)->lockForUpdate()->firstOrFail();
            $profile = ContactProfile::query()->where('gym_id', $writer->gym_id)
                ->lockForUpdate()->findOrFail($profile->id);
            abort_unless(User::query()->whereKey($profile->user_id)
                ->where('gym_id', $writer->gym_id)
                ->whereHas('roles', fn ($roles) => $roles->where('name', 'sales_manager'))
                ->exists(), 403);

            return ContactNote::query()->create([
                'user_id' => $profile->user_id,
                'writer_id' => $writer->id,
                'contact_profile_id' => $profile->id,
                'phone_number' => $profile->phone_number,
                'note' => $note,
            ]);
        });
    }
}
