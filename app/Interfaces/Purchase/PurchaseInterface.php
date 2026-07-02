<?php

namespace App\Interfaces\Purchase;

use App\Interfaces\BaseInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;



interface PurchaseInterface extends BaseInterface
{
    public function paginateHistory(
        int $gymId,
        string $locale,
        ?string $search = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $paymentMethod = null,
        ?int $personId = null,
        ?int $warehouseId = null,
        int $perPage = 10
    );

    //public function create(array $data);
}
