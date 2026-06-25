<?php

namespace App\Services\Trainer;

use App\Interfaces\GymSchedule\GymScheduleInterface;
use App\Interfaces\Trainer\TrainerInterface;
use App\Interfaces\TrainerSchedule\TrainerScheduleInterface;
use App\Interfaces\TrainerSessionDuration\TrainerSessionDurationInterface;
use App\Interfaces\TrainerSessionDurationSlot\TrainerSessionDurationSlotInterface;
use App\Models\TrainerCommission;
use App\Models\TrainerMonthlySalary;
use App\Models\User;
use App\Services\EntryCodes\EntryCodeService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrainerService
{
    public function __construct(
        protected TrainerInterface $trainerRepository,
        protected TrainerScheduleInterface $trainerScheduleRepository,
        protected EntryCodeService $entryCodeService,
        protected GymScheduleInterface $gymScheduleRepository,
        protected TrainerSessionDurationInterface $trainerSessionDurationRepository,
        protected TrainerSessionDurationSlotInterface $trainerSessionDurationSlotRepository,
    ) {}

    public function getAllPaginated()
    {
        return $this->trainerRepository
            ->paginateForUser(auth()->user(), 1000);
    }

    public function getById($id)
    {
        return $this->trainerRepository->findOrFail($id, ['roles']);
    }

    public function profileData(int $id): array
    {
        $user = Auth::user();

        $trainer = User::query()
            ->with([
                'roles',
                'gym',
                'trainedPersonMemberships' => function ($query) {
                    $query->latest('id');
                },
                'trainedPersonMemberships.person',
                'trainedPersonMemberships.membershipPlan.translations',
                'trainerCommissions' => function ($query) {
                    $query->latest('id');
                },
                'trainerCommissions.personMembership.person',
                'trainerCommissions.personMembership.membershipPlan.translations',
                'trainerMonthlySalaries' => function ($query) {
                    $query->latest('salary_month')->latest('id');
                },
                'trainerMonthlySalaries.personMembership.person',
                'trainerMonthlySalaries.personMembership.membershipPlan.translations',
                'trainerMonthlySalaries.trainerCommission',
            ])
            ->whereHas('roles', function ($query) {
                $query->where('roles.id', 7);
            })
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->where('gym_id', $user->gym_id);
            })
            ->findOrFail($id);

        return [
            'trainer' => $trainer,
        ];
    }

    public function salaryPageData(int $id): array
    {
        $user = Auth::user();

        $trainer = User::query()
            ->with([
                'roles',
                'gym',
                'trainerCommissions' => function ($query) {
                    $query->latest('id');
                },
                'trainerCommissions.personMembership.person',
                'trainerCommissions.personMembership.membershipPlan.translations',
                'trainerCommissions.monthlySalaries' => function ($query) {
                    $query->latest('salary_month')->latest('id');
                },
                'trainerCommissions.monthlySalaries.personMembership.person',
                'trainerCommissions.monthlySalaries.personMembership.trainer',
                'trainerCommissions.monthlySalaries.personMembership.membershipPlan.translations',
            ])
            ->whereHas('roles', function ($query) {
                $query->where('roles.id', 7);
            })
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->where('gym_id', $user->gym_id);
            })
            ->findOrFail($id);

        return [
            'trainer' => $trainer,
        ];
    }

    public function updateMonthlySalaryStatuses(int $trainerId, array $salaryIds, string $action): void
    {
        $salaryIds = array_values(array_unique(array_map('intval', $salaryIds)));

        DB::transaction(function () use ($trainerId, $salaryIds, $action) {
            $this->ensureTrainerIsVisible($trainerId);

            $salaries = TrainerMonthlySalary::query()
                ->whereIn('id', $salaryIds)
                ->lockForUpdate()
                ->get();

            if ($salaries->count() !== count($salaryIds)) {
                throw ValidationException::withMessages([
                    'salary_ids' => 'Ընտրված աշխատավարձերից մեկը չի գտնվել։',
                ]);
            }

            $wrongTrainerExists = $salaries->contains(fn ($salary) => (int) $salary->trainer_id !== (int) $trainerId);

            if ($wrongTrainerExists) {
                throw ValidationException::withMessages([
                    'salary_ids' => 'Ընտրված աշխատավարձը չի պատկանում տվյալ մարզչին։',
                ]);
            }

            $allowedStatuses = ['pending', 'transfer'];
            $invalidStatusExists = $salaries->contains(fn ($salary) => !in_array($salary->status, $allowedStatuses, true));

            if ($invalidStatusExists) {
                throw ValidationException::withMessages([
                    'salary_ids' => $action === 'pay'
                        ? 'Միայն սպասման կամ փոխանցման կարգավիճակով աշխատավարձերը կարող են վճարվել։'
                        : 'Միայն սպասման կամ փոխանցման կարգավիճակով աշխատավարձերը կարող են չեղարկվել։',
                ]);
            }

            $newStatus = $action === 'pay' ? 'paid' : 'cancel';

            TrainerMonthlySalary::query()
                ->whereIn('id', $salaryIds)
                ->update(['status' => $newStatus]);
        });
    }

    public function transferMonthlySalary(int $trainerId, int $salaryId): void
    {
        DB::transaction(function () use ($trainerId, $salaryId) {
            $this->ensureTrainerIsVisible($trainerId);

            $salary = TrainerMonthlySalary::query()
                ->with('personMembership')
                ->whereKey($salaryId)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $salary->trainer_id !== (int) $trainerId) {
                throw ValidationException::withMessages([
                    'salary_id' => 'Ընտրված աշխատավարձը չի պատկանում տվյալ մարզչին։',
                ]);
            }

            if ($salary->status === 'transfer') {
                throw ValidationException::withMessages([
                    'salary_id' => 'Այս աշխատավարձն արդեն փոխանցված է։',
                ]);
            }

            $personMembership = $salary->personMembership;

            if (!$personMembership || !$personMembership->trainer_id) {
                throw ValidationException::withMessages([
                    'salary_id' => 'Աբոնեմենտի ընթացիկ մարզիչը չի գտնվել։',
                ]);
            }

            $newTrainerId = (int) $personMembership->trainer_id;

            if ($newTrainerId === (int) $salary->trainer_id) {
                throw ValidationException::withMessages([
                    'salary_id' => 'Փոխանցումը հնարավոր չէ, քանի որ մարզիչը չի փոխվել։',
                ]);
            }

            $oldCommission = TrainerCommission::query()
                ->whereKey($salary->trainer_commission_id)
                ->lockForUpdate()
                ->first();

            if (!$oldCommission) {
                throw ValidationException::withMessages([
                    'salary_id' => 'Հին մարզչի կոմիսիան չի գտնվել։',
                ]);
            }

            $newCommission = TrainerCommission::query()
                ->where('trainer_id', $newTrainerId)
                ->where('membership_sale_id', $oldCommission->membership_sale_id)
                ->where('person_membership_id', $personMembership->id)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (!$newCommission) {
                throw ValidationException::withMessages([
                    'salary_id' => 'Նոր մարզչի կոմիսիան չի գտնվել։',
                ]);
            }

            $targetSalaryExists = TrainerMonthlySalary::query()
                ->where('trainer_id', $newTrainerId)
                ->where('person_membership_id', $personMembership->id)
                ->where('trainer_commission_id', $newCommission->id)
                ->whereDate('salary_month', $salary->salary_month)
                ->whereKeyNot($salary->id)
                ->exists();

            if ($targetSalaryExists) {
                throw ValidationException::withMessages([
                    'salary_id' => 'Այս ամսվա աշխատավարձն արդեն գոյություն ունի նոր մարզչի համար։',
                ]);
            }

            $amount = (float) $salary->price;

            $oldCommission->update([
                'salary_amount' => max((float) $oldCommission->salary_amount - $amount, 0),
            ]);

            $newCommission->update([
                'salary_amount' => (float) $newCommission->salary_amount + $amount,
            ]);

            $salary->update([
                'trainer_id' => $newTrainerId,
                'trainer_commission_id' => $newCommission->id,
                'status' => 'transfer',
            ]);
        });
    }

    public function saveTrainerScheduleData(int $trainerId, array $data): void
    {
        DB::transaction(function () use ($trainerId, $data) {
            $scheduleIds = collect($data['schedule_names'])
                ->filter()
                ->unique()
                ->values();

            foreach ($scheduleIds as $scheduleId) {
                $this->trainerScheduleRepository->firstOrCreate(
                    $trainerId,
                    $scheduleId
                );
            }

            foreach ($data['session_durations'] ?? [] as $durationData) {
                if (!empty($durationData['id'])) {
                    continue;
                }

                $trainerSchedule = $this->trainerScheduleRepository
                    ->findByTrainerAndScheduleName(
                        $trainerId,
                        $durationData['schedule_name_id']
                    );

                $duration = $this->trainerSessionDurationRepository->updateOrCreate(
                    [
                        'id' => $durationData['id'] ?? null,
                    ],
                    [
                        'trainer_schedule_id' => $trainerSchedule->id,
                        'title' => $durationData['title'],
                        'minutes' => $durationData['minutes'],
                        'type' => $durationData['type'],
                        'price' => $durationData['price'] ?? null,
                    ]
                );

                $existingSlotIds = [];

                foreach ($durationData['slots'] ?? [] as $slotData) {
                    $slot = $this->trainerSessionDurationSlotRepository->updateOrCreate(
                        [
                            'id' => $slotData['id'] ?? null,
                        ],
                        [
                            'session_duration_id' => $duration->id,
                            'week_day' => $slotData['week_day'],
                            'start_time' => $slotData['start_time'],
                            'end_time' => $slotData['end_time'],
                        ]
                    );

                    $existingSlotIds[] = $slot->id;
                }

                $this->trainerSessionDurationSlotRepository->deleteMissingByDuration(
                    $duration->id,
                    $existingSlotIds
                );
            }
        });
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

    public function getTrainerSessionDuration($trainerId)
    {
        return $this->trainerScheduleRepository->getTrainerSessionDuration($trainerId);
    }

    protected function ensureTrainerIsVisible(int $trainerId): void
    {
        $user = Auth::user();

        User::query()
            ->whereKey($trainerId)
            ->whereHas('roles', function ($query) {
                $query->where('roles.id', 7);
            })
            ->when(!$user->hasRole('owner'), function ($query) use ($user) {
                $query->where('gym_id', $user->gym_id);
            })
            ->firstOrFail();
    }
}
