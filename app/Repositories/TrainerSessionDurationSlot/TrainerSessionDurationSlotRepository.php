<?php

namespace App\Repositories\TrainerSessionDurationSlot;

use App\Interfaces\TrainerSessionDurationSlot\TrainerSessionDurationSlotInterface;
use App\Models\TrainerSessionDurationSlot;
use App\Repositories\BaseRepository;

class TrainerSessionDurationSlotRepository extends BaseRepository implements TrainerSessionDurationSlotInterface
{
    public function __construct(TrainerSessionDurationSlot $model)
    {
        parent::__construct($model);
    }
    public function updateOrCreate(array $attributes, array $values): TrainerSessionDurationSlot
    {
        return $this->model->updateOrCreate(
            $attributes,
            $values
        );
    }

    public function deleteMissingByDuration(int $durationId, array $slotIds): void
    {
        $this->query()
            ->where('session_duration_id', $durationId)
            ->whereNotIn('id', $slotIds)
            ->delete();
    }
}
