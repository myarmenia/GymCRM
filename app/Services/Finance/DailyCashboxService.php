<?php

namespace App\Services\Finance;

use App\Models\MembershipSale;
use App\Models\User;
use App\Repositories\Finance\DailyCashboxRepository;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;

class DailyCashboxService
{
    public function __construct(protected DailyCashboxRepository $repository) {}

    public function pageData(User $actor): array
    {
        abort_unless($actor->hasAnyRole(['sales_manager', 'manager']) && $actor->gym_id !== null, 403);

        $day = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $payments = $this->repository->receivedPayments($actor, $day);
        $sales = $this->repository->salesWithoutReceivedPayment(
            $actor, $day, $payments->pluck('membership_sale_id')->unique()->all(),
        );

        // Return only the fields this personal cashbox is allowed to display.
        $rows = $payments->map(function ($payment) use ($day): array {
            return [
                'id' => 'payment-'.$payment->id,
                'occurred_at' => CarbonImmutable::parse($payment->received_at)->format('Y-m-d H:i:s'),
                ...$this->saleLabels($payment->membershipSale),
                'previous_sale' => ! $payment->membershipSale->sold_at?->isSameDay($day),
                'amount' => (float) $payment->received_amount,
                'payment_method' => $payment->paymentMethod?->name ?? $payment->paymentMethod?->slug ?? '-',
                'card_type' => $payment->cardType?->name,
            ];
        })->concat($sales->map(fn (MembershipSale $sale): array => [
            'id' => 'sale-'.$sale->id,
            'occurred_at' => $sale->sold_at->format('Y-m-d H:i:s'),
            ...$this->saleLabels($sale),
            'previous_sale' => false,
            'amount' => 0,
            'payment_method' => '-',
            'card_type' => null,
        ]))->sortBy([['occurred_at', 'desc'], ['id', 'desc']])->values();

        $byMethod = $payments->groupBy('payment_method_id')->map(function ($group): array {
            $method = $group->first()->paymentMethod;

            return [
                'id' => $method?->id,
                'name' => $method?->name ?? $method?->slug ?? '-',
                'amount' => round((float) $group->sum('received_amount'), 2),
            ];
        })->values();

        $page = LengthAwarePaginator::resolveCurrentPage();

        return [
            'date' => $day->toDateString(),
            'summary' => [
                'received_amount' => round((float) $payments->sum('received_amount'), 2),
                'payments_count' => $payments->count(),
                'today_sales_count' => $sales->count() + $payments
                    ->filter(fn ($payment) => (int) $payment->membershipSale->user_id === (int) $actor->id
                        && $payment->membershipSale->sold_at?->isSameDay($day))
                    ->pluck('membership_sale_id')->unique()->count(),
                'payment_methods' => $byMethod,
            ],
            'entries' => new LengthAwarePaginator(
                $rows->forPage($page, 25)->values(), $rows->count(), 25, $page,
                ['path' => LengthAwarePaginator::resolveCurrentPath()],
            ),
        ];
    }

    private function saleLabels(MembershipSale $sale): array
    {
        return [
            'customer' => trim(($sale->person?->name ?? '').' '.($sale->person?->surname ?? '')) ?: '-',
            'membership' => $sale->membershipPlan?->name ?? '-',
        ];
    }
}
