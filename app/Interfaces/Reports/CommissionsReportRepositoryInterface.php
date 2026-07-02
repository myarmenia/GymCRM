<?php

namespace App\Interfaces\Reports;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CommissionsReportRepositoryInterface
{
    public function paginatedTrainerCommissions(User $user, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    public function paginatedSalespersonCommissions(User $user, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    public function membershipPlanOptions(User $user): Collection;

    public function trainerOptions(User $user): Collection;

    public function salespersonOptions(User $user): Collection;
}
