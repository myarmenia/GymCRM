<?php

namespace App\Interfaces\OwnerSales;

use App\Interfaces\BaseInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface OwnerSaleInterface extends BaseInterface
{
    public function paginateForOwner(array $filters = [], int $perPage = 10): LengthAwarePaginator;

    public function summaryForOwner(array $filters = []): array;

    public function gymHasSales(int $gymId): bool;

    public function gymHasActivePeriod(int $gymId): bool;

    public function endingSoon(): Collection;
}
