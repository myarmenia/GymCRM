<?php

namespace App\Services\Reports;

use App\Interfaces\Reports\TrainerCommissionsReportRepositoryInterface;
use App\Models\TrainerCommission;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TrainerCommissionsReportService
{
    public function __construct(
        protected TrainerCommissionsReportRepositoryInterface $trainerCommissionsReportRepository,
    ) {}

    public function report(User $user, array $filters = []): array
    {
        $filters = $this->reportFilters($filters);
        $summaryCommissions = $this->trainerCommissionsReportRepository->commissionsForSummary($user, $filters);
        $paginatedCommissions = $this->trainerCommissionsReportRepository->paginatedCommissions($user, $filters);

        $paginatedCommissions->getCollection()->transform(fn (TrainerCommission $commission) => $this->mapCommission($commission));

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
        $commissions = $this->trainerCommissionsReportRepository
            ->commissionsForExport($user, $filters)
            ->map(fn (TrainerCommission $commission) => $this->mapCommission($commission));

        return [
            'rows' => $commissions,
            'columns' => $this->exportColumns(),
            'filters' => $filters,
            'filename' => 'trainer-commissions-report-'.now()->format('Y-m-d-H-i-s').'.xls',
            'title' => __('backend_messages.trainer_commissions_report'),
            'summary' => $this->exportSummary($this->summary($commissions)),
        ];
    }

    protected function reportFilters(array $filters): array
    {
        return array_merge(
            $this->resolvePeriod($filters),
            collect($filters)
                ->only(['trainer_id', 'membership_plan_id', 'status'])
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
            'membershipPlans' => $this->trainerCommissionsReportRepository->membershipPlanOptions($user)
                ->map(fn ($membershipPlan) => [
                    'value' => $membershipPlan->id,
                    'label' => $this->membershipPlanName($membershipPlan) ?? ('#'.$membershipPlan->id),
                ])
                ->values(),
            'trainers' => $this->trainerCommissionsReportRepository->trainerOptions($user)
                ->map(fn (User $trainer) => [
                    'value' => $trainer->id,
                    'label' => $this->userName($trainer),
                ])
                ->values(),
            'statuses' => [
                ['value' => 'pending', 'label' => __('backend_messages.pending')],
                ['value' => 'partial', 'label' => __('backend_messages.partially_paid')],
                ['value' => 'paid', 'label' => __('backend_messages.paid')],
                ['value' => 'transferred', 'label' => __('backend_messages.transferred')],
                ['value' => 'cancelled', 'label' => __('backend_messages.generation_stopped')],
            ],
        ];
    }

    protected function exportColumns(): array
    {
        return [
            ['key' => 'trainer', 'title' => __('backend_messages.trainer')],
            ['key' => 'membership_plan', 'title' => __('backend_messages.membership')],
            ['key' => 'customer', 'title' => __('backend_messages.client')],
            ['key' => 'salary_type', 'title' => __('backend_messages.commission_type')],
            ['key' => 'salary_value', 'title' => __('backend_messages.commission_value')],
            ['key' => 'initial_commission_amount', 'title' => __('backend_messages.initial_commission')],
            ['key' => 'salary_amount', 'title' => __('backend_messages.total_reassigned')],
            ['key' => 'cancelled_unearned_amount', 'title' => __('backend_messages.unearned_cancelled')],
            ['key' => 'net_paid_amount', 'title' => __('backend_messages.net_paid')],
            ['key' => 'outstanding_amount', 'title' => __('backend_messages.unpaid_balance')],
            ['key' => 'refunded_amount', 'title' => __('backend_messages.refunded')],
            ['key' => 'transferred_in_amount', 'title' => __('backend_messages.transferred_entry')],
            ['key' => 'transferred_out_amount', 'title' => __('backend_messages.transferred_exit')],
            ['key' => 'status', 'title' => __('backend_messages.status')],
            ['key' => 'is_kept', 'title' => __('backend_messages.saved_status')],
            ['key' => 'generation_stopped_reason', 'title' => __('backend_messages.reason_stopped')],
            ['key' => 'generation_stopped_at', 'title' => __('backend_messages.generation_stopped_at')],
            ['key' => 'created_at', 'title' => __('backend_messages.created')],
        ];
    }

    protected function summary(Collection $commissions): array
    {
        return [
            'commissions_count' => $commissions->count(),
            'total_commission_amount' => round($commissions->sum(
                fn ($commission) => $this->commissionMetrics($commission)['total_amount']
            ), 2),
            'paid_commission_amount' => round($commissions->sum(
                fn ($commission) => $this->commissionMetrics($commission)['net_paid_amount']
            ), 2),
            'pending_commission_amount' => round($commissions->sum(
                fn ($commission) => $this->commissionMetrics($commission)['outstanding_amount']
            ), 2),
            'refunded_commission_amount' => round($commissions->sum(
                fn ($commission) => $this->commissionMetrics($commission)['refunded_amount']
            ), 2),
            'cancelled_unearned_commission_amount' => round($commissions->sum(
                fn ($commission) => $this->commissionMetrics($commission)['cancelled_unearned_amount']
            ), 2),
            'transferred_in_amount' => round($commissions->sum(
                fn ($commission) => $this->commissionMetrics($commission)['transferred_in_amount']
            ), 2),
            'transferred_out_amount' => round($commissions->sum(
                fn ($commission) => $this->commissionMetrics($commission)['transferred_out_amount']
            ), 2),
            'kept_commissions_count' => $commissions->filter(fn ($commission) => (bool) (is_array($commission) ? ($commission['is_kept'] ?? false) : ($commission->is_kept ?? false)))->count(),
        ];
    }

    protected function exportSummary(array $summary): array
    {
        return [
            'title' => __('backend_messages.summary'),
            'rows' => [
                ['label' => __('backend_messages.record_count'), 'value' => $summary['commissions_count']],
                ['label' => __('backend_messages.total_commission_amount'), 'value' => $summary['total_commission_amount']],
                ['label' => __('backend_messages.paid_commission'), 'value' => $summary['paid_commission_amount']],
                ['label' => __('backend_messages.pending_commission'), 'value' => $summary['pending_commission_amount']],
                ['label' => __('backend_messages.refunded_commission'), 'value' => $summary['refunded_commission_amount']],
                ['label' => __('backend_messages.unearned_cancelled'), 'value' => $summary['cancelled_unearned_commission_amount']],
                ['label' => __('backend_messages.transferred_entry'), 'value' => $summary['transferred_in_amount']],
                ['label' => __('backend_messages.transferred_exit'), 'value' => $summary['transferred_out_amount']],
                ['label' => __('backend_messages.saved_commissions'), 'value' => $summary['kept_commissions_count']],
            ],
        ];
    }

    protected function mapCommission(TrainerCommission $commission): array
    {
        $metrics = $this->commissionMetrics($commission);

        return [
            'id' => $commission->id,
            'trainer' => $this->userName($commission->trainer),
            'membership_plan' => $this->membershipPlanName($commission->personMembership?->membershipPlan)
                ?? $this->membershipPlanName($commission->membershipSale?->membershipPlan)
                ?? '-',
            'customer' => $this->customerName($commission),
            'salary_type' => $commission->salary_type,
            'salary_value' => (float) $commission->salary_value,
            'initial_commission_amount' => (float) ($commission->initial_salary_amount ?? 0),
            'salary_amount' => $metrics['total_amount'],
            'cancelled_unearned_amount' => $metrics['cancelled_unearned_amount'],
            'net_paid_amount' => $metrics['net_paid_amount'],
            'outstanding_amount' => $metrics['outstanding_amount'],
            'refunded_amount' => $metrics['refunded_amount'],
            'transferred_in_amount' => $metrics['transferred_in_amount'],
            'transferred_out_amount' => $metrics['transferred_out_amount'],
            'status' => $metrics['status'],
            'is_kept' => (bool) $commission->is_kept,
            'generation_stopped_reason' => $commission->generation_stopped_reason === 'membership_cancelled'
                ? __('backend_messages.membership_cancellation')
                : ($commission->generation_stopped_reason ?? '-'),
            'generation_stopped_at' => $commission->generation_stopped_at?->toDateTimeString(),
            'created_at' => $commission->created_at?->toDateTimeString(),
        ];
    }

    protected function commissionMetrics(TrainerCommission|array $commission): array
    {
        if (is_array($commission)) {
            return [
                'total_amount' => (float) ($commission['salary_amount'] ?? 0),
                'net_paid_amount' => (float) ($commission['net_paid_amount'] ?? 0),
                'outstanding_amount' => (float) ($commission['outstanding_amount'] ?? 0),
                'refunded_amount' => (float) ($commission['refunded_amount'] ?? 0),
                'cancelled_unearned_amount' => (float) ($commission['cancelled_unearned_amount'] ?? 0),
                'transferred_in_amount' => (float) ($commission['transferred_in_amount'] ?? 0),
                'transferred_out_amount' => (float) ($commission['transferred_out_amount'] ?? 0),
                'status' => $commission['status'] ?? 'pending',
            ];
        }

        $outstanding = max(round((float) $commission->salary_amount, 2), 0);
        $payout = max(round((float) ($commission->payout_amount ?? 0), 2), 0);
        $refunded = max(round((float) ($commission->refunded_amount ?? 0), 2), 0);
        $cancelledUnearned = max(round((float) ($commission->cancelled_unearned_amount ?? 0), 2), 0);
        $netPaid = max(round($payout - $refunded, 2), 0);
        $transferredIn = max(round((float) ($commission->transferred_in_amount ?? 0), 2), 0);
        $transferredOut = max(round((float) ($commission->transferred_out_amount ?? 0), 2), 0);
        $total = round($outstanding + $netPaid, 2);

        $status = match (true) {
            $commission->generation_stopped_at !== null => 'cancelled',
            $outstanding <= 0 && $netPaid > 0 => 'paid',
            $outstanding > 0 && $netPaid > 0 => 'partial',
            $outstanding <= 0 && $transferredOut > 0 => 'transferred',
            default => 'pending',
        };

        return [
            'total_amount' => $total,
            'net_paid_amount' => $netPaid,
            'outstanding_amount' => $outstanding,
            'refunded_amount' => $refunded,
            'cancelled_unearned_amount' => $cancelledUnearned,
            'transferred_in_amount' => $transferredIn,
            'transferred_out_amount' => $transferredOut,
            'status' => $status,
        ];
    }

    protected function customerName(TrainerCommission $commission): string
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
