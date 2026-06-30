<?php

namespace App\Services\People;

use App\Interfaces\People\PersonInterface;
use App\Models\EntryCode;
use App\Models\EntryPermission;
use App\Models\Person;
use App\Services\EntryCodes\EntryCodeService;
use App\Services\FileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PersonService
{
    public function __construct(
        protected PersonInterface $personRepository,
        protected EntryCodeService $entryCodeService,
        protected FileUploadService $fileUploadService,
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
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
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
        ];
    }

    public function store($data)
    {
        $entryCode = $this->availableEntryCode((int) $data->entry_code_id);
        $dataStore = $this->dataToArray($data);
        $person = $this->personRepository->create($dataStore);

        // Attach gyms based on current user's role (sales_manager auto-assign)
        $this->syncGyms($person);

        // Entry code association
        EntryPermission::create([
            'entry_code_id' => $entryCode->id,
            'relation_type' => Person::class,
            'relation_id'   => $person->id,
            'status'        => 1,
        ]);
        $this->entryCodeService->activateEntryCode($entryCode->id, true);

        return $person;
    }

    public function update($id, $data)
    {
        $person = $this->personRepository->findOrFail((int) $id, ['gyms']);

        $dataUpdate = $this->dataToArray($data, $person);
        $person = $this->personRepository->update((int) $id, $dataUpdate);

        // Sync gyms (sales_manager forces his own gym)
        $this->syncGyms($person);

        // Handle entry code changes
        $oldEntryCodeId = $person->entryPermissions()->first()?->entry_code_id;

        if ($oldEntryCodeId) {
            $this->entryCodeService->activateEntryCode($oldEntryCodeId, false);
        }

        // Remove existing permissions
        $person->entryPermissions()->delete();

        if (!empty($data->entry_code_id)) {
            EntryPermission::create([
                'entry_code_id' => $data->entry_code_id,
                'relation_type' => Person::class,
                'relation_id'   => $person->id,
                'status'        => 1,
            ]);
            $this->entryCodeService->activateEntryCode($data->entry_code_id, true);
        }

        return $person;
    }

    protected function dataToArray($data, ?Person $person = null)
    {
        $array = $data->toArray();

        if (($array['image'] ?? null) instanceof UploadedFile) {
            if ($person?->image && Storage::disk('public')->exists($person->image)) {
                Storage::disk('public')->delete($person->image);
            }

            $array['image'] = $this->fileUploadService->upload($array['image'], 'people/images');
        } elseif ($person) {
            $array['image'] = $array['image'] ?? $person->image;
        }

        // Hash password if present
        if (!empty($array['password'])) {
            $array['password'] = Hash::make($array['password']);
        } else {
            unset($array['password']);
        }

        return $array;
    }

    protected function availableEntryCode(int $entryCodeId): EntryCode
    {
        $user = Auth::user();
        $entryCode = EntryCode::query()
            ->where('id', $entryCodeId)
            ->where('status', true)
            ->where('activation', false)
            ->when(!$user->hasRole('owner') && $user->gym_id, function ($query) use ($user) {
                $query->where('gym_id', $user->gym_id);
            })
            ->first();

        if (!$entryCode) {
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
    protected function syncGyms($person)
    {
        $user = Auth::user();

        if ($user->hasAnyRole(['sales_manager', 'super_admin']) && $user->gym_id) {
            // Attach only the sales_manager's own gym (many-to-many)
            $person->gyms()->sync([(int) $user->gym_id]);
        }
        // For other roles (admin/owner) we do not modify gym assignments automatically.
        // If you want them to be able to assign gyms, you would need to send gym_ids from frontend.
    }
}
