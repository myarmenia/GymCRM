<?php

namespace App\Services\Memberships;

use App\Interfaces\Memberships\MembershipPlanInterface;
use Illuminate\Support\Facades\DB;

class MembershipPlanService
{

    public function __construct(protected MembershipPlanInterface $membershipPlanRepository)
    {
    }


    public function getAllPaginated()
    {
        $user = auth()->user();

        return $this->membershipPlanRepository->paginate(
            10,
            ['gym_id' => $user->gym_id],
            ['isLocked']
        );
    }


    public function getById($id)
    {
        return $this->membershipPlanRepository->findOrFail($id, ['isLocked']);
    }

    public function store($dto)
    {
        DB::beginTransaction();

        try {

            $dto->prepare();

            $membershipPlan = $this->membershipPlanRepository->create(
                $dto->toArray()
            );

            foreach ($dto->translations as $locale => $translation) {

                $membershipPlan->translations()->create([
                    'locale' => $locale,
                    'name' => $translation['name'],
                    'description' => $translation['description'] ?? null,
                ]);
            }

            DB::commit();

            return $membershipPlan;

        } catch (\Throwable $e) {

            DB::rollBack();

            throw $e;
        }
    }

    // public function update($id, $data)
    // {

    //     $dataUpdate = $this->dataToArray($data);

    //     if (empty($dataUpdate['password'])) {
    //         unset($dataUpdate['password']);
    //     }

    //     $user = $this->userRepository->update($id, $dataUpdate);

    //     $user->syncRoles($data->roles);

    //     return $user;
    // }


    // protected function dataToArray($data)
    // {

    //     $authUser = Auth::user();

    //     if ($authUser->hasRole('owner')) {
    //         if (empty($data->gym_id)) {
    //             throw new \Exception('Gym is required');
    //         }
    //     } else {
    //         $data->gym_id = $authUser->gym_id;
    //     }

    //     return $data->toArray();

    // }


}
