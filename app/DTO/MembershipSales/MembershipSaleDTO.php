<?php

namespace App\DTO\MembershipSales;

class MembershipSaleDTO
{
    public function __construct(
        public int $user_id,
        public int $person_id,
        public int $gym_id,
        public int $membership_plan_id,
        public float $total_price,
        public ?string $discount_type,
        public ?float $discount_value,
        public float $discount_amount,
        public float $final_price,
        public string $payment_status,
        public ?string $notes,
        public float $discount_membership_amount,
        public ?string $sold_at,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            user_id: (int) $data['user_id'],
            person_id: (int) $data['person_id'],
            gym_id: (int) $data['gym_id'],
            membership_plan_id: (int) $data['membership_plan_id'],
            total_price: (float) ($data['total_price'] ?? 0),
            discount_type: $data['discount_type'] ?? null,
            discount_value: isset($data['discount_value']) ? (float) $data['discount_value'] : null,
            discount_amount: (float) ($data['discount_amount'] ?? 0),
            final_price: (float) ($data['final_price'] ?? 0),
            payment_status: $data['payment_status'] ?? 'unpaid',
            notes: $data['notes'] ?? null,
            discount_membership_amount: (float) ($data['discount_membership_amount'] ?? 0),
            sold_at: $data['sold_at'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->user_id,
            'person_id' => $this->person_id,
            'gym_id' => $this->gym_id,
            'membership_plan_id' => $this->membership_plan_id,
            'total_price' => $this->total_price,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'discount_amount' => $this->discount_amount,
            'final_price' => $this->final_price,
            'payment_status' => $this->payment_status,
            'notes' => $this->notes,
            'discount_membership_amount' => $this->discount_membership_amount,
            'sold_at' => $this->sold_at,
        ];
    }
}
