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
            'title' => 'Մարզիչների միջնորդավճարների հաշվետվություն',
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
                ['value' => 'pending', 'label' => 'Սպասման մեջ'],
                ['value' => 'partial', 'label' => 'Մասնակի վճարված'],
                ['value' => 'paid', 'label' => 'Վճարված'],
                ['value' => 'transferred', 'label' => 'Փոխանցված'],
            ],
        ];
    }

    protected function exportColumns(): array
    {
        return [
            ['key' => 'trainer', 'title' => 'Մարզիչ'],
            ['key' => 'membership_plan', 'title' => 'Աբոնեմենտ'],
            ['key' => 'customer', 'title' => 'Հաճախորդ'],
            ['key' => 'salary_type', 'title' => 'Միջնորդավճարի տեսակ'],
            ['key' => 'salary_value', 'title' => 'Միջնորդավճարի արժեք'],
            ['key' => 'salary_amount', 'title' => 'Ընդհանուր վերագրված'],
            ['key' => 'net_paid_amount', 'title' => 'Զուտ վճարված'],
            ['key' => 'outstanding_amount', 'title' => 'Չվճարված մնացորդ'],
            ['key' => 'refunded_amount', 'title' => 'Վերադարձված'],
            ['key' => 'transferred_in_amount', 'title' => 'Փոխանցված մուտք'],
            ['key' => 'transferred_out_amount', 'title' => 'Փոխանցված ելք'],
            ['key' => 'status', 'title' => 'Կարգավիճակ'],
            ['key' => 'is_kept', 'title' => 'Պահված է'],
            ['key' => 'created_at', 'title' => 'Ստեղծվել է'],
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
            'title' => 'Ամփոփում',
            'rows' => [
                ['label' => 'Գրանցումների քանակ', 'value' => $summary['commissions_count']],
                ['label' => 'Միջնորդավճարի ընդհանուր գումար', 'value' => $summary['total_commission_amount']],
                ['label' => 'Վճարված միջնորդավճար', 'value' => $summary['paid_commission_amount']],
                ['label' => 'Սպասող միջնորդավճար', 'value' => $summary['pending_commission_amount']],
                ['label' => 'Վերադարձված միջնորդավճար', 'value' => $summary['refunded_commission_amount']],
                ['label' => 'Փոխանցված մուտք', 'value' => $summary['transferred_in_amount']],
                ['label' => 'Փոխանցված ելք', 'value' => $summary['transferred_out_amount']],
                ['label' => 'Պահված միջնորդավճարներ', 'value' => $summary['kept_commissions_count']],
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
            'salary_amount' => $metrics['total_amount'],
            'net_paid_amount' => $metrics['net_paid_amount'],
            'outstanding_amount' => $metrics['outstanding_amount'],
            'refunded_amount' => $metrics['refunded_amount'],
            'transferred_in_amount' => $metrics['transferred_in_amount'],
            'transferred_out_amount' => $metrics['transferred_out_amount'],
            'status' => $metrics['status'],
            'is_kept' => (bool) $commission->is_kept,
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
                'transferred_in_amount' => (float) ($commission['transferred_in_amount'] ?? 0),
                'transferred_out_amount' => (float) ($commission['transferred_out_amount'] ?? 0),
                'status' => $commission['status'] ?? 'pending',
            ];
        }

        $outstanding = max(round((float) $commission->salary_amount, 2), 0);
        $payout = max(round((float) ($commission->payout_amount ?? 0), 2), 0);
        $refunded = max(round((float) ($commission->refunded_amount ?? 0), 2), 0);
        $netPaid = max(round($payout - $refunded, 2), 0);
        $transferredIn = max(round((float) ($commission->transferred_in_amount ?? 0), 2), 0);
        $transferredOut = max(round((float) ($commission->transferred_out_amount ?? 0), 2), 0);
        $total = round($outstanding + $netPaid, 2);

        $status = match (true) {
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
