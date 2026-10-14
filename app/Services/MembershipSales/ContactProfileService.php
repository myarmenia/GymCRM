<?php

namespace App\Services\MembershipSales;

use App\Models\ContactNote;
use App\Models\ContactProfile;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContactProfileService
{
    private const DETAILS = ['name', 'surname', 'email', 'birth_date', 'gender', 'relationship'];

    public function forPhone(int $gymId, string $phone): ?ContactProfile
    {
        $normalized = ContactNotePhone::canonical($phone);
        return $normalized === '' ? null : ContactProfile::query()
            ->where('gym_id', $gymId)->where('phone_normalized', $normalized)->first();
    }

    public function forNote(User $manager, string $phone, array $details = []): ContactProfile
    {
        $normalized = ContactNotePhone::canonical($phone);
        if ($normalized === '' || $manager->gym_id === null) {
            throw ValidationException::withMessages(['phone_number' => __('validation.required', ['attribute' => 'phone_number'])]);
        }

        $profile = ContactProfile::query()->where('gym_id', $manager->gym_id)
            ->where('phone_normalized', $normalized)->lockForUpdate()->first();
        if ($profile && (int) $profile->user_id !== (int) $manager->id) {
            $prior = User::query()->find($profile->user_id);
            if ($prior?->active && (int) $prior->gym_id === (int) $manager->gym_id && $prior->hasRole('sales_manager')) {
                throw ValidationException::withMessages(['phone_number' => __('backend.membership_sales.contact_note_manager_required')]);
            }
            ContactProfile::query()->where('group_uuid', $profile->group_uuid)->lockForUpdate()->get()
                ->each(function (ContactProfile $member) use ($manager): void {
                    $member->user_id = $manager->id;
                    $member->save();
                });
            $profile->refresh();
        }
        if (! $profile) {
            $profile = new ContactProfile([
                'gym_id' => $manager->gym_id,
                'user_id' => $manager->id,
                'is_primary' => true,
                'phone_number' => trim($phone),
                'phone_normalized' => $normalized,
                ...$this->details($details),
            ]);
            $profile->uuid = (string) \Illuminate\Support\Str::uuid();
            $profile->group_uuid = $profile->uuid;
            $profile->save();
        } elseif ($details !== []) {
            $profile->fill(array_filter($this->details($details), fn ($value) => $value !== null && $value !== ''));
            $profile->save();
        }
        return $profile;
    }

    public function addRelated(User $manager, ContactProfile $primary, array $data): ContactProfile
    {
        return DB::transaction(function () use ($manager, $primary, $data): ContactProfile {
            $this->assertOwner($manager, $primary);
            Gym::query()->whereKey($primary->gym_id)->lockForUpdate()->firstOrFail();
            $normalized = ContactNotePhone::canonical((string) $data['phone_number']);
            if ($normalized === '') {
                throw ValidationException::withMessages(['phone_number' => __('validation.required', ['attribute' => 'phone_number'])]);
            }
            if ($this->forPhone((int) $primary->gym_id, $data['phone_number'])) {
                throw ValidationException::withMessages(['phone_number' => __('validation.unique', ['attribute' => 'phone_number'])]);
            }
            $profile = ContactProfile::query()->create([
                'gym_id' => $primary->gym_id,
                'user_id' => $primary->user_id,
                'group_uuid' => $primary->group_uuid,
                'is_primary' => false,
                'phone_number' => trim($data['phone_number']),
                'phone_normalized' => $normalized,
                ...$this->details($data),
            ]);
            if (filled($data['note'] ?? null)) {
                ContactNote::query()->create([
                    'user_id' => $manager->id,
                    'contact_profile_id' => $profile->id,
                    'phone_number' => $profile->phone_number,
                    'note' => trim($data['note']),
                ]);
            }
            return $profile;
        });
    }

    public function linkExisting(User $manager, ContactProfile $primary, array $data): ContactProfile
    {
        return DB::transaction(function () use ($manager, $primary, $data): ContactProfile {
            $this->assertOwner($manager, $primary);
            Gym::query()->whereKey($primary->gym_id)->lockForUpdate()->firstOrFail();
            $primary = ContactProfile::query()->lockForUpdate()->findOrFail($primary->id);
            abort_unless($primary->is_primary, 404);

            $normalized = ContactNotePhone::canonical((string) $data['phone_number']);
            $existing = $normalized === '' ? null : ContactProfile::query()
                ->where('gym_id', $primary->gym_id)
                ->where('phone_normalized', $normalized)
                ->lockForUpdate()->first();
            if (! $existing) {
                throw ValidationException::withMessages(['phone_number' => __('validation.exists', ['attribute' => 'phone_number'])]);
            }
            $this->assertOwner($manager, $existing);
            if ($existing->group_uuid === $primary->group_uuid) {
                throw ValidationException::withMessages(['phone_number' => __('backend.contact_notes.already_linked')]);
            }
            if (! $existing->is_primary || ContactProfile::query()
                ->where('group_uuid', $existing->group_uuid)->where('id', '<>', $existing->id)->exists()) {
                throw ValidationException::withMessages(['phone_number' => __('backend.contact_notes.standalone_required')]);
            }

            $existing->group_uuid = $primary->group_uuid;
            $existing->is_primary = false;
            $existing->relationship = $data['relationship'] ?? null;
            $existing->save();

            return $existing;
        });
    }

    public function update(User $manager, ContactProfile $profile, array $data): void
    {
        $this->assertOwner($manager, $profile);
        $profile->fill($this->details($data));
        $profile->save();
    }

    private function assertOwner(User $manager, ContactProfile $profile): void
    {
        abort_unless($manager->hasRole('sales_manager')
            && (int) $profile->user_id === (int) $manager->id
            && (int) $profile->gym_id === (int) $manager->gym_id, 403);
    }

    private function details(array $data): array
    {
        return array_intersect_key($data, array_flip(self::DETAILS));
    }
}
