<?php

namespace App\Services\People;

use App\Interfaces\People\PersonInterface;
use App\Models\AttendanceSheet;
use App\Models\EntryCode;
use App\Models\EntryPermission;
use App\Models\GymPerson;
use App\Models\Person;
use App\Services\Audit\PersonAuditService;
use App\Services\EntryCodes\EntryCodeService;
use App\Services\FileUploadService;
use App\Services\MembershipSales\ContactNotePhone;
use App\Services\Reminders\ReminderService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class PersonService
{
    public function __construct(
        protected PersonInterface $personRepository,
        protected FileUploadService $fileUploadService,
        protected ReminderService $reminderService,
        protected PersonAuditService $personAuditService,
        protected EntryCodeService $entryCodeService,
    ) {}

    public function getAllPaginated(array $filters = [])
    {
        return $this->personRepository->paginateForUser(auth()->user(), 10, $filters);
    }

    public function getById($id)
    {
        $person = $this->personRepository->findOrFail((int) $id, ['gyms']);
        $user = Auth::user();
        if ($user && ! $user->hasRole('owner')) {
            abort_unless($person->gyms->contains('id', $user->gym_id), 403);
            $person->setRelation('gyms', $person->gyms->where('id', $user->gym_id)->values());
            $person->forGym((int) $user->gym_id);
        }

        return $person;
    }

    public function profileData(int $id): array
    {
        $user = Auth::user();

        $profilePerson = Person::with('gyms')->when(! $user->hasRole('owner'), fn ($query) => $query->whereHas('gyms', fn ($gyms) => $gyms->where('gyms.id', $user->gym_id)))->findOrFail($id);
        $gymId = $user->hasRole('owner')
            ? (int) request()->query('gym_id', $profilePerson->gyms->first()?->id)
            : (int) $user->gym_id;
        abort_unless($profilePerson->gyms->contains('id', $gymId), 403);
        if (! $user->hasRole('owner') && request()->filled('gym_id')) {
            abort_unless((int) request()->query('gym_id') === $gymId, 403);
        }
        $person = Person::query()
            ->with([
                'gyms' => fn ($query) => $query->where('gyms.id', $gymId),
                'entryPermissions' => fn ($query) => $query->whereHas('entryCode', fn ($code) => $code->where('gym_id', $gymId)),
                'entryPermissions.entryCode.gym:id,name',
                'memberships' => function ($query) use ($gymId) {
                    $query->where('gym_id', $gymId)->latest('id');
                },
                'memberships.membershipPlan.translations',
                'memberships.trainer',
                'memberships.freezes',
                'memberships.guests.guest',
                'memberships.membershipSale.membershipPlan.translations',
                'memberships.membershipSale.payments.paymentMethod.translations',
                'memberships.membershipSale.payments.cardType',
                'memberships.membershipSale.discounts.discount.translations',
                'membershipSales' => function ($query) use ($gymId) {
                    $query->where('gym_id', $gymId)->latest('sold_at')->latest('id');
                },
                'membershipSales.membershipPlan.translations',
                'membershipSales.payments.paymentMethod.translations',
                'membershipSales.payments.cardType',
                'membershipSales.discounts.discount.translations',
            ])
            ->when(! $user->hasRole('owner'), function ($query) use ($user) {
                $query->whereHas('gyms', function ($q) use ($user) {
                    $q->where('gyms.id', $user->gym_id);
                });
            })
            ->findOrFail($id)->forGym($gymId);

        $entryPermission = $person->entryPermissions
            ->sortByDesc('id')
            ->first(fn ($permission) => (bool) $permission->status)
            ?? $person->entryPermissions->sortByDesc('id')->first();

        $entries = AttendanceSheet::query()
            ->where('relation_type', Person::class)
            ->where('relation_id', $person->id)
            ->where('direction', 'entry')
            ->where('gym_id', $gymId);
        $monthStart = now('Asia/Yerevan')->startOfMonth();
        $lastEntry = (clone $entries)->orderByDesc('date')->orderByDesc('id')->first(['date']);

        return [
            'person' => $person,
            'profileGyms' => $user->hasRole('owner') ? $profilePerson->gyms->map->only(['id', 'name'])->values() : [],
            'selectedGymId' => $gymId,
            'blockInfo' => [
                'blocked_at' => $person->gymLink($gymId)?->blocked_at?->toIso8601String(),
                'blocked_by' => $person->gymLink($gymId)?->blockedBy?->only(['id', 'name', 'surname']),
            ],
            'entryCode' => $entryPermission?->entryCode,
            'visitStats' => [
                'total' => (clone $entries)->count(),
                'thisMonth' => (clone $entries)
                    ->where('date', '>=', $monthStart->toDateTimeString())
                    ->where('date', '<', $monthStart->copy()->addMonth()->toDateTimeString())
                    ->count(),
                'lastVisitAt' => $lastEntry?->date?->toIso8601String(),
            ],
            'reminderUsers' => $this->reminderService->usersForSelect($user),
            'defaultReminderRecipientIds' => $this->reminderService->defaultMembershipRecipients($user),
        ];
    }

    public function store($data)
    {
        $phone = ContactNotePhone::canonical((string) $data->phone);
        if (! ContactNotePhone::isValidPersonPhone((string) $data->phone)) {
            throw ValidationException::withMessages(['phone' => __('validation.regex', ['attribute' => 'phone'])]);
        }
        $gymId = (int) Auth::user()?->gym_id;
        abort_unless($gymId, 403);
        $uploadedImage = null;
        try {
            return DB::transaction(function () use ($data, $phone, $gymId, &$uploadedImage) {
                $person = Person::query()->where('phone', $phone)->lockForUpdate()->first();
                if ($person?->gyms()->where('gyms.id', $gymId)->exists()) {
                    throw ValidationException::withMessages(['phone' => __('backend_messages.person_with_this_phone_number_already_exists')]);
                }
                $entryCode = $this->resolveEntryCode($data);
                if (! $person) {
                    $attributes = $this->dataToArray($data);
                    $uploadedImage = $data->image instanceof UploadedFile ? ($attributes['image'] ?? null) : null;
                    $attributes['phone'] = $phone;
                    $person = $this->personRepository->create($attributes);
                }
                $link = GymPerson::withTrashed()->firstOrNew(['person_id' => $person->id, 'gym_id' => $gymId]);
                $link->fill(['type' => $data->type, 'deleted_at' => null]);
                $link->save();
                EntryPermission::query()->create([
                    'entry_code_id' => $entryCode->id, 'relation_type' => Person::class,
                    'relation_id' => $person->id, 'status' => true,
                ]);
                $entryCode->update(['activation' => true]);
                // A permission belongs to this link's aggregate, not to the shared identity.
                $link->increment('version');
                $this->personAuditService->afterCreated($person->forGym($gymId));

                return $person;
            });
        } catch (Throwable $exception) {
            $this->deleteUploadedImage($uploadedImage);
            throw $exception;
        }
    }

    protected function resolveEntryCode($data): EntryCode
    {
        if ($data->entry_code_mode === 'new') {
            $gymId = (int) (Auth::user()?->gym_id ?? 0);
            $entryCodeToken = trim((string) $data->entry_code_token);

            if ($gymId === 0 || $entryCodeToken === '') {
                throw ValidationException::withMessages([
                    'entry_code_token' => __('backend_messages.entry_code_required'),
                ]);
            }

            return $this->entryCodeService->createForGym(
                $gymId,
                $entryCodeToken,
            );
        }

        if ($data->entry_code_id === null) {
            throw ValidationException::withMessages([
                'entry_code_id' => __('backend_messages.entry_code_required'),
            ]);
        }

        return $this->availableEntryCode((int) $data->entry_code_id);
    }

    public function update($id, $data)
    {
        $gymId = (int) Auth::user()?->gym_id;
        abort_unless($gymId, 403);

        return DB::transaction(function () use ($id, $data, $gymId) {
            $person = Person::query()->findOrFail((int) $id)->forGym($gymId);
            $link = GymPerson::query()->where('person_id', $person->id)->where('gym_id', $gymId)->lockForUpdate()->firstOrFail();
            $oldSnapshot = $this->personAuditService->snapshot($person);
            $link->type = $data->type;
            $permissionChanged = $this->syncEntryPermission($person, $data->entry_code_id === null ? null : (int) $data->entry_code_id);
            if ($link->isDirty() || $permissionChanged) {
                $link->version = (int) $link->version + 1;
                $link->save();
            }
            $person->unsetRelation('gyms');
            $this->personAuditService->afterChanged($person, $oldSnapshot);

            return $person;
        });
    }

    public function block(int $id): void
    {
        $gymId = (int) Auth::user()?->gym_id;
        abort_unless($gymId, 403);
        DB::transaction(function () use ($id, $gymId): void {
            $link = GymPerson::query()->where('person_id', $id)->where('gym_id', $gymId)->lockForUpdate()->firstOrFail();
            if (! $link->is_blocked) {
                $link->update(['is_blocked' => true, 'blocked_by' => Auth::id(), 'blocked_at' => now()]);
            }
        });
    }

    protected function dataToArray($data, ?Person $person = null)
    {
        $array = $data->toArray();

        if (($array['image'] ?? null) instanceof UploadedFile) {

            $array['image'] = $this->fileUploadService->upload($array['image'], 'people/images');
        } elseif ($person) {
            $array['image'] = $array['image'] ?? $person->image;
        }

        // Hash password if present
        if (! empty($array['password'])) {
            $array['password'] = Hash::make($array['password']);
        } else {
            unset($array['password']);
        }

        return $array;
    }

    protected function availableEntryCode(int $entryCodeId, ?Person $person = null): EntryCode
    {
        $user = Auth::user();
        $entryCode = EntryCode::query()
            ->where('id', $entryCodeId)
            ->where('status', true)
            ->when($user->gym_id, function ($query) use ($user) {
                $query->where('gym_id', $user->gym_id);
            })
            ->lockForUpdate()
            ->first();

        $assignedElsewhere = $entryCode !== null
            && EntryPermission::query()
                ->where('entry_code_id', $entryCodeId)
                ->where('status', true)
                ->whereNull('deleted_at')
                ->when($person !== null, function ($query) use ($person): void {
                    $query->where(function ($other) use ($person): void {
                        $other
                            ->where('relation_type', '<>', $person->getMorphClass())
                            ->orWhere('relation_id', '<>', $person->id);
                    });
                })
                ->exists();
        $currentPersonOwnsCode = $entryCode !== null
            && $person !== null
            && EntryPermission::query()
                ->where('entry_code_id', $entryCodeId)
                ->where('relation_type', $person->getMorphClass())
                ->where('relation_id', $person->id)
                ->where('status', true)
                ->whereNull('deleted_at')
                ->exists();

        if (! $entryCode || $assignedElsewhere || ((bool) $entryCode->activation && ! $currentPersonOwnsCode)) {
            throw ValidationException::withMessages([
                'entry_code_id' => __('backend_messages.selected_entry_code_unavailable_create_one'),
            ]);
        }

        return $entryCode;
    }

    protected function syncEntryPermission(Person $person, ?int $entryCodeId): bool
    {
        $permissions = $person->entryPermissions()
            ->whereHas('entryCode', fn ($query) => $query->where('gym_id', Auth::user()->gym_id))
            ->whereNull('deleted_at')
            ->get();
        if (
            $entryCodeId !== null
            && $permissions->count() === 1
            && (int) $permissions->first()->entry_code_id === $entryCodeId
            && (bool) $permissions->first()->status
        ) {
            return false;
        }

        $entryCode = $entryCodeId === null
            ? null
            : $this->availableEntryCode($entryCodeId, $person);
        $oldEntryCodeIds = $permissions
            ->pluck('entry_code_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $person->entryPermissions()->whereHas('entryCode', fn ($query) => $query->where('gym_id', Auth::user()->gym_id))->delete();
        if ($entryCode !== null) {
            EntryPermission::query()->create([
                'entry_code_id' => $entryCode->id,
                'relation_type' => $person->getMorphClass(),
                'relation_id' => $person->id,
                'status' => true,
            ]);
            $entryCode->update(['activation' => true]);
        }

        foreach (array_diff($oldEntryCodeIds, $entryCodeId === null ? [] : [$entryCodeId]) as $oldId) {
            $this->releaseEntryCodeIfUnused($oldId);
        }

        return $permissions->isNotEmpty() || $entryCode !== null;
    }

    protected function releaseEntryCodeIfUnused(int $entryCodeId): void
    {
        $stillUsed = EntryPermission::query()
            ->where('entry_code_id', $entryCodeId)
            ->where('status', true)
            ->whereNull('deleted_at')
            ->exists();

        if (! $stillUsed) {
            EntryCode::query()->whereKey($entryCodeId)->update(['activation' => false]);
        }
    }

    protected function deleteUploadedImage(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
