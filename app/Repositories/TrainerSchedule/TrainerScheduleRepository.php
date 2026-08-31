<?php

namespace App\Repositories\TrainerSchedule;

use App\Interfaces\TrainerSchedule\TrainerScheduleInterface;
use App\Models\TrainerSchedule;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class TrainerScheduleRepository extends BaseRepository implements TrainerScheduleInterface
{
    public function __construct(TrainerSchedule $model, ?string $connection = null)
    {
        parent::__construct($model, $connection);
    }

    public function create($data): Model
    {
        return $this->model->create($data);
    }

    public function getTrainerSessionDuration(int $trainerId): Collection
    {
        return $this->query()
            ->with([
                'sessionDurations.slots',
            ])
            ->where('user_id', $trainerId)
            ->get();
    }

    public function deleteMissingSchedules(int $trainerId, Collection $scheduleIds): void
    {
        $this->query()
            ->where('user_id', $trainerId)
            ->whereNotIn('schedule_name_id', $scheduleIds)
            ->get()
            ->each(function (TrainerSchedule $trainerSchedule): void {
                $trainerSchedule->sessionDurations()->delete();
                $trainerSchedule->delete();
            });
    }

    public function firstOrCreate(int $trainerId, int $scheduleNameId): ?Model
    {
        $trainerSchedule = $this->query()->withTrashed()->firstOrNew([
            'user_id' => $trainerId,
            'schedule_name_id' => $scheduleNameId,
        ]);

        if (! $trainerSchedule->exists) {
            $trainerSchedule->save();
        } elseif ($trainerSchedule->trashed()) {
            $trainerSchedule->restore();
        }

        return $trainerSchedule;
    }

    public function findByTrainerAndScheduleName(int $trainerId, int $scheduleNameId): ?Model
    {
        return $this->query()
            ->where('user_id', $trainerId)
            ->where('schedule_name_id', $scheduleNameId)
            ->firstOrFail();
    }

    public function getByTrainerId(int $trainerId)
    {
        return $this->query()
            ->with([
                'sessionDurations.slots',
            ])
            ->where('user_id', $trainerId)
            ->get();
    }
}
