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
            'filename' => 'salesperson-commissions-report-'.now()->format('Y-m-d-H-i-s').'.xls',
            'title' => __('backend_messages.salesperson_commissions_report'),
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
        if (! $value) {
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
                    'label' => $this->membershipPlanName($membershipPlan) ?? ('#'.$membershipPlan->id),
                ])
                ->values(),
            'salespeople' => $this->salespersonCommissionsReportRepository->salespersonOptions($user)
                ->map(fn (User $salesperson) => [
                    'value' => $salesperson->id,
                    'label' => $this->userName($salesperson),
                ])
                ->values(),
            'statuses' => [
                ['value' => 'pending', 'label' => __('backend_messages.pending')],
                ['value' => 'partial', 'label' => __('backend_messages.partially_paid')],
                ['value' => 'paid', 'label' => __('backend_messages.paid')],
                ['value' => 'cancelled', 'label' => __('backend_messages.cancelled')],
            ],
        ];
    }

    protected function exportColumns(): array
    {
        return [
            ['key' => 'salesperson', 'title' => __('backend_messages.salesperson')],
            ['key' => 'membership_sale', 'title' => __('backend_messages.membership_sale')],
            ['key' => 'customer', 'title' => __('backend_messages.client')],
            ['key' => 'salary_type', 'title' => __('backend_messages.commission_type')],
            ['key' => 'salary_value', 'title' => __('backend_messages.commission_value')],
            ['key' => 'salary_amount', 'title' => __('backend_messages.commission_amount')],
            ['key' => 'net_paid_amount', 'title' => __('backend_messages.net_paid')],
            ['key' => 'outstanding_amount', 'title' => __('backend_messages.unpaid_balance')],
            ['key' => 'refunded_amount', 'title' => __('backend_messages.refunded')],
            ['key' => 'status', 'title' => __('backend_messages.status')],
            ['key' => 'created_at', 'title' => __('backend_messages.created')],
        ];
    }

    protected function summary(Collection $commissions): array
    {
        $metrics = $commissions->map(fn ($commission) => $this->commissionMetrics($commission));
        $active = $metrics->reject(fn (array $commission) => $commission['status'] === 'cancelled');

        return [
            'commissions_count' => $commissions->count(),
            'total_sale_amount' => round($active->sum('sale_amount'), 2),
            'total_commission_amount' => round($active->sum('salary_amount'), 2),
            'paid_commission_amount' => round($active->sum('net_paid_amount'), 2),
            'pending_commission_amount' => round($active->sum('outstanding_amount'), 2),
            'refunded_commission_amount' => round($active->sum('refunded_amount'), 2),
            'cancelled_commission_amount' => round($metrics->where('status', 'cancelled')->sum('salary_amount'), 2),
        ];
    }

    protected function exportSummary(array $summary): array
    {
        return [
            'title' => __('backend_messages.summary'),
            'rows' => [
                ['label' => __('backend_messages.record_count'), 'value' => $summary['commissions_count']],
                ['label' => __('backend_messages.sales_amount'), 'value' => $summary['total_sale_amount']],
                ['label' => __('backend_messages.total_commission_amount'), 'value' => $summary['total_commission_amount']],
                ['label' => __('backend_messages.paid_commission'), 'value' => $summary['paid_commission_amount']],
                ['label' => __('backend_messages.pending_commission'), 'value' => $summary['pending_commission_amount']],
                ['label' => __('backend_messages.refunded_commission'), 'value' => $summary['refunded_commission_amount']],
                ['label' => __('backend_messages.cancelled_commission'), 'value' => $summary['cancelled_commission_amount']],
            ],
        ];
    }

    protected function mapCommission(SalespersonCommission $commission): array
    {
        $metrics = $this->commissionMetrics($commission);

        return [
            'id' => $commission->id,
            'salesperson' => $this->userName($commission->salesperson),
            'membership_sale' => $this->membershipSaleName($commission),
            'customer' => $this->customerName($commission),
            'salary_type' => $commission->salary_type,
            'salary_value' => (float) $commission->salary_value,
            'salary_amount' => $metrics['salary_amount'],
            'sale_amount' => (float) $commission->sale_amount,
            'net_paid_amount' => $metrics['net_paid_amount'],
            'outstanding_amount' => $metrics['outstanding_amount'],
            'refunded_amount' => $metrics['refunded_amount'],
            'status' => $metrics['status'],
            'created_at' => $commission->created_at?->toDateTimeString(),
        ];
    }

    protected function commissionMetrics(SalespersonCommission|array $commission): array
    {
        if (is_array($commission)) {
            return [
                'sale_amount' => (float) ($commission['sale_amount'] ?? 0),
                'salary_amount' => (float) ($commission['salary_amount'] ?? 0),
                'net_paid_amount' => (float) ($commission['net_paid_amount'] ?? 0),
                'outstanding_amount' => (float) ($commission['outstanding_amount'] ?? 0),
                'refunded_amount' => (float) ($commission['refunded_amount'] ?? 0),
                'status' => $commission['status'] ?? 'pending',
            ];
        }

        $salaryAmount = max(round((float) $commission->salary_amount, 2), 0);
        $outstanding = max(round((float) ($commission->outstanding_amount ?? 0), 2), 0);
        $payout = max(round((float) ($commission->payout_amount ?? 0), 2), 0);
        $refunded = max(round((float) ($commission->refunded_amount ?? 0), 2), 0);
        $netPaid = max(round($payout - $refunded, 2), 0);

        $status = match (true) {
            $commission->status === 'cancelled' => 'cancelled',
            $outstanding > 0 && $netPaid > 0 => 'partial',
            $outstanding <= 0 && $netPaid > 0 => 'paid',
            default => 'pending',
        };

        return [
            'sale_amount' => round((float) $commission->sale_amount, 2),
            'salary_amount' => $salaryAmount,
            'net_paid_amount' => $netPaid,
            'outstanding_amount' => $outstanding,
            'refunded_amount' => $refunded,
            'status' => $status,
        ];
    }

    protected function membershipSaleName(SalespersonCommission $commission): string
    {
        $planName = $this->membershipPlanName($commission->membershipPlan)
            ?? $this->membershipPlanName($commission->personMembership?->membershipPlan)
            ?? $this->membershipPlanName($commission->membershipSale?->membershipPlan);

        return '#'.$commission->membership_sale_id.' - '.($planName ?? '-');
    }

    protected function customerName(SalespersonCommission $commission): string
    {
        $person = $commission->personMembership?->person ?? $commission->membershipSale?->person;

        return trim(($person?->name ?? '').' '.($person?->surname ?? '')) ?: '-';
    }

    protected function membershipPlanName($membershipPlan): ?string
    {
        if (! $membershipPlan) {
            return null;
        }

        return $membershipPlan->translations?->firstWhere('locale', app()->getLocale())?->name
            ?? $membershipPlan->name
            ?? null;
    }

    protected function userName(?User $user): string
    {
        return trim(($user?->name ?? '').' '.($user?->surname ?? '')) ?: ($user?->email ?? '-');
    }
}
