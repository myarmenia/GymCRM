<?php

namespace App\Interfaces\TrainerSessionDurationSlot;

use App\Models\TrainerSessionDurationSlot;

interface TrainerSessionDurationSlotInterface
{
    public function updateOrCreate(array $attributes, array $values): TrainerSessionDurationSlot;

    public function deleteMissingByDuration(int $durationId, array $slotIds): void;
}