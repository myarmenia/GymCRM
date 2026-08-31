<?php

namespace App\Services\Trainer;

use App\Interfaces\GymSchedule\GymScheduleInterface;
use App\Interfaces\Trainer\TrainerInterface;
use App\Interfaces\TrainerSchedule\TrainerScheduleInterface;
use App\Interfaces\TrainerSessionDuration\TrainerSessionDurationInterface;
use App\Interfaces\TrainerSessionDurationSlot\TrainerSessionDurationSlotInterface;
use App\Models\PersonMembership;
use App\Models\SalaryPayableAssignment;
use App\Models\TrainerMonthlySalary;
use App\Models\TrainerSchedule;
use App\Models\TrainerSessionDuration;
use App\Models\User;
use App\Services\EntryCodes\EntryCodeService;
use App\Services\SalaryPayouts\SalaryPayoutService;
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
        protected SalaryPayoutService $salaryPayoutService,
    ) {}

    public function getAllPaginated(array $filters = [])
    {
        return $this->trainerRepository
            ->paginateForUser(auth()->user(), 1000, $filters);
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
            ->when(! $user->hasRole('owner'), function ($query) use ($user) {
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
            ->when(! $user->hasRole('owner'), function ($query) use ($user) {
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
            $invalidStatusExists = $salaries->contains(fn ($salary) => ! in_array($salary->status, $allowedStatuses, true));

            if ($invalidStatusExists) {
                throw ValidationException::withMessages([
                    'salary_ids' => $action === 'pay'
                        ? 'Միայն սպասման կամ փոխանցման կարգավիճակով աշխատավարձերը կարող են վճարվել։'
                        : 'Միայն սպասման կամ փոխանցման կարգավիճակով աշխատավարձերը կարող են չեղարկվել։',
                ]);
            }

            TrainerMonthlySalary::query()
                ->whereIn('id', $salaryIds)
                ->update([
                    'status' => 'cancel',
                    'version' => DB::raw('version + 1'),
                ]);
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

            if (
                ! in_array($salary->status, ['pending', 'transfer'], true)
            ) {
                throw ValidationException::withMessages([
                    'salary_id' => 'Միայն չվճարված աշխատավարձը կարող է փոխանցվել այլ մարզչի։',
                ]);
            }

            $personMembership = $salary->personMembership;

            if (! $personMembership || ! $personMembership->trainer_id) {
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

            $assignments = SalaryPayableAssignment::query()
                ->where('trainer_monthly_salary_id', $salary->id)
                ->where('payee_id', $trainerId)
                ->where('available_amount', '>', 0)
                ->lockForUpdate()
                ->get();

            if ($assignments->isEmpty()) {
                throw ValidationException::withMessages([
                    'salary_id' => 'Փոխանցման ենթակա չվճարված մնացորդ չկա։',
                ]);
            }

            foreach ($assignments as $assignment) {
                $this->salaryPayoutService->transfer(
                    Auth::user(),
                    $assignment,
                    [
                        'amount' => (float) $assignment->available_amount,
                        'reason' => 'Փոխանցվել է մարզչի աշխատավարձերի էջից։',
                    ],
                );
            }
        });
    }

    public function saveTrainerScheduleData(int $trainerId, array $data): void
    {
        DB::transaction(function () use ($trainerId, $data) {
            $scheduleIds = collect($data['schedule_names'])
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $lockedScheduleIds = $this->lockedScheduleIdsForTrainer($trainerId);
            $existingScheduleIds = TrainerSchedule::query()
                ->where('user_id', $trainerId)
                ->pluck('schedule_name_id')
                ->map(fn ($id) => (int) $id);

            $removedLockedScheduleIds = $existingScheduleIds
                ->diff($scheduleIds)
                ->intersect($lockedScheduleIds);

            if ($removedLockedScheduleIds->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'schedule_names' => 'Այս ժամային գրաֆիկը կապված է վաճառված/ակտիվ աբոնեմենտի հետ և հնարավոր չէ հեռացնել։',
                ]);
            }

            $this->trainerScheduleRepository->deleteMissingSchedules(
                $trainerId,
                $scheduleIds
            );

            foreach ($scheduleIds as $scheduleId) {
                $this->trainerScheduleRepository->firstOrCreate(
                    $trainerId,
                    $scheduleId
                );
            }

            $keptDurationIds = [];

            foreach ($data['session_durations'] ?? [] as $durationData) {
                $scheduleNameId = (int) ($durationData['schedule_name_id'] ?? 0);

                if (! $scheduleNameId) {
                    continue;
                }

                $trainerSchedule = $this->trainerScheduleRepository
                    ->findByTrainerAndScheduleName(
                        $trainerId,
                        $scheduleNameId
                    );

                if (! empty($durationData['id'])) {
                    $duration = TrainerSessionDuration::query()
                        ->with('trainerSchedule:id,schedule_name_id')
                        ->whereKey((int) $durationData['id'])
                        ->whereHas('trainerSchedule', function ($query) use ($trainerId) {
                            $query->where('user_id', $trainerId);
                        })
                        ->firstOrFail();

                    $keptDurationIds[] = $duration->id;

                    $existingScheduleNameId = (int) $duration->trainerSchedule->schedule_name_id;

                    if ($lockedScheduleIds->contains($existingScheduleNameId)) {
                        continue;
                    }
                }

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

                $keptDurationIds[] = $duration->id;
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

            $this->deleteMissingUnlockedDurations(
                $trainerId,
                $keptDurationIds,
                $lockedScheduleIds
            );
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
        $trainerSchedules = $this->trainerScheduleRepository->getTrainerSessionDuration($trainerId);
        $lockedScheduleIds = $this->lockedScheduleIdsForTrainer((int) $trainerId);

        return $trainerSchedules->map(function ($trainerSchedule) use ($lockedScheduleIds) {
            $isLocked = $lockedScheduleIds->contains((int) $trainerSchedule->schedule_name_id);

            $trainerSchedule->setAttribute('is_locked', $isLocked);
            $trainerSchedule->setAttribute(
                'lock_reason',
                $isLocked
                    ? 'Այս գրաֆիկը կապված է վաճառված/ակտիվ աբոնեմենտի հետ։'
                    : null
            );

            $trainerSchedule->sessionDurations->each(function ($duration) use ($isLocked) {
                $duration->setAttribute('is_locked', $isLocked);
                $duration->setAttribute(
                    'lock_reason',
                    $isLocked
                        ? 'Այս պարապունքի տեսակը կապված է վաճառված/ակտիվ աբոնեմենտի հետ։'
                        : null
                );
            });

            return $trainerSchedule;
        });
    }

    protected function ensureTrainerIsVisible(int $trainerId): void
    {
        $user = Auth::user();

        User::query()
            ->whereKey($trainerId)
            ->whereHas('roles', function ($query) {
                $query->where('roles.id', 7);
            })
            ->when(! $user->hasRole('owner'), function ($query) use ($user) {
                $query->where('gym_id', $user->gym_id);
            })
            ->firstOrFail();
    }

    protected function lockedScheduleIdsForTrainer(int $trainerId)
    {
        return PersonMembership::query()
            ->where('trainer_id', $trainerId)
            ->whereIn('status', ['waiting', 'active', 'frozen'])
            ->whereHas('membershipPlan.schedules')
            ->with('membershipPlan.schedules:id')
            ->get()
            ->flatMap(function (PersonMembership $personMembership) {
                return $personMembership->membershipPlan?->schedules?->pluck('id') ?? collect();
            })
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    protected function deleteMissingUnlockedDurations(
        int $trainerId,
        array $keptDurationIds,
        $lockedScheduleIds
    ): void {
        TrainerSessionDuration::query()
            ->whereHas('trainerSchedule', function ($query) use ($trainerId, $lockedScheduleIds) {
                $query->where('user_id', $trainerId)
                    ->when($lockedScheduleIds->isNotEmpty(), function ($q) use ($lockedScheduleIds) {
                        $q->whereNotIn('schedule_name_id', $lockedScheduleIds);
                    });
            })
            ->when(! empty($keptDurationIds), function ($query) use ($keptDurationIds) {
                $query->whereNotIn('id', $keptDurationIds);
            })
            ->delete();
    }
}
