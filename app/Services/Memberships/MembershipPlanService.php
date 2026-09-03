<?php

namespace App\Services\Memberships;

use App\Interfaces\Memberships\MembershipPlanInterface;
use App\Models\MembershipPlan;
use App\Repositories\Memberships\MembershipCategoryRepository;
use App\Repositories\ScheduleName\ScheduleNameRepository;
use Illuminate\Support\Facades\DB;

class MembershipPlanService
{
    public function __construct(
        protected MembershipPlanInterface $membershipPlanRepository,
        protected ScheduleNameRepository $scheduleNameRepository,
        protected MembershipCategoryRepository $membershipCategoryRepository,
        protected MembershipSalaryCalculator $salaryCalculator,
    ) {}

    public function getAllPaginated()
    {
        return $this->membershipPlanRepository->paginate(
            10,
            ['gym_id' => auth()->user()->gym_id],
            ['translations', 'MembershipCategory'],
        );
    }

    /** @return array<string, mixed> */
    public function getCreateData(): array
    {
        return [
            'membershipCategories' => $this->membershipPlanRepository->getCreateData(),
            'scheduleNames' => $this->scheduleNameRepository->getAllWithTrainerByGym(),
        ];
    }

    public function store(array $data): MembershipPlan
    {
        $data = $this->normalizeSalaryData($data);

        return DB::transaction(function () use ($data): MembershipPlan {
            /** @var MembershipPlan $membershipPlan */
            $membershipPlan = $this->membershipPlanRepository->store($data);

            $this->syncTranslations($membershipPlan, $data['translations']);
            $this->syncSchedule($membershipPlan, $data['schedule_name_id'] ?? null);
            $this->syncTrainers($membershipPlan, $data['trainers'] ?? []);

            return $membershipPlan->fresh(['translations', 'schedules', 'trainers']);
        });
    }

    /** @return array<string, mixed> */
    public function edit(string $locale, int $id): array
    {
        return [
            'membershipPlan' => $this->membershipPlanRepository->findForEdit($id, $locale),
            'membershipCategories' => $this->membershipCategoryRepository->getAllForSelectByGymId(),
            'scheduleNames' => $this->scheduleNameRepository->getAllWithTrainerByGym(),
        ];
    }

    public function update(int $id, array $data): void
    {
        DB::transaction(function () use ($id, $data): void {
            /** @var MembershipPlan $membershipPlan */
            $membershipPlan = $this->membershipPlanRepository->findOrFail($id);
            $data = $this->normalizeSalaryData(
                $data,
                $membershipPlan->is_locked ? $membershipPlan->price : null,
            );
            $startingVersion = (int) $membershipPlan->version;
            $aggregateChanged = false;

            if (! $membershipPlan->is_locked) {
                $rootData = $this->sharedRootData($data);
                $membershipPlan->fill($rootData);
                $aggregateChanged = $membershipPlan->isDirty(array_keys($rootData));
                $aggregateChanged = $this->syncTranslations(
                    $membershipPlan,
                    $data['translations'],
                ) || $aggregateChanged;
                $aggregateChanged = $this->syncSchedule(
                    $membershipPlan,
                    $data['schedule_name_id'] ?? null,
                ) || $aggregateChanged;
            }

            $aggregateChanged = $this->syncTrainers(
                $membershipPlan,
                $data['trainers'] ?? [],
            ) || $aggregateChanged;

            if ($aggregateChanged) {
                $membershipPlan->version = $startingVersion + 1;
                $membershipPlan->save();
            }
        });
    }

    /** @return array<string, mixed> */
    private function sharedRootData(array $data): array
    {
        return [
            'membership_category_id' => $data['membership_category_id'],
            'price' => $data['price'],
            'price_type' => 'percent',
            'price_value' => $data['price_value'] ?? 0,
            'duration_type' => $data['duration_type'],
            'duration_value' => $data['duration_value'] ?? null,
            'visits_limit' => $data['visits_limit'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'guest_limit' => $data['guest_limit'] ?? 0,
            'freeze_limit' => $data['freeze_limit'] ?? 0,
            'active' => $data['active'] ?? false,
        ];
    }

    private function syncTranslations(MembershipPlan $membershipPlan, array $translations): bool
    {
        $changed = false;

        foreach ($translations as $locale => $data) {
            $translation = $membershipPlan->translations()->firstOrNew(['locale' => $locale]);
            $translation->fill([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            if (! $translation->exists || $translation->isDirty()) {
                $translation->save();
                $changed = true;
            }
        }

        return $changed;
    }

    private function syncSchedule(MembershipPlan $membershipPlan, int|string|null $scheduleId): bool
    {
        $desiredIds = $scheduleId === null || $scheduleId === ''
            ? []
            : [(int) $scheduleId];
        $currentIds = $membershipPlan->schedules()
            ->pluck('schedule_names.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        if ($currentIds === $desiredIds) {
            return false;
        }

        $membershipPlan->schedules()->sync($desiredIds);

        return true;
    }

    /** @param list<array<string, mixed>> $trainers */
    private function syncTrainers(MembershipPlan $membershipPlan, array $trainers): bool
    {
        $changed = false;
        $existing = $membershipPlan->membershipPlanTrainers()
            ->get()
            ->keyBy(fn ($trainer): int => (int) $trainer->trainer_id);
        $desiredTrainerIds = [];

        foreach ($trainers as $trainerData) {
            if (empty($trainerData['trainer_id'])) {
                continue;
            }

            $trainerId = (int) $trainerData['trainer_id'];
            $desiredTrainerIds[] = $trainerId;
            $trainer = $existing->get($trainerId)
                ?? $membershipPlan->membershipPlanTrainers()->make(['trainer_id' => $trainerId]);
            $trainer->fill([
                'price_type' => 'percent',
                'price_value' => $trainerData['price_value'] ?? 0,
                'total_price' => $trainerData['total_price'] ?? 0,
            ]);

            if (! $trainer->exists || $trainer->isDirty()) {
                $trainer->save();
                $changed = true;
            }
        }

        $removed = $existing->except(array_values(array_unique($desiredTrainerIds)));
        if ($removed->isNotEmpty()) {
            $removed->each->delete();
            $changed = true;
        }

        return $changed;
    }

    /** @return array<string, mixed> */
    private function normalizeSalaryData(
        array $data,
        int|float|string|null $priceOverride = null,
    ): array {
        $price = (float) ($priceOverride ?? $data['price'] ?? 0);

        $data['price_type'] = 'percent';
        $data['price_value'] = $this->salaryCalculator->normalizePercentage(
            $data['price_value'] ?? 0,
        );
        $data['trainers'] = array_map(function (array $trainer) use ($price): array {
            $percentage = $this->salaryCalculator->normalizePercentage(
                $trainer['price_value'] ?? 0,
            );

            return [
                ...$trainer,
                'price_type' => 'percent',
                'price_value' => $percentage,
                'total_price' => $this->salaryCalculator->amount($price, $percentage),
            ];
        }, $data['trainers'] ?? []);

        return $data;
    }
}
