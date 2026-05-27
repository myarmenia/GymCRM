<?php

namespace App\Repositories\Partners;

use App\Interfaces\Partners\PartnerInterface;
use App\Models\Partner;
use App\Repositories\BaseRepository;

class PartnerRepository extends BaseRepository implements PartnerInterface
{
    public function __construct(Partner $model)
    {
        parent::__construct($model);
    }


    public function getByGymWithPagination(int $gymId, int $perPage = 10)
    {
        return $this->model->where('gym_id', $gymId)->paginate($perPage);
    }
}
