<?php

namespace App\Interfaces\Reports;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface MembershipSalesReportRepositoryInterface
{
    public function paginatedSales(User $user, string $startDate, string $endDate, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    public function salesForSummary(User $user, string $startDate, string $endDate, array $filters = []): Collection;
}
