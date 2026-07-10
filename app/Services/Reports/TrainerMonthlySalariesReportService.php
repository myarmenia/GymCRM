<?php

namespace App\Services\Reports;

use App\Interfaces\Reports\TrainerMonthlySalariesReportRepositoryInterface;
use App\Models\TrainerMonthlySalary;
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

        $paginatedSalaries->getCollection()->transform(fn (TrainerMonthlySalary $salary) => $this->mapSalary($salary));

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
            ->map(fn (TrainerMonthlySalary $salary) => $this->mapSalary($salary));

        return [
            'rows' => $salaries,
            'columns' => $this->exportColumns(),
            'filters' => $filters,
            'filename' => 'trainer-monthly-salaries-report-' . now()->format('Y-m-d-H-i-s') . '.xls',
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
            'trainers' => $this->trainerMonthlySalariesReportRepository->trainerOptions($user)
                ->map(fn (User $trainer) => [
                    'value' => $trainer->id,
                    'label' => $this->userName($trainer),
                ])
                ->values(),
            'statuses' => [
                ['value' => 'pending', 'label' => 'Սպասման մեջ'],
                ['value' => 'paid', 'label' => 'Վճարված'],
                ['value' => 'transfer', 'label' => 'Փոխանցում'],
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
            ['key' => 'price', 'title' => 'Գումար'],
            ['key' => 'status', 'title' => 'Կարգավիճակ'],
            ['key' => 'created_at', 'title' => 'Ստեղծվել է'],
        ];
    }

    protected function summary(Collection $salaries): array
    {
        return [
            'salaries_count' => $salaries->count(),
            'total_price' => round($salaries->sum(fn ($salary) => (float) (is_array($salary) ? ($salary['price'] ?? 0) : ($salary->price ?? 0))), 2),
            'paid_price' => round($salaries->sum(fn ($salary) => $this->statusValue($salary, 'paid')), 2),
            'pending_price' => round($salaries->sum(fn ($salary) => $this->statusValue($salary, 'pending')), 2),
        ];
    }

    protected function exportSummary(array $summary): array
    {
        return [
            'title' => 'Ամփոփում',
            'rows' => [
                ['label' => 'Գրանցումների քանակ', 'value' => $summary['salaries_count']],
                ['label' => 'Ընդհանուր գումար', 'value' => $summary['total_price']],
                ['label' => 'Վճարված գումար', 'value' => $summary['paid_price']],
                ['label' => 'Սպասող գումար', 'value' => $summary['pending_price']],
            ],
        ];
    }

    protected function mapSalary(TrainerMonthlySalary $salary): array
    {
        return [
            'id' => $salary->id,
            'trainer' => $this->userName($salary->trainer),
            'membership_customer' => $this->membershipCustomer($salary),
            'salary_month' => $salary->salary_month?->toDateString(),
            'price' => (float) $salary->price,
            'status' => $salary->status,
            'created_at' => $salary->created_at?->toDateTimeString(),
        ];
    }

    protected function statusValue(TrainerMonthlySalary|array $salary, string $status): float
    {
        $salaryStatus = is_array($salary) ? ($salary['status'] ?? null) : $salary->status;

        if ($salaryStatus !== $status) {
            return 0;
        }

        return (float) (is_array($salary) ? ($salary['price'] ?? 0) : ($salary->price ?? 0));
    }

    protected function membershipCustomer(TrainerMonthlySalary $salary): string
    {
        $membership = $salary->personMembership;
        $planName = $this->membershipPlanName($membership?->membershipPlan);
        $customerName = trim(($membership?->person?->name ?? '') . ' ' . ($membership?->person?->surname ?? '')) ?: '-';

        return trim(($planName ?? '-') . ' / ' . $customerName);
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
