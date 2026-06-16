<?php

namespace App\DTO\MembershipSaleDiscounts;

class MembershipSaleDiscountDTO
{
    public function __construct(
        public int $membership_sale_id,
        public int $discount_id,
        public string $discount_type,
        public float $discount_value,
        public float $discount_amount,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            membership_sale_id: (int) $data['membership_sale_id'],
            discount_id: (int) $data['discount_id'],
            discount_type: $data['discount_type'],
            discount_value: (float) ($data['discount_value'] ?? 0),
            discount_amount: (float) ($data['discount_amount'] ?? 0),
        );
    }

    public function toArray(): array
    {
        return [
            'membership_sale_id' => $this->membership_sale_id,
            'discount_id' => $this->discount_id,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'discount_amount' => $this->discount_amount,
        ];
    }
}
