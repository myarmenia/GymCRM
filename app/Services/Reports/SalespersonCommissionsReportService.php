<?php

namespace App\Services\Reports;

use App\Interfaces\Reports\SalespersonCommissionsReportRepositoryInterface;
use App\Models\SalespersonCommission;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SalespersonCommissionsReportService
{
    public function __construct(
        protected SalespersonCommissionsReportRepositoryInterface $salespersonCommissionsReportRepository,
    ) {}

    public function report(User $user, array $filters = []): array
    {
        $filters = $this->reportFilters($filters);
        $summaryCommissions = $this->salespersonCommissionsReportRepository->commissionsForSummary($user, $filters);
        $paginatedCommissions = $this->salespersonCommissionsReportRepository->paginatedCommissions($user, $filters);

        $paginatedCommissions->getCollection()->transform(fn (SalespersonCommission $commission) => $this->mapCommission($commission));

        return [
            'filters' => $filters,
            'summary' => $this->summary($summaryCommissions),
            'commissions' => $paginatedCommissions,
            'filterOptions' => $this->filterOptions($user),
        ];
    }

    public function exportData(User $user, array $filters = []): array
    {
        $filters = $this->reportFilters($filters);
        $commissions = $this->salespersonCommissionsReportRepository
            ->commissionsForExport($user, $filters)
            ->map(fn (SalespersonCommission $commission) => $this->mapCommission($commission));

        return [
            'rows' => $commissions,
            'columns' => $this->exportColumns(),
            'filters' => $filters,
            'filename' => 'salesperson-commissions-report-' . now()->format('Y-m-d-H-i-s') . '.xls',
            'title' => 'Վաճառողների միջնորդավճարների հաշվետվություն',
            'summary' => $this->exportSummary($this->summary($commissions)),
        ];
    }

    protected function reportFilters(array $filters): array
    {
        return array_merge(
            $this->resolvePeriod($filters),
            collect($filters)
                ->only(['salesperson_id', 'membership_plan_id', 'status'])
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->all()
        );
    }

    protected function resolvePeriod(array $filters): array
    {
        $period = in_array($filters['period'] ?? null, ['monthly', 'quarterly', 'yearly'], true)
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

    protected function filterOptions(User $user): array
    {
        return [
            'membershipPlans' => $this->salespersonCommissionsReportRepository->membershipPlanOptions($user)
                ->map(fn ($membershipPlan) => [
                    'value' => $membershipPlan->id,
                    'label' => $this->membershipPlanName($membershipPlan) ?? ('#' . $membershipPlan->id),
                ])
                ->values(),
            'salespeople' => $this->salespersonCommissionsReportRepository->salespersonOptions($user)
                ->map(fn (User $salesperson) => [
                    'value' => $salesperson->id,
                    'label' => $this->userName($salesperson),
                ])
                ->values(),
            'statuses' => [
                ['value' => 'pending', 'label' => 'Սպասման մեջ'],
                ['value' => 'paid', 'label' => 'Վճարված'],
                ['value' => 'cancelled', 'label' => 'Չեղարկված'],
            ],
        ];
    }

    protected function exportColumns(): array
    {
        return [
            ['key' => 'salesperson', 'title' => 'Վաճառող'],
            ['key' => 'membership_sale', 'title' => 'Աբոնեմենտի վաճառք'],
            ['key' => 'customer', 'title' => 'Հաճախորդ'],
            ['key' => 'salary_type', 'title' => 'Միջնորդավճարի տեսակ'],
            ['key' => 'salary_value', 'title' => 'Միջնորդավճարի արժեք'],
            ['key' => 'salary_amount', 'title' => 'Միջնորդավճարի գումար'],
            ['key' => 'status', 'title' => 'Կարգավիճակ'],
            ['key' => 'created_at', 'title' => 'Ստեղծվել է'],
        ];
    }

    protected function summary(Collection $commissions): array
    {
        return [
            'commissions_count' => $commissions->count(),
            'total_sale_amount' => round($commissions->sum(fn ($commission) => (float) (is_array($commission) ? ($commission['sale_amount'] ?? 0) : ($commission->sale_amount ?? 0))), 2),
            'total_commission_amount' => round($commissions->sum(fn ($commission) => (float) (is_array($commission) ? ($commission['salary_amount'] ?? 0) : ($commission->salary_amount ?? 0))), 2),
            'paid_commission_amount' => round($commissions->sum(fn ($commission) => $this->statusAmount($commission, 'paid')), 2),
            'pending_commission_amount' => round($commissions->sum(fn ($commission) => $this->statusAmount($commission, 'pending')), 2),
        ];
    }

    protected function exportSummary(array $summary): array
    {
        return [
            'title' => 'Ամփոփում',
            'rows' => [
                ['label' => 'Գրանցումների քանակ', 'value' => $summary['commissions_count']],
                ['label' => 'Վաճառքների գումար', 'value' => $summary['total_sale_amount']],
                ['label' => 'Միջնորդավճարի ընդհանուր գումար', 'value' => $summary['total_commission_amount']],
                ['label' => 'Վճարված միջնորդավճար', 'value' => $summary['paid_commission_amount']],
                ['label' => 'Սպասող միջնորդավճար', 'value' => $summary['pending_commission_amount']],
            ],
        ];
    }

    protected function mapCommission(SalespersonCommission $commission): array
    {
        return [
            'id' => $commission->id,
            'salesperson' => $this->userName($commission->salesperson),
            'membership_sale' => $this->membershipSaleName($commission),
            'customer' => $this->customerName($commission),
            'salary_type' => $commission->salary_type,
            'salary_value' => (float) $commission->salary_value,
            'salary_amount' => (float) $commission->salary_amount,
            'sale_amount' => (float) $commission->sale_amount,
            'status' => $commission->status,
            'created_at' => $commission->created_at?->toDateTimeString(),
        ];
    }

    protected function statusAmount(SalespersonCommission|array $commission, string $status): float
    {
        $commissionStatus = is_array($commission) ? ($commission['status'] ?? null) : $commission->status;

        if ($commissionStatus !== $status) {
            return 0;
        }

        return (float) (is_array($commission) ? ($commission['salary_amount'] ?? 0) : ($commission->salary_amount ?? 0));
    }

    protected function membershipSaleName(SalespersonCommission $commission): string
    {
        $planName = $this->membershipPlanName($commission->membershipPlan)
            ?? $this->membershipPlanName($commission->personMembership?->membershipPlan)
            ?? $this->membershipPlanName($commission->membershipSale?->membershipPlan);

        return '#' . $commission->membership_sale_id . ' - ' . ($planName ?? '-');
    }

    protected function customerName(SalespersonCommission $commission): string
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
