<?php

namespace App\Services\MeasurementUnit;

use App\Interfaces\MeasurementUnit\MeasurementUnitInterface;


class MeasurementUnitService
{

    public function __construct(protected MeasurementUnitInterface $measurementUnitRepository) {}

    public function getAll($locale, $perPage)
    {
        return $this->measurementUnitRepository->wherePaginate([], [], $perPage);
    }
}
