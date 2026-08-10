<?php

namespace App\Http\Resources\Mobile;

use App\Models\MembershipPlanPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileMembershipDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $membership = (new MobileMembershipResource($this->resource))->toArray($request);
        $sale = $this->membershipSale;
        $payments = $sale?->payments ?? collect();
        $paid = (float) $payments
            ->where('type', 'payment')
            ->where('status', 'paid')
            ->sum('amount');
        $refunded = (float) $payments
            ->where('type', 'refund')
            ->where('status', 'paid')
            ->sum('amount');
        $netPaid = max($paid - $refunded, 0);
        $finalPrice = (float) ($sale?->final_price ?? 0);
        $debt = $this->status === 'cancelled' ? 0 : max($finalPrice - $netPaid, 0);
        $refundDue = $this->status === 'cancelled'
            ? max($paid - $refunded, 0)
            : max($netPaid - $finalPrice, 0);

        return [
            ...$membership,
            'activated_at' => $this->activated_at?->toIso8601String(),
            'expired_at' => $this->expired_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'gym' => $this->gym ? [
                'id' => $this->gym->id,
                'name' => $this->gym->name,
                'address' => $this->gym->address,
                'phone' => $this->gym->phone,
                'email' => $this->gym->email,
            ] : null,
            'sale' => $sale ? [
                'id' => $sale->id,
                'sold_at' => $sale->sold_at?->toIso8601String(),
                'payment_status' => $sale->payment_status,
                'original_price' => $sale->total_price,
                'discount_type' => $sale->discount_type,
                'discount_value' => $sale->discount_value,
                'discount_amount' => $sale->discount_amount,
                'final_price' => $sale->final_price,
                'paid_amount' => number_format($paid, 2, '.', ''),
                'refunded_amount' => number_format($refunded, 2, '.', ''),
                'net_paid_amount' => number_format($netPaid, 2, '.', ''),
                'debt_amount' => number_format($debt, 2, '.', ''),
                'overpaid_amount' => number_format(max($netPaid - $finalPrice, 0), 2, '.', ''),
                'refund_due_amount' => number_format($refundDue, 2, '.', ''),
                'notes' => $sale->notes,
            ] : null,
            'payments' => $payments
                ->map(fn (MembershipPlanPayment $payment) => [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'type' => $payment->type,
                    'status' => $payment->status,
                    'payment_method' => $payment->paymentMethod ? [
                        'id' => $payment->paymentMethod->id,
                        'name' => $payment->paymentMethod->name ?? $payment->paymentMethod->slug,
                    ] : null,
                    'card_type' => $payment->cardType ? [
                        'id' => $payment->cardType->id,
                        'name' => $payment->cardType->name ?? $payment->cardType->slug,
                    ] : null,
                    'is_hdm' => $payment->is_hdm,
                    'notes' => $payment->notes,
                    'paid_at' => $payment->created_at?->toIso8601String(),
                ])
                ->values(),
            'freezes' => $this->freezes
                ->map(fn ($freeze) => [
                    'id' => $freeze->id,
                    'start_date' => $freeze->start_date?->toDateString(),
                    'end_date' => $freeze->end_date?->toDateString(),
                    'notes' => $freeze->notes,
                ])
                ->values(),
            'guests' => $this->guests
                ->map(fn ($guestRecord) => [
                    'id' => $guestRecord->id,
                    'name' => $guestRecord->guest
                        ? trim("{$guestRecord->guest->name} {$guestRecord->guest->surname}")
                        : null,
                    'visited_at' => $guestRecord->created_at?->toIso8601String(),
                ])
                ->values(),
        ];
    }
}
