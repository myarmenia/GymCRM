<?php

namespace App\Repositories\MeasurementUnit;

use App\Interfaces\MeasurementUnit\MeasurementUnitInterface;
use App\Models\MeasurementUnit;
use App\Repositories\BaseRepository;

class MeasurementUnitRepository extends BaseRepository implements MeasurementUnitInterface
{

    public function __construct(MeasurementUnit $model)
    {
        parent::__construct($model);
    }

}
