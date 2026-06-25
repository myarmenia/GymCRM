<?php

namespace App\DTO\MembershipPlanPayments;

class MembershipPlanPaymentDTO
{
    public function __construct(
        public int $membership_sale_id,
        public float $amount,
        public int $payment_method_id,
        public ?int $card_type_id,
        public string $status,
        public string $type,
        public bool $is_hdm,
        public ?string $notes,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            membership_sale_id: (int) $data['membership_sale_id'],
            amount: (float) ($data['amount'] ?? 0),
            payment_method_id: (int) $data['payment_method_id'],
            card_type_id: isset($data['card_type_id']) ? (int) $data['card_type_id'] : null,
            status: $data['status'] ?? 'pending',
            type: $data['type'] ?? 'payment',
            is_hdm: (bool) ($data['is_hdm'] ?? false),
            notes: $data['notes'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'membership_sale_id' => $this->membership_sale_id,
            'amount' => $this->amount,
            'payment_method_id' => $this->payment_method_id,
            'card_type_id' => $this->card_type_id,
            'status' => $this->status,
            'type' => $this->type,
            'is_hdm' => $this->is_hdm,
            'notes' => $this->notes,
        ];
    }
}
