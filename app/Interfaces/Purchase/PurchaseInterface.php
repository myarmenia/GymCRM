<?php

namespace App\Interfaces\Purchase;

use App\Interfaces\BaseInterface;

interface PurchaseInterface extends BaseInterface
{
    public function paginateHistory(
        int $gymId,
        string $locale,
        ?string $search = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $paymentMethodId = null,
        ?int $personId = null,
        ?int $warehouseId = null,
        int $perPage = 10
    );

    // public function create(array $data);
}
