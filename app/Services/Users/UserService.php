<?php

namespace App\Services\Users;

use App\Interfaces\Users\UserInterface;
use App\Models\EntryPermission;
use App\Models\User;
use App\Services\EntryCodes\EntryCodeService;
use App\Services\FileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Laravel\Reverb\Loggers\Log;

class UserService
{

    public function __construct(
        protected UserInterface $userRepository,
        protected EntryCodeService $entryCodeService,
        protected FileUploadService $fileUploadService
    )
    {
    }


    public function getAllPaginated(array $filters = [])
    {
        return $this->userRepository
            ->paginateForUser(auth()->user(), 10, $filters);
    }


    public function getById($id){
        // return $this->userRepository->getById($id)->load('roles');
        return $this->userRepository->findOrFail($id, ['roles']);
    }

    public function store($data)
    {
        $dataStore = $this->dataToArray($data);
        $user = $this->userRepository->create($dataStore);
        $user->assignRole($data->roles);

        Log::info('User created with ID: ' . $user->id);
        Log::info('data: ' . json_encode($data));

        // Փոխված տողը ստորև
        if (!empty($data->entry_code_id)) {
            EntryPermission::create([
                'entry_code_id' => $data->entry_code_id,
                'relation_type' => User::class,
                'relation_id'   => $user->id,
                'status'        => 1,
            ]);

            $this->entryCodeService->activateEntryCode($data->entry_code_id, true);
        }

        return $user;
    }
    public function update($id, $data)
    {
        $existingUser = $this->userRepository->findOrFail($id);
        $dataUpdate = $this->dataToArray($data, $existingUser);

        if (empty($dataUpdate['password'])) {
            unset($dataUpdate['password']);
        }

        $user = $this->userRepository->update($id, $dataUpdate);
        $user->syncRoles($data->roles);

        $oldEntryCodeId = $user->entryPermissions()->first()?->entry_code_id;

        if ($oldEntryCodeId) {
            $this->entryCodeService->activateEntryCode($oldEntryCodeId, false);
        }

        // Remove all existing entry_permissions for this user
        $user->entryPermissions()->delete();

        // Create new association if entry_code_id is provided
        if (!empty($data->entry_code_id)) {   // <--- ուղղված
            EntryPermission::create([
                'entry_code_id' => $data->entry_code_id,
                'relation_type' => User::class,
                'relation_id'   => $user->id,
                'status'        => 1,
            ]);

            $this->entryCodeService->activateEntryCode($data->entry_code_id, true);
        }

        return $user;
    }


    protected function dataToArray($data, ?User $existingUser = null){

        $authUser = Auth::user();

        if ($authUser->hasRole('owner')) {
            if (empty($data->gym_id)) {
                throw new \Exception('Gym is required');
            }
        } else {
            $data->gym_id = $authUser->gym_id;
        }

        $array = $data->toArray();

        if (($array['image'] ?? null) instanceof UploadedFile) {
            if ($existingUser?->image && Storage::disk('public')->exists($existingUser->image)) {
                Storage::disk('public')->delete($existingUser->image);
            }

            $array['image'] = $this->fileUploadService->upload($array['image'], 'users/images');
        } elseif ($existingUser) {
            $array['image'] = $array['image'] ?? $existingUser->image;
        }

        return $array;

    }

    public function getTrainers()
    {
        return $this->userRepository->getTrainersWithSchedulesByGymId();
    }


}
