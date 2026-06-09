<?php

namespace App\Interfaces\TrainerSessionDuration;

use App\Models\TrainerSessionDuration;

interface TrainerSessionDurationInterface
{
    public function updateOrCreate(array $attributes, array $values): TrainerSessionDuration;

    public function deleteMissingByTrainer(int $trainerId, array $durationIds): void;
}