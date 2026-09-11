<?php

namespace App\DTO\TrainerCommissions;

class TrainerCommissionDTO
{
    public function __construct(
        public int $trainer_id,
        public int $membership_sale_id,
        public int $person_membership_id,
        public string $salary_type,
        public float $salary_value,
        public float $salary_amount,
        public float $initial_salary_amount,
        public string $calculation_mode,
        public int $salary_start_installment,
        public int $salary_installment_count,
        public string $status,
        public ?string $paid_at,
        public bool $is_kept,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            trainer_id: (int) $data['trainer_id'],
            membership_sale_id: (int) $data['membership_sale_id'],
            person_membership_id: (int) $data['person_membership_id'],
            salary_type: $data['salary_type'],
            salary_value: (float) ($data['salary_value'] ?? 0),
            salary_amount: (float) ($data['salary_amount'] ?? 0),
            initial_salary_amount: (float) ($data['initial_salary_amount'] ?? $data['salary_amount'] ?? 0),
            calculation_mode: $data['calculation_mode'] ?? 'prepaid',
            salary_start_installment: max((int) ($data['salary_start_installment'] ?? 1), 1),
            salary_installment_count: max((int) ($data['salary_installment_count'] ?? 1), 0),
            status: $data['status'] ?? 'pending',
            paid_at: $data['paid_at'] ?? null,
            is_kept: (bool) ($data['is_kept'] ?? false),
        );
    }

    public function toArray(): array
    {
        return [
            'trainer_id' => $this->trainer_id,
            'membership_sale_id' => $this->membership_sale_id,
            'person_membership_id' => $this->person_membership_id,
            'salary_type' => $this->salary_type,
            'salary_value' => $this->salary_value,
            'salary_amount' => $this->salary_amount,
            'initial_salary_amount' => $this->initial_salary_amount,
            'calculation_mode' => $this->calculation_mode,
            'salary_start_installment' => $this->salary_start_installment,
            'salary_installment_count' => $this->salary_installment_count,
            'status' => $this->status,
            'paid_at' => $this->paid_at,
            'is_kept' => $this->is_kept,
        ];
    }
}
