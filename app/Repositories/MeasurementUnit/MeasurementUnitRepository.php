<?php

namespace App\Repositories\MeasurementUnit;

use App\Interfaces\MeasurementUnit\MeasurementUnitInterface;
use App\Models\MeasurementUnit;
use App\Repositories\BaseRepository;
use Illuminate\Pagination\LengthAwarePaginator;


class MeasurementUnitRepository extends BaseRepository implements MeasurementUnitInterface
{

    public function __construct(MeasurementUnit $model)
    {
        parent::__construct($model);
    }

    public function wherePaginate(array $conditions, array $with = [], int $perPage = 10): LengthAwarePaginator
    {
        return $this->query()
            ->with($with)
            ->where($conditions)
            ->orderBy('id', 'asc')
            ->paginate($perPage);
    }
}
