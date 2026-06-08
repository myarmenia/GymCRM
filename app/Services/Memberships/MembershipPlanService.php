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

        return $this->membershipPlanRepository->paginate( 10, ['gym_id' => $user->gym_id], ['translations', 'MembershipCategory']);
    }


    public function getById($id)
    {
        return $this->membershipPlanRepository->findOrFail($id, ['translations']);
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

    public function update($id, $dto)
    {
        DB::beginTransaction();
        try {

            $dto->prepare();

            $membershipPlan = $this->membershipPlanRepository->update($id, $dto->toArray());

            foreach ($dto->translations as $locale => $translation) {

                $membershipPlan->translations()->updateOrCreate(
                    ['locale' => $locale],
                    [
                        'name' => $translation['name'],
                        'description' => $translation['description'] ?? null,
                    ]
                );
            }

            DB::commit();

            return $membershipPlan;

        } catch (\Throwable $e) {

            DB::rollBack();

            throw $e;
        }

    }


}
