<?php

namespace App\Services\Memberships;

use App\Interfaces\Memberships\MembershipPlanInterface;
use App\Interfaces\MembershipPlanSchedule\MembershipPlanScheduleInterface;
use App\Interfaces\MembershipPlanTrainer\MembershipPlanTrainerInterface;
use App\Repositories\Memberships\MembershipCategoryRepository;
use App\Repositories\ScheduleName\ScheduleNameRepository;
use Illuminate\Support\Facades\DB;

class MembershipPlanService
{

    public function __construct(
        protected MembershipPlanInterface $membershipPlanRepository,
        protected MembershipPlanTrainerInterface $membershipPlanTrainerRepository,
        protected MembershipPlanScheduleInterface $membershipPlanScheduleRepository,
        protected ScheduleNameRepository $scheduleNameRepository,
        protected MembershipCategoryRepository $membershipCategoryRepository
    ) {}


    public function getAllPaginated()
    {
        $user = auth()->user();

        return $this->membershipPlanRepository->paginate(10, ['gym_id' => $user->gym_id], ['translations', 'MembershipCategory']);
    }
    //
    //
    //public function getById($id)
    //{
    //    return $this->membershipPlanRepository->findOrFail($id, ['translations']);
    //}
    //
    //public function store($dto)
    //{
    //    DB::beginTransaction();
    //
    //    try {
    //
    //        $dto->prepare();
    //
    //        $membershipPlan = $this->membershipPlanRepository->create(
    //            $dto->toArray()
    //        );
    //
    //        foreach ($dto->translations as $locale => $translation) {
    //
    //            $membershipPlan->translations()->create([
    //                'locale' => $locale,
    //                'name' => $translation['name'],
    //                'description' => $translation['description'] ?? null,
    //            ]);
    //        }
    //
    //        DB::commit();
    //
    //        return $membershipPlan;
    //
    //    } catch (\Throwable $e) {
    //
    //        DB::rollBack();
    //
    //        throw $e;
    //    }
    //}
    //
    //public function update($id, $dto)
    //{
    //    DB::beginTransaction();
    //    try {
    //
    //        $dto->prepare();
    //
    //        $membershipPlan = $this->membershipPlanRepository->update($id, $dto->toArray());
    //
    //        foreach ($dto->translations as $locale => $translation) {
    //
    //            $membershipPlan->translations()->updateOrCreate(
    //                ['locale' => $locale],
    //                [
    //                    'name' => $translation['name'],
    //                    'description' => $translation['description'] ?? null,
    //                ]
    //            );
    //        }
    //
    //        DB::commit();
    //
    //        return $membershipPlan;
    //
    //    } catch (\Throwable $e) {
    //
    //        DB::rollBack();
    //
    //        throw $e;
    //    }
    //
    //}

    public function getCreateData()
    {
        $scheduls = $this->scheduleNameRepository->getAllWithTrainerByGym();
        //dd($scheduls);
        return ['membershipCategories' => $this->membershipPlanRepository->getCreateData(), 'scheduleNames' => $scheduls]; //$this->membershipPlanRepository->getCreateData();
    }

    //public function store(array $data)
    //{
    //    return $this->membershipPlanRepository->store($data);
    //}

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $membershipPlan = $this->membershipPlanRepository->store($data);

            //foreach ($data['translations'] ?? [] as $locale => $translation) {
            //    //$this->translationRepository->store([
            //    //    'membership_plan_id' => $membershipPlan->id,
            //    //    'locale' => $locale,
            //    //    'name' => $translation['name'] ?? null,
            //    //    'description' => $translation['description'] ?? null,
            //    //]);
            //    $membershipPlan->translations()->create([
            //        'locale' => $locale,
            //        'name' => $translation['name'],
            //        'description' => $translation['description'] ?? null,
            //    ]);
            //}
            foreach ($data['translations'] as $locale => $translation) {
                $membershipPlan->translations()->updateOrCreate(
                    [
                        'membership_plan_id' => $membershipPlan->id,
                        'locale' => $locale,
                    ],
                    [
                        'name' => $translation['name'],
                        'description' => $translation['description'] ?? null,
                    ]
                );
            }

            foreach ($data['trainers'] ?? [] as $trainerItem) {
                if (empty($trainerItem['trainer_id'])) {
                    continue;
                }

                $this->membershipPlanTrainerRepository->store([
                    'membership_plan_id' => $membershipPlan->id ?? null,
                    'trainer_id' => $trainerItem['trainer_id'] ?? null,
                    'price_type' => $trainerItem['price_type'] ?? null,
                    'price_value' => $trainerItem['price_value'] ?? null,
                    'total_price' => $trainerItem['total_price'] ?? null,
                ]);

                //foreach ($trainerItem['schedule_ids'] ?? [] as $scheduleId) {
                $this->membershipPlanScheduleRepository->store([
                    'membership_plan_id' => $membershipPlan->id ?? null,
                    'schedule_id' => $data['schedule_name_id'] ?? null,
                ]);
                //}
            }



            return $membershipPlan;
        });
    }

    public function edit(string $locale, int $id)
    {
        $membershipPlan = $this->membershipPlanRepository->findForEdit($id, $locale);

        return [
            'membershipPlan' => $membershipPlan,
            'membershipCategories' => $this->membershipCategoryRepository->getAllForSelectByGymId(),
            'scheduleNames' => $this->scheduleNameRepository->getAllWithTrainerByGym(),
        ];
    }

    public function update(int $id, array $data): void
    {
        DB::transaction(function () use ($id, $data) {
            $membershipPlan = $this->membershipPlanRepository->update($id, [
                'membership_category_id' => $data['membership_category_id'],
                'price' => $data['price'],
                'price_type' => $data['price_type'],
                'price_value' => $data['price_value'],
                'duration_type' => $data['duration_type'],
                'duration_value' => $data['duration_value'] ?? null,
                'visits_limit' => $data['visits_limit'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'guest_limit' => $data['guest_limit'] ?? 0,
                'freeze_limit' => $data['freeze_limit'] ?? 0,
                'active' => $data['active'] ?? false,
            ]);

            foreach ($data['translations'] as $locale => $translation) {
                //$this->membershipPlanTranslationRepository->updateOrCreate([
                //    'membership_plan_id' => $membershipPlan->id,
                //    'locale' => $locale,
                //], [
                //    'name' => $translation['name'],
                //    'description' => $translation['description'] ?? null,
                //]);
                $membershipPlan->translations()->updateOrCreate(
                    [
                        'membership_plan_id' => $membershipPlan->id,
                        'locale' => $locale,
                    ],
                    [
                        'name' => $translation['name'],
                        'description' => $translation['description'] ?? null,
                    ]
                );
            }

            $this->membershipPlanScheduleRepository->deleteByMembershipPlanId($membershipPlan->id);

            if (!empty($data['schedule_name_id'])) {
                $this->membershipPlanScheduleRepository->store([
                    'membership_plan_id' => $membershipPlan->id,
                    'schedule_id' => $data['schedule_name_id'],
                ]);
            }

            $this->membershipPlanTrainerRepository->deleteByMembershipPlanId($membershipPlan->id);

            foreach ($data['trainers'] ?? [] as $trainer) {
                $this->membershipPlanTrainerRepository->store([
                    'membership_plan_id' => $membershipPlan->id,
                    'trainer_id' => $trainer['trainer_id'],
                    'price_type' => $trainer['price_type'],
                    'price_value' => $trainer['price_value'],
                    'total_price' => $trainer['total_price'],
                ]);
            }
        });
    }
}
