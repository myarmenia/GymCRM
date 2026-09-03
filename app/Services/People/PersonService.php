<?php

namespace App\Services\People;

use App\Interfaces\People\PersonInterface;
use App\Models\EntryCode;
use App\Models\EntryPermission;
use App\Models\Person;
use App\Services\Audit\PersonAuditService;
use App\Services\FileUploadService;
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
    ) {}

    public function getAllPaginated(array $filters = [])
    {
        return $this->personRepository->paginateForUser(auth()->user(), 10, $filters);
    }

    public function getById($id)
    {
        return $this->personRepository->findOrFail((int) $id, ['gyms']);
    }

    public function profileData(int $id): array
    {
        $user = Auth::user();

        $person = Person::query()
            ->with([
                'gyms',
                'entryPermissions.entryCode.gym:id,name',
                'memberships' => function ($query) {
                    $query->latest('id');
                },
                'memberships.membershipPlan.translations',
                'memberships.trainer',
                'memberships.freezes',
                'memberships.guests.guest',
                'memberships.membershipSale.membershipPlan.translations',
                'memberships.membershipSale.payments.paymentMethod.translations',
                'memberships.membershipSale.payments.cardType',
                'memberships.membershipSale.discounts.discount.translations',
                'membershipSales' => function ($query) {
                    $query->latest('sold_at')->latest('id');
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
            ->findOrFail($id);

        $entryPermission = $person->entryPermissions
            ->sortByDesc('id')
            ->first(fn ($permission) => (bool) $permission->status)
            ?? $person->entryPermissions->sortByDesc('id')->first();

        return [
            'person' => $person,
            'entryCode' => $entryPermission?->entryCode,
            'reminderUsers' => $this->reminderService->usersForSelect($user),
            'defaultReminderRecipientIds' => $this->reminderService->defaultMembershipRecipients($user),
        ];
    }

    public function store($data)
    {
        $dataStore = $this->dataToArray($data);
        $uploadedImage = $data->image instanceof UploadedFile
            ? ($dataStore['image'] ?? null)
            : null;

        try {
            return DB::transaction(function () use ($data, $dataStore) {
                $entryCode = $this->availableEntryCode((int) $data->entry_code_id);
                $person = $this->personRepository->create($dataStore);

                $this->syncGyms($person);
                EntryPermission::query()->create([
                    'entry_code_id' => $entryCode->id,
                    'relation_type' => Person::class,
                    'relation_id' => $person->id,
                    'status' => true,
                ]);
                $entryCode->update(['activation' => true]);
                $this->personAuditService->afterCreated($person);

                return $person;
            });
        } catch (Throwable $exception) {
            $this->deleteUploadedImage($uploadedImage);

            throw $exception;
        }
    }

    public function update($id, $data)
    {
        $existing = $this->personRepository->findOrFail((int) $id, ['gyms']);
        $dataUpdate = $this->dataToArray($data, $existing);
        $uploadedImage = $data->image instanceof UploadedFile
            ? ($dataUpdate['image'] ?? null)
            : null;

        try {
            [$person, $oldImage] = DB::transaction(function () use ($id, $data, $dataUpdate) {
                /** @var Person $person */
                $person = Person::query()
                    ->with('gyms')
                    ->lockForUpdate()
                    ->findOrFail((int) $id);
                $startingVersion = (int) $person->version;
                $oldImage = $person->image;

                $person->fill($dataUpdate);
                $sharedRootChanged = array_diff(
                    array_keys($person->getDirty()),
                    ['image', 'mobile_deleted', 'fcm_token', 'version', 'updated_at'],
                ) !== [];
                $gymChanged = $this->syncGyms($person);
                $permissionChanged = $this->syncEntryPermission(
                    $person,
                    $data->entry_code_id === null ? null : (int) $data->entry_code_id,
                );

                if ($sharedRootChanged || $gymChanged || $permissionChanged) {
                    $person->version = $startingVersion + 1;
                }

                if ($person->isDirty()) {
                    $person->save();
                }

                return [$person, $oldImage];
            });
        } catch (Throwable $exception) {
            $this->deleteUploadedImage($uploadedImage);

            throw $exception;
        }

        if ($uploadedImage !== null && $oldImage !== $uploadedImage) {
            $this->deleteUploadedImage($oldImage);
        }

        return $person;
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
                'entry_code_id' => 'Ընտրված մուտքի կոդը հասանելի չէ։ Ստեղծիր',
            ]);
        }

        return $entryCode;
    }

    /**
     * Automatically assign gym(s) based on the authenticated user's role.
     * - sales_manager: force person to belong to his own gym (user->gym_id)
     * - other roles: do nothing (leave current gyms unchanged)
     */
    protected function syncGyms(Person $person): bool
    {
        $user = Auth::user();

        if ($user->hasAnyRole(['sales_manager', 'super_admin']) && $user->gym_id) {
            $changes = $person->gyms()->syncWithoutDetaching([(int) $user->gym_id]);

            return $changes['attached'] !== [] || $changes['updated'] !== [];
        }

        return false;
    }

    protected function syncEntryPermission(Person $person, ?int $entryCodeId): bool
    {
        $permissions = $person->entryPermissions()
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

        $person->entryPermissions()->delete();
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
