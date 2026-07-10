<?php

namespace App\Interfaces\Reports;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TrainerMonthlySalariesReportRepositoryInterface
{
    public function paginatedSalaries(User $user, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    public function salariesForSummary(User $user, array $filters = []): Collection;

    public function salariesForExport(User $user, array $filters = []): Collection;

    public function trainerOptions(User $user): Collection;
}
