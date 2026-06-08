<?php

namespace App\Repositories\TrainerSessionDuration;

use App\Interfaces\TrainerSessionDuration\TrainerSessionDurationInterface;
use App\Models\TrainerSessionDuration;
use App\Repositories\BaseRepository;

class TrainerSessionDurationRepository extends BaseRepository implements TrainerSessionDurationInterface
{
    public function __construct(TrainerSessionDuration $model)
    {
        parent::__construct($model);
    }
    public function updateOrCreate(array $attributes, array $values): TrainerSessionDuration
    {
        return $this->model->updateOrCreate(
            $attributes,
            $values
        );
    }

    public function deleteMissingByTrainer(int $trainerId, array $durationIds): void
    {
        $this->query()
            ->whereHas('trainerSchedule', function ($q) use ($trainerId) {
                $q->where('user_id', $trainerId);
            })
            ->whereNotIn('id', $durationIds)
            ->delete();
    }
}
