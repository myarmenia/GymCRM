<?php

namespace App\Services\Audit\Snapshots;

use App\Models\MembershipSale;

class MembershipSaleSnapshotFactory
{
    public function make(MembershipSale $sale): array
    {
        $sale->load([
            'user:id,name,surname',
            'person:id,name,surname,email,phone',
            'gym:id,name',
            'membershipPlan.translations',
            'personMemberships.trainer:id,name,surname',
            'personMemberships.freezes',
            'discounts.discount.translations',
            'payments.paymentMethod.translations',
            'payments.cardType',
        ]);

        $membership = $sale->personMemberships->first();

        return [
            'id' => $sale->id,
            'person' => $sale->person ? [
                'id' => $sale->person->id,
                'name' => $this->fullName($sale->person),
                'email' => $sale->person->email,
                'phone' => $sale->person->phone,
            ] : null,
            'gym' => $sale->gym ? [
                'id' => $sale->gym->id,
                'name' => $sale->gym->name,
            ] : null,
            'membership_plan' => $sale->membershipPlan ? [
                'id' => $sale->membershipPlan->id,
                'name' => $sale->membershipPlan->name ?? "#{$sale->membershipPlan->id}",
            ] : null,
            'salesperson' => $sale->user ? [
                'id' => $sale->user->id,
                'name' => $this->fullName($sale->user),
            ] : null,
            'membership' => $membership ? [
                'id' => $membership->id,
                'status' => $membership->status,
                'start_date' => $membership->start_date?->toDateString(),
                'end_date' => $membership->end_date?->toDateString(),
                'trainer' => $membership->trainer ? [
                    'id' => $membership->trainer->id,
                    'name' => $this->fullName($membership->trainer),
                ] : null,
                'visits_left' => $membership->visits_left,
                'freeze_left' => $membership->freeze_left,
                'freeze_used' => $membership->freeze_used,
                'guest_left' => $membership->guest_left,
                'valid_at' => $membership->valid_at?->toDateString(),
                'freezes' => $membership->freezes
                    ->map(fn ($freeze) => [
                        'start_date' => $freeze->start_date?->toDateString(),
                        'end_date' => $freeze->end_date?->toDateString(),
                        'notes' => $freeze->notes,
                    ])
                    ->values()
                    ->all(),
            ] : null,
            'total_price' => $this->money($sale->total_price),
            'discount_type' => $sale->discount_type,
            'discount_value' => $this->money($sale->discount_value),
            'discount_amount' => $this->money($sale->discount_amount),
            'discount_membership_amount' => $this->money($sale->discount_membership_amount),
            'final_price' => $this->money($sale->final_price),
            'payment_status' => $sale->payment_status,
            'notes' => $sale->notes,
            'sold_at' => $sale->sold_at?->format('Y-m-d H:i:s'),
            'discounts' => $sale->discounts
                ->map(fn ($discount) => [
                    'name' => $discount->discount?->name ?? "#{$discount->discount_id}",
                    'type' => $discount->discount_type,
                    'value' => $this->money($discount->discount_value),
                    'amount' => $this->money($discount->discount_amount),
                ])
                ->values()
                ->all(),
            'payments' => $sale->payments
                ->map(fn ($payment) => [
                    'type' => $payment->type,
                    'status' => $payment->status,
                    'amount' => $this->money($payment->amount),
                    'payment_method' => $payment->paymentMethod?->name
                        ?? ($payment->payment_method_id ? "#{$payment->payment_method_id}" : null),
                    'card_type' => $payment->cardType?->name,
                    'is_hdm' => (bool) $payment->is_hdm,
                    'notes' => $payment->notes,
                ])
                ->values()
                ->all(),
        ];
    }

    protected function fullName(object $model): string
    {
        return trim(($model->name ?? '').' '.($model->surname ?? ''));
    }

    protected function money(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
