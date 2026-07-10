<?php

namespace App\Interfaces\Reports;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TrainerCommissionsReportRepositoryInterface
{
    public function paginatedCommissions(User $user, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    public function commissionsForSummary(User $user, array $filters = []): Collection;

    public function commissionsForExport(User $user, array $filters = []): Collection;

    public function membershipPlanOptions(User $user): Collection;

    public function trainerOptions(User $user): Collection;
}
