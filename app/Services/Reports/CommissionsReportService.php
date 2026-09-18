<?php

namespace App\Services\Reports;

use App\Interfaces\Reports\CommissionsReportRepositoryInterface;
use App\Models\SalespersonCommission;
use App\Models\TrainerCommission;
use App\Models\User;
use Carbon\Carbon;

class CommissionsReportService
{
    private const FILTER_KEYS = [
        'trainer_membership_plan_id',
        'trainer_status',
        'trainer_id',
        'salesperson_membership_plan_id',
        'salesperson_status',
        'salesperson_id',
    ];

    private const PERIODS = ['monthly', 'quarterly', 'yearly'];

    public function __construct(
        protected CommissionsReportRepositoryInterface $commissionsReportRepository,
    ) {}

    public function report(User $user, array $filters = []): array
    {
        $filters = $this->reportFilters($filters);

        $trainerCommissions = $this->commissionsReportRepository->paginatedTrainerCommissions($user, $filters);
        $salespersonCommissions = $this->commissionsReportRepository->paginatedSalespersonCommissions($user, $filters);

        $trainerCommissions->getCollection()->transform(fn (TrainerCommission $commission) => $this->mapTrainerCommission($commission));
        $salespersonCommissions->getCollection()->transform(fn (SalespersonCommission $commission) => $this->mapSalespersonCommission($commission));

        return [
            'trainerCommissions' => $trainerCommissions,
            'salespersonCommissions' => $salespersonCommissions,
            'filters' => $filters,
            'filterOptions' => [
                'membershipPlans' => $this->commissionsReportRepository->membershipPlanOptions($user)
                    ->map(fn ($membershipPlan) => [
                        'value' => $membershipPlan->id,
                        'label' => $this->membershipPlanName($membershipPlan) ?? ('#' . $membershipPlan->id),
                    ])
                    ->values(),
                'trainers' => $this->commissionsReportRepository->trainerOptions($user)
                    ->map(fn (User $trainer) => [
                        'value' => $trainer->id,
                        'label' => $this->userName($trainer),
                    ])
                    ->values(),
                'salespeople' => $this->commissionsReportRepository->salespersonOptions($user)
                    ->map(fn (User $salesperson) => [
                        'value' => $salesperson->id,
                        'label' => $this->userName($salesperson),
                    ])
                    ->values(),
                'trainerStatuses' => [
                    ['value' => 'pending', 'label' => __('backend_messages.pending')],
                    ['value' => 'paid', 'label' => __('backend_messages.paid')],
                ],
                'salespersonStatuses' => [
                    ['value' => 'pending', 'label' => __('backend_messages.pending')],
                    ['value' => 'paid', 'label' => __('backend_messages.paid')],
                    ['value' => 'cancelled', 'label' => __('backend_messages.cancelled')],
                ],
            ],
        ];
    }

    public function exportData(User $user, array $filters = []): array
    {
        $resolvedFilters = $this->reportFilters($filters);
        $tab = ($filters['tab'] ?? null) === 'salesperson' ? 'salesperson' : 'trainer';

        if ($tab === 'salesperson') {
            $rows = $this->commissionsReportRepository
                ->salespersonCommissionsForExport($user, $resolvedFilters)
                ->map(fn (SalespersonCommission $commission) => $this->mapSalespersonCommission($commission));

            return [
                'rows' => $rows,
                'columns' => $this->salespersonExportColumns(),
                'filters' => array_merge($resolvedFilters, ['tab' => $tab]),
                'filename' => 'salesperson-commissions-report-' . now()->format('Y-m-d-H-i-s') . '.xls',
                'title' => __('backend_messages.salesperson_commissions_report'),
            ];
        }

        $rows = $this->commissionsReportRepository
            ->trainerCommissionsForExport($user, $resolvedFilters)
            ->map(fn (TrainerCommission $commission) => $this->mapTrainerCommission($commission));

        return [
            'rows' => $rows,
            'columns' => $this->trainerExportColumns(),
            'filters' => array_merge($resolvedFilters, ['tab' => $tab]),
            'filename' => 'trainer-commissions-report-' . now()->format('Y-m-d-H-i-s') . '.xls',
            'title' => __('backend_messages.trainer_commissions_report'),
        ];
    }

    protected function reportFilters(array $filters): array
    {
        $periodFilters = $this->resolvePeriod($filters);
        $reportFilters = collect($filters)
            ->only(self::FILTER_KEYS)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        return array_merge($periodFilters, $reportFilters);
    }

    protected function resolvePeriod(array $filters): array
    {
        $period = in_array($filters['period'] ?? null, self::PERIODS, true)
            ? $filters['period']
            : 'monthly';
        $now = now();

        [$defaultStart, $defaultEnd] = match ($period) {
            'quarterly' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
            'yearly' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };

        $startDate = $this->parseDate($filters['start_date'] ?? null, $defaultStart);
        $endDate = $this->parseDate($filters['end_date'] ?? null, $defaultEnd);

        if ($startDate->greaterThan($endDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        return [
            'period' => $period,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
        ];
    }

    protected function parseDate(?string $value, Carbon $fallback): Carbon
    {
        if (!$value) {
            return $fallback;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return $fallback;
        }
    }

    protected function trainerExportColumns(): array
    {
        return [
            ['key' => 'id', 'title' => 'ID'],
            ['key' => 'trainer', 'title' => __('backend_messages.trainer')],
            ['key' => 'customer', 'title' => __('backend_messages.client')],
            ['key' => 'membership_plan', 'title' => __('backend_messages.membership')],
            ['key' => 'salary_type', 'title' => __('backend_messages.type')],
            ['key' => 'salary_value', 'title' => __('backend_messages.price')],
            ['key' => 'salary_amount', 'title' => __('backend_messages.amount')],
            ['key' => 'status', 'title' => __('backend_messages.status')],
            ['key' => 'is_kept', 'title' => __('backend_messages.saved_status')],
            ['key' => 'paid_at', 'title' => __('backend_messages.paid_status')],
            ['key' => 'created_at', 'title' => __('backend_messages.created')],
        ];
    }

    protected function salespersonExportColumns(): array
    {
        return [
            ['key' => 'id', 'title' => 'ID'],
            ['key' => 'salesperson', 'title' => __('backend_messages.salesperson')],
            ['key' => 'customer', 'title' => __('backend_messages.client')],
            ['key' => 'membership_plan', 'title' => __('backend_messages.membership')],
            ['key' => 'salary_type', 'title' => __('backend_messages.type')],
            ['key' => 'salary_value', 'title' => __('backend_messages.price')],
            ['key' => 'sale_amount', 'title' => __('backend_messages.sale_amount')],
            ['key' => 'salary_amount', 'title' => __('backend_messages.commission')],
            ['key' => 'status', 'title' => __('backend_messages.status')],
            ['key' => 'paid_at', 'title' => __('backend_messages.paid_status')],
            ['key' => 'created_at', 'title' => __('backend_messages.created')],
        ];
    }

    protected function mapTrainerCommission(TrainerCommission $commission): array
    {
        return [
            'id' => $commission->id,
            'trainer' => $this->userName($commission->trainer),
            'customer' => $this->customerName($commission),
            'membership_plan' => $this->membershipPlanName($commission->personMembership?->membershipPlan)
                ?? $this->membershipPlanName($commission->membershipSale?->membershipPlan),
            'salary_type' => $commission->salary_type,
            'salary_value' => (float) $commission->salary_value,
            'salary_amount' => (float) $commission->salary_amount,
            'status' => $commission->status,
            'paid_at' => $commission->paid_at?->toDateTimeString(),
            'is_kept' => (bool) $commission->is_kept,
            'created_at' => $commission->created_at?->toDateTimeString(),
        ];
    }

    protected function mapSalespersonCommission(SalespersonCommission $commission): array
    {
        return [
            'id' => $commission->id,
            'salesperson' => $this->userName($commission->salesperson),
            'customer' => $this->customerName($commission),
            'membership_plan' => $this->membershipPlanName($commission->membershipPlan)
                ?? $this->membershipPlanName($commission->personMembership?->membershipPlan)
                ?? $this->membershipPlanName($commission->membershipSale?->membershipPlan),
            'salary_type' => $commission->salary_type,
            'salary_value' => (float) $commission->salary_value,
            'salary_amount' => (float) $commission->salary_amount,
            'sale_amount' => (float) $commission->sale_amount,
            'status' => $commission->status,
            'paid_at' => $commission->paid_at?->toDateTimeString(),
            'created_at' => $commission->created_at?->toDateTimeString(),
        ];
    }

    protected function customerName(TrainerCommission|SalespersonCommission $commission): string
    {
        $person = $commission->personMembership?->person ?? $commission->membershipSale?->person;

        return trim(($person?->name ?? '') . ' ' . ($person?->surname ?? '')) ?: '-';
    }

    protected function membershipPlanName($membershipPlan): ?string
    {
        if (!$membershipPlan) {
            return null;
        }

        return $membershipPlan->translations?->firstWhere('locale', app()->getLocale())?->name
            ?? $membershipPlan->name
            ?? null;
    }

    protected function userName(?User $user): string
    {
        return trim(($user?->name ?? '') . ' ' . ($user?->surname ?? '')) ?: ($user?->email ?? '-');
    }
}
