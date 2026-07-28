<?php

namespace App\Services\Reports;

use App\Interfaces\Reports\TrainerMonthlySalariesReportRepositoryInterface;
use App\Models\SalaryPayableAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TrainerMonthlySalariesReportService
{
    public function __construct(
        protected TrainerMonthlySalariesReportRepositoryInterface $trainerMonthlySalariesReportRepository,
    ) {}

    public function report(User $user, array $filters = []): array
    {
        $filters = $this->reportFilters($filters);
        $summarySalaries = $this->trainerMonthlySalariesReportRepository->salariesForSummary($user, $filters);
        $paginatedSalaries = $this->trainerMonthlySalariesReportRepository->paginatedSalaries($user, $filters);

        $paginatedSalaries->getCollection()->transform(
            fn (SalaryPayableAssignment $assignment) => $this->mapSalary($assignment)
        );

        return [
            'filters' => $filters,
            'summary' => $this->summary($summarySalaries),
            'salaries' => $paginatedSalaries,
            'filterOptions' => $this->filterOptions($user),
        ];
    }

    public function exportData(User $user, array $filters = []): array
    {
        $filters = $this->reportFilters($filters);
        $salaries = $this->trainerMonthlySalariesReportRepository
            ->salariesForExport($user, $filters)
            ->map(fn (SalaryPayableAssignment $assignment) => $this->mapSalary($assignment));

        return [
            'rows' => $salaries,
            'columns' => $this->exportColumns(),
            'filters' => $filters,
            'filename' => 'trainer-monthly-salaries-report-'.now()->format('Y-m-d-H-i-s').'.xls',
            'title' => 'Մարզիչների աշխատավարձերի հաշվետվություն',
            'summary' => $this->exportSummary($this->summary($salaries)),
        ];
    }

    protected function reportFilters(array $filters): array
    {
        return array_merge(
            $this->resolvePeriod($filters),
            collect($filters)
                ->only(['trainer_id', 'status'])
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
            'trainers' => $this->trainerMonthlySalariesReportRepository->trainerOptions($user)
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
                ['value' => 'cancel', 'label' => 'Չեղարկված'],
                ['value' => 'reject', 'label' => 'Մերժված'],
            ],
        ];
    }

    protected function exportColumns(): array
    {
        return [
            ['key' => 'trainer', 'title' => 'Մարզիչ'],
            ['key' => 'membership_customer', 'title' => 'Աբոնեմենտ / հաճախորդ'],
            ['key' => 'salary_month', 'title' => 'Աշխատավարձի ամիս'],
            ['key' => 'price', 'title' => 'Մարզչին վերագրված'],
            ['key' => 'net_paid_amount', 'title' => 'Զուտ վճարված'],
            ['key' => 'outstanding_amount', 'title' => 'Չվճարված մնացորդ'],
            ['key' => 'refunded_amount', 'title' => 'Վերադարձված'],
            ['key' => 'transferred_in_amount', 'title' => 'Փոխանցված մուտք'],
            ['key' => 'transferred_out_amount', 'title' => 'Փոխանցված ելք'],
            ['key' => 'status', 'title' => 'Կարգավիճակ'],
            ['key' => 'created_at', 'title' => 'Ստեղծվել է'],
        ];
    }

    protected function summary(Collection $salaries): array
    {
        $metrics = $salaries->map(fn ($salary) => $this->salaryMetrics($salary));
        $active = $metrics->reject(fn (array $salary) => in_array($salary['status'], ['cancel', 'reject'], true));

        return [
            'salaries_count' => $salaries->map(
                fn ($salary) => is_array($salary)
                    ? ($salary['source_salary_id'] ?? $salary['id'])
                    : $salary->trainer_monthly_salary_id
            )->unique()->count(),
            'parts_count' => $salaries->count(),
            'total_price' => round($active->sum('total_amount'), 2),
            'paid_price' => round($active->sum('net_paid_amount'), 2),
            'pending_price' => round($active->sum('outstanding_amount'), 2),
            'refunded_price' => round($active->sum('refunded_amount'), 2),
            'transferred_in_price' => round($active->sum('transferred_in_amount'), 2),
            'transferred_out_price' => round($active->sum('transferred_out_amount'), 2),
            'cancelled_price' => round($metrics->where('status', 'cancel')->sum('total_amount'), 2),
            'rejected_price' => round($metrics->where('status', 'reject')->sum('total_amount'), 2),
        ];
    }

    protected function exportSummary(array $summary): array
    {
        return [
            'title' => 'Ամփոփում',
            'rows' => [
                ['label' => 'Գրանցումների քանակ', 'value' => $summary['salaries_count']],
                ['label' => 'Մարզիչների բաժինների քանակ', 'value' => $summary['parts_count']],
                ['label' => 'Ընդհանուր գումար', 'value' => $summary['total_price']],
                ['label' => 'Վճարված գումար', 'value' => $summary['paid_price']],
                ['label' => 'Սպասող գումար', 'value' => $summary['pending_price']],
                ['label' => 'Վերադարձված գումար', 'value' => $summary['refunded_price']],
                ['label' => 'Փոխանցված մուտք', 'value' => $summary['transferred_in_price']],
                ['label' => 'Փոխանցված ելք', 'value' => $summary['transferred_out_price']],
                ['label' => 'Չեղարկված գումար', 'value' => $summary['cancelled_price']],
                ['label' => 'Մերժված գումար', 'value' => $summary['rejected_price']],
            ],
        ];
    }

    protected function mapSalary(SalaryPayableAssignment $assignment): array
    {
        $salary = $assignment->trainerMonthlySalary;
        $metrics = $this->salaryMetrics($assignment);

        return [
            'id' => $assignment->id,
            'source_salary_id' => $salary?->id,
            'trainer' => $this->userName($assignment->payee),
            'membership_customer' => $this->membershipCustomer($salary),
            'salary_month' => $salary?->salary_month?->toDateString(),
            'price' => $metrics['total_amount'],
            'net_paid_amount' => $metrics['net_paid_amount'],
            'outstanding_amount' => $metrics['outstanding_amount'],
            'refunded_amount' => $metrics['refunded_amount'],
            'transferred_in_amount' => $metrics['transferred_in_amount'],
            'transferred_out_amount' => $metrics['transferred_out_amount'],
            'status' => $metrics['status'],
            'created_at' => $assignment->created_at?->toDateTimeString(),
        ];
    }

    protected function salaryMetrics(SalaryPayableAssignment|array $salary): array
    {
        if (is_array($salary)) {
            return [
                'total_amount' => (float) ($salary['price'] ?? 0),
                'net_paid_amount' => (float) ($salary['net_paid_amount'] ?? 0),
                'outstanding_amount' => (float) ($salary['outstanding_amount'] ?? 0),
                'refunded_amount' => (float) ($salary['refunded_amount'] ?? 0),
                'transferred_in_amount' => (float) ($salary['transferred_in_amount'] ?? 0),
                'transferred_out_amount' => (float) ($salary['transferred_out_amount'] ?? 0),
                'status' => $salary['status'] ?? 'pending',
            ];
        }

        $outstanding = max(round((float) $salary->available_amount, 2), 0);
        $payout = max(round((float) ($salary->payout_amount ?? 0), 2), 0);
        $refunded = max(round((float) ($salary->refunded_amount ?? 0), 2), 0);
        $netPaid = max(round($payout - $refunded, 2), 0);
        $transferredIn = $salary->parent_assignment_id
            ? max(round((float) $salary->amount, 2), 0)
            : 0;
        $transferredOut = max(round((float) ($salary->transferred_out_amount ?? 0), 2), 0);
        $sourceStatus = $salary->trainerMonthlySalary?->status;

        $status = match (true) {
            in_array($sourceStatus, ['cancel', 'reject'], true) => $sourceStatus,
            $outstanding > 0 && $netPaid > 0 => 'partial',
            $outstanding <= 0 && $netPaid > 0 => 'paid',
            $outstanding <= 0 && $transferredOut > 0 => 'transferred',
            default => 'pending',
        };

        return [
            'total_amount' => round($outstanding + $netPaid, 2),
            'net_paid_amount' => $netPaid,
            'outstanding_amount' => $outstanding,
            'refunded_amount' => $refunded,
            'transferred_in_amount' => $transferredIn,
            'transferred_out_amount' => $transferredOut,
            'status' => $status,
        ];
    }

    protected function membershipCustomer($salary): string
    {
        $membership = $salary?->personMembership;
        $planName = $this->membershipPlanName($membership?->membershipPlan);
        $customerName = trim(($membership?->person?->name ?? '').' '.($membership?->person?->surname ?? '')) ?: '-';

        return trim(($planName ?? '-').' / '.$customerName);
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
