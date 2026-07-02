<?php

namespace App\Services\Reports;

use App\Interfaces\Reports\CommissionsReportRepositoryInterface;
use App\Models\SalespersonCommission;
use App\Models\TrainerCommission;
use App\Models\User;

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
                    ['value' => 'pending', 'label' => 'Սպասման մեջ'],
                    ['value' => 'paid', 'label' => 'Վճարված'],
                ],
                'salespersonStatuses' => [
                    ['value' => 'pending', 'label' => 'Սպասման մեջ'],
                    ['value' => 'paid', 'label' => 'Վճարված'],
                    ['value' => 'cancelled', 'label' => 'Չեղարկված'],
                ],
            ],
        ];
    }

    protected function reportFilters(array $filters): array
    {
        return collect($filters)
            ->only(self::FILTER_KEYS)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
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
