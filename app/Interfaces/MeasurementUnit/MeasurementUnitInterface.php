<?php

namespace App\Interfaces\MeasurementUnit;

use App\Interfaces\BaseInterface;
use Illuminate\Pagination\LengthAwarePaginator;


interface MeasurementUnitInterface extends BaseInterface
{
    public function wherePaginate(array $conditions, array $with = [], int $perPage = 10): LengthAwarePaginator;
}
