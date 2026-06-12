<?php

namespace App\Repositories\TrainerCommissions;

use App\Interfaces\TrainerCommissions\TrainerCommissionInterface;
use App\Models\TrainerCommission;
use App\Repositories\BaseRepository;

class TrainerCommissionRepository extends BaseRepository implements TrainerCommissionInterface
{
    public function __construct(TrainerCommission $model)
    {
        parent::__construct($model);
    }
}
