<?php

namespace App\DTO\SalespersonCommissions;

class SalespersonCommissionDTO
{
    public function __construct(
        public int $salesperson_id,
        public int $membership_sale_id,
        public ?int $person_membership_id,
        public int $membership_plan_id,
        public string $salary_type,
        public float $salary_value,
        public float $salary_amount,
        public float $sale_amount,
        public string $status,
        public ?string $paid_at,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            salesperson_id: (int) $data['salesperson_id'],
            membership_sale_id: (int) $data['membership_sale_id'],
            person_membership_id: isset($data['person_membership_id']) ? (int) $data['person_membership_id'] : null,
            membership_plan_id: (int) $data['membership_plan_id'],
            salary_type: $data['salary_type'],
            salary_value: (float) ($data['salary_value'] ?? 0),
            salary_amount: (float) ($data['salary_amount'] ?? 0),
            sale_amount: (float) ($data['sale_amount'] ?? 0),
            status: $data['status'] ?? 'pending',
            paid_at: $data['paid_at'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'salesperson_id' => $this->salesperson_id,
            'membership_sale_id' => $this->membership_sale_id,
            'person_membership_id' => $this->person_membership_id,
            'membership_plan_id' => $this->membership_plan_id,
            'salary_type' => $this->salary_type,
            'salary_value' => $this->salary_value,
            'salary_amount' => $this->salary_amount,
            'sale_amount' => $this->sale_amount,
            'status' => $this->status,
            'paid_at' => $this->paid_at,
        ];
    }
}
