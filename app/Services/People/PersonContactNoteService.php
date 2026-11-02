<?php

namespace App\Services\People;

use App\Models\ContactNote;
use App\Models\ContactProfile;
use App\Models\Person;
use App\Models\User;
use App\Services\MembershipSales\ContactNoteOwnershipService;
use App\Services\MembershipSales\ContactNotePhone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PersonContactNoteService
{
    public function __construct(
        private readonly ContactNoteOwnershipService $ownership,
    ) {}

    public function pageData(Person $person, User $viewer): array
    {
        $variants = ContactNotePhone::variants((string) $person->phone);
        $canAccessGym = $viewer->gym_id !== null
            && $person->gyms->contains('id', $viewer->gym_id);
        $gymIds = $canAccessGym ? [(int) $viewer->gym_id] : [];
        $profile = $canAccessGym && $variants !== [] ? ContactProfile::query()
            ->with('user')
            ->where('gym_id', $viewer->gym_id)
            ->where('phone_normalized', ContactNotePhone::canonical((string) $person->phone))
            ->first() : null;
        $requiresSalesManager = $profile === null && ! $viewer->hasRole('sales_manager');
        $salesManagers = $canAccessGym && $variants !== [] && $requiresSalesManager
            ? User::query()->role('sales_manager')->where('gym_id', $viewer->gym_id)
                ->where('active', true)->orderBy('name')->orderBy('surname')
                ->get(['id', 'name', 'surname'])
            : collect();

        $notes = $variants === [] || $gymIds === [] ? collect() : $this->notesForPhones($variants, $gymIds)
            ->with(['user:id,name,surname', 'writer:id,name,surname'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return [
            'contactNotes' => $notes,
            'canAddContactNote' => $canAccessGym && $variants !== []
                && ($profile !== null
                    ? $profile->user?->hasRole('sales_manager')
                        && (int) $profile->user->gym_id === (int) $viewer->gym_id
                    : ($viewer->hasRole('sales_manager') || $salesManagers->isNotEmpty())),
            'contactNoteRequiresSalesManager' => $requiresSalesManager,
            'contactNoteSalesManagers' => $salesManagers,
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

    public function create(Person $person, User $viewer, string $note, ?int $salesManagerId = null): ContactNote
    {
        abort_unless($viewer->gym_id !== null
            && $person->gyms->contains('id', $viewer->gym_id), 403);

        if (ContactNotePhone::variants((string) $person->phone) === []) {
            throw ValidationException::withMessages(['note' => __('validation.required', ['attribute' => 'phone_number'])]);
        }

        $profile = ContactProfile::query()->where('gym_id', $viewer->gym_id)
            ->where('phone_normalized', ContactNotePhone::canonical((string) $person->phone))->first();
        if ($profile) {
            return $this->ownership->addToProfile($viewer, $profile, trim($note));
        }

        $manager = $viewer->hasRole('sales_manager') ? $viewer : User::query()
            ->role('sales_manager')->where('gym_id', $viewer->gym_id)
            ->where('active', true)->find($salesManagerId);
        if (! $manager) {
            throw ValidationException::withMessages([
                'sales_manager_id' => __('validation.required', ['attribute' => 'sales_manager_id']),
            ]);
        }

        return $this->ownership->create($manager, $person->phone, trim($note), [
            'name' => $person->name,
            'surname' => $person->surname,
            'email' => $person->email,
            'birth_date' => $person->birth_date,
            'gender' => $person->gender,
        ], $viewer);
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
