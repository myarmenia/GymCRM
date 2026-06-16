<?php

namespace App\Repositories\SalespersonCommissions;

use App\Interfaces\SalespersonCommissions\SalespersonCommissionInterface;
use App\Models\SalespersonCommission;
use App\Repositories\BaseRepository;

class SalespersonCommissionRepository extends BaseRepository implements SalespersonCommissionInterface
{
    public function __construct(SalespersonCommission $model)
    {
        parent::__construct($model);
    }
}
