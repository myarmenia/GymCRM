<?php

namespace App\Services\EntryCodes;

use App\Interfaces\EntryCodes\EntryCodeInterface;
use App\Models\Gym;
use Illuminate\Support\Facades\Auth;

class EntryCodeService
{
    public function __construct(protected EntryCodeInterface $entryCodeRepository) {}

    public function getAllPaginated(int $perPage = 10)
    {
        return $this->entryCodeRepository->paginate($perPage);
    }

    

    public function find(int $id)
    {
        return $this->entryCodeRepository->find($id);
    }

    public function create(array $data)
    {
        return $this->entryCodeRepository->create($data);
    }

    public function update(int $id, array $data)
    {
        return $this->entryCodeRepository->update($id, $data);
    }

    public function delete(int $id)
    {
        return $this->entryCodeRepository->delete($id);
    }

    /**
     * Get gyms for dropdown based on user role
     */
    public function getGymsForCurrentUser()
    {
        $user = auth()->user();

        if ($user->hasRole('owner')) {
            return Gym::all();
        }

        // For non-owner, return only the gym(s) they belong to
        $gymId = $user->gym_id ?? null;
        if ($gymId) {
            return Gym::where('id', $gymId)->get();
        }

        return collect();
    }

    public function getByGymId(int $gymId, ?int $currentId = null)
    {
        return $this->entryCodeRepository->getByGymId($gymId, $currentId);
    }

    public function activateEntryCode(int $entryCodeId, bool $active = true): void
    {
        $this->entryCodeRepository->activate($entryCodeId, $active);
    }
}