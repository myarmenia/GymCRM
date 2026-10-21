<?php

namespace App\Services\People;

use App\Models\ContactNote;
use App\Models\Person;
use App\Models\User;
use App\Services\MembershipSales\ContactNoteOwnerResolver;
use App\Services\MembershipSales\ContactNoteOwnershipService;
use App\Services\MembershipSales\ContactNotePhone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PersonContactNoteService
{
    public function __construct(
        private readonly ContactNoteOwnerResolver $owners,
        private readonly ContactNoteOwnershipService $ownership,
    ) {}

    public function pageData(Person $person, User $viewer): array
    {
        $variants = ContactNotePhone::variants((string) $person->phone);
        $gymIds = $viewer->hasRole('owner')
            ? $person->gyms->modelKeys()
            : [(int) $viewer->gym_id];

        $notes = $variants === [] || $gymIds === [] ? collect() : $this->notesForPhones($variants, $gymIds)
            ->with('user:id,name,surname')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $owner = $viewer->hasRole('sales_manager') && $variants !== [] && $viewer->gym_id
            ? $this->owners->forPhone((string) $person->phone, (int) $viewer->gym_id)
            : null;

        return [
            'contactNotes' => $notes,
            'canAddContactNote' => $viewer->hasRole('sales_manager')
                && $variants !== []
                && $viewer->gym_id !== null
                && ($owner === null || (int) $owner->id === (int) $viewer->id),
        ];
    }

    public function loadLatestForPeople(EloquentCollection $people, User $viewer): void
    {
        $people->each(fn (Person $person) => $person->setAttribute('latest_contact_note', null));

        $variants = $people->flatMap(fn (Person $person) => ContactNotePhone::variants((string) $person->phone))
            ->unique()->values()->all();
        $gymIds = $viewer->hasRole('owner')
            ? $people->flatMap(fn (Person $person) => $person->gyms->modelKeys())->unique()->values()->all()
            : ($viewer->gym_id === null ? [] : [(int) $viewer->gym_id]);

        if ($variants === [] || $gymIds === []) {
            return;
        }

        $notesByPhone = $this->notesForPhones($variants, $gymIds)
            ->select(['id', 'user_id', 'contact_profile_id', 'phone_number', 'note', 'created_at'])
            ->with(['profile:id,gym_id', 'user:id,gym_id'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (ContactNote $note) => ContactNotePhone::canonical($note->phone_number));

        foreach ($people as $person) {
            $allowedGymIds = $viewer->hasRole('owner')
                ? $person->gyms->modelKeys()
                : [(int) $viewer->gym_id];
            $latest = $notesByPhone->get(ContactNotePhone::canonical((string) $person->phone))
                ?->first(fn (ContactNote $note) => in_array(
                    (int) ($note->profile?->gym_id ?? $note->user?->gym_id),
                    $allowedGymIds,
                    true,
                ));

            $person->setAttribute('latest_contact_note', $latest?->note);
        }
    }

    public function create(Person $person, User $viewer, string $note): ContactNote
    {
        abort_unless($viewer->hasRole('sales_manager')
            && $viewer->gym_id !== null
            && $person->gyms->contains('id', $viewer->gym_id), 403);

        if (ContactNotePhone::variants((string) $person->phone) === []) {
            throw ValidationException::withMessages(['note' => __('validation.required', ['attribute' => 'phone_number'])]);
        }

        return $this->ownership->create($viewer, $person->phone, trim($note), [
            'name' => $person->name,
            'surname' => $person->surname,
            'email' => $person->email,
            'birth_date' => $person->birth_date,
            'gender' => $person->gender,
        ]);
    }

    private function notesForPhones(array $variants, array $gymIds): Builder
    {
        return ContactNote::query()
            ->whereIn(DB::raw(ContactNotePhone::normalizedSql('phone_number')), $variants)
            ->where(function (Builder $query) use ($gymIds) {
                $query->whereHas('profile', fn (Builder $profiles) => $profiles->whereIn('gym_id', $gymIds))
                    ->orWhere(function (Builder $legacy) use ($gymIds) {
                        $legacy->whereNull('contact_profile_id')
                            ->whereHas('user', fn (Builder $users) => $users->whereIn('gym_id', $gymIds));
                    });
            });
    }
}
