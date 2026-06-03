<?php

namespace App\Services\Memberships;

use App\Interfaces\Memberships\MembershipPlanInterface;
use Illuminate\Support\Facades\Auth;

class MembershipPlanService
{

    public function __construct(protected MembershipPlanInterface $membershipPlanRepository)
    {
    }


    public function getAllPaginated()
    {
        return $this->membershipPlanRepository
            ->paginateForUser(auth()->user(), 10);
    }


    public function getById($id)
    {
        // return $this->membershipPlanRepository->getById($id)->load('roles');
        return $this->membershipPlanRepository->findOrFail($id, ['roles']);
    }

    public function store($data)
    {

        $dataStore = $this->dataToArray($data);

        $user = $this->userRepository->create($dataStore);
        $user->assignRole($data->roles);

        return $user;
    }

    public function update($id, $data)
    {

        $dataUpdate = $this->dataToArray($data);

        if (empty($dataUpdate['password'])) {
            unset($dataUpdate['password']);
        }

        $user = $this->userRepository->update($id, $dataUpdate);

        $user->syncRoles($data->roles);

        return $user;
    }


    protected function dataToArray($data)
    {

        $authUser = Auth::user();

        if ($authUser->hasRole('owner')) {
            if (empty($data->gym_id)) {
                throw new \Exception('Gym is required');
            }
        } else {
            $data->gym_id = $authUser->gym_id;
        }

        return $data->toArray();

    }


}
