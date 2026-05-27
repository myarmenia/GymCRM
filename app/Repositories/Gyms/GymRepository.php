<?php

namespace App\Repositories\Gyms;

use App\Interfaces\Gyms\GymInterface;
use App\Models\Gym;
use App\Repositories\BaseRepository;

class GymRepository extends BaseRepository implements GymInterface
{

    public function __construct(Gym $model)
    {
        parent::__construct($model);
    }


}
