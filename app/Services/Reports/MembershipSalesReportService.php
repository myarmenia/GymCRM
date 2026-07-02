<?php

namespace App\Services\Reports;

use App\Interfaces\Reports\MembershipSalesReportRepositoryInterface;
use App\Models\MembershipSale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class MembershipSalesReportService
{
    public function __construct(
        protected MembershipSalesReportRepositoryInterface $membershipSalesReportRepository,
    ) {}

    public function report(User $user, array $filters): array
    {
        $period = $this->resolvePeriod($filters);
        $summarySales = $this->membershipSalesReportRepository->salesForSummary(
            $user,
            $period['start_date'],
            $period['end_date']
        );
        $paginatedSales = $this->membershipSalesReportRepository->paginatedSales(
            $user,
            $period['start_date'],
            $period['end_date']
        );

        $paginatedSales->getCollection()->transform(fn (MembershipSale $sale) => $this->mapSale($sale));

        return [
            'filters' => $period,
            'summary' => $this->summary($summarySales),
            'sales' => $paginatedSales,
            'totals' => $this->summary($paginatedSales->getCollection()),
        ];
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

    protected function summary(Collection $sales): array
    {
        $paidAmount = 0;
        $manualDiscountAmount = 0;
        $membershipDiscountAmount = 0;
        $totalAmount = 0;
        $finalAmount = 0;

        foreach ($sales as $sale) {
            $totalAmount += (float) (is_array($sale) ? ($sale['total_price'] ?? 0) : ($sale->total_price ?? 0));
            $finalAmount += (float) (is_array($sale) ? ($sale['final_price'] ?? 0) : ($sale->final_price ?? 0));
            $manualDiscountAmount += (float) (is_array($sale) ? ($sale['manual_discount_amount'] ?? 0) : ($sale->discount_amount ?? 0));
            $membershipDiscountAmount += (float) (is_array($sale) ? ($sale['membership_discount_amount'] ?? 0) : $this->membershipDiscountAmount($sale));
            $paidAmount += (float) (is_array($sale) ? ($sale['paid_amount'] ?? 0) : $this->paidAmount($sale));
        }

        return [
            'sold_memberships_count' => $sales->count(),
            'total_amount' => round($totalAmount, 2),
            'paid_amount' => round($paidAmount, 2),
            'debt' => round(max($finalAmount - $paidAmount, 0), 2),
            'manual_discount_amount' => round($manualDiscountAmount, 2),
            'membership_discount_amount' => round($membershipDiscountAmount, 2),
            'final_amount' => round($finalAmount, 2),
        ];
    }

    protected function mapSale(MembershipSale $sale): array
    {
        $membership = $sale->personMemberships->first();
        $paidAmount = $this->paidAmount($sale);

        return [
            'id' => $sale->id,
            'customer' => $this->personName($sale),
            'membership_plan' => $this->planName($sale),
            'trainer' => $this->trainerName($membership),
            'start_date' => $membership?->start_date,
            'end_date' => $membership?->valid_at ?? $membership?->end_date,
            'total_price' => (float) $sale->total_price,
            'manual_discount_amount' => (float) $sale->discount_amount,
            'membership_discount_amount' => $this->membershipDiscountAmount($sale),
            'discount_amount' => (float) $sale->discount_amount + $this->membershipDiscountAmount($sale),
            'final_price' => (float) $sale->final_price,
            'paid_amount' => $paidAmount,
            'debt' => max((float) $sale->final_price - $paidAmount, 0),
            'status' => $sale->payment_status,
            'created_at' => $sale->created_at?->toDateTimeString(),
        ];
    }

    protected function paidAmount(MembershipSale|array $sale): float
    {
        $payments = is_array($sale) ? ($sale['payments'] ?? collect()) : ($sale->payments ?? collect());
        $paid = 0;
        $refunded = 0;

        foreach ($payments as $payment) {
            if (($payment['status'] ?? $payment->status) !== 'paid') {
                continue;
            }

            if (($payment['type'] ?? $payment->type) === 'refund') {
                $refunded += (float) ($payment['amount'] ?? $payment->amount ?? 0);
                continue;
            }

            $paid += (float) ($payment['amount'] ?? $payment->amount ?? 0);
        }

        return max($paid - $refunded, 0);
    }

    protected function membershipDiscountAmount(MembershipSale|array $sale): float
    {
        $discounts = is_array($sale) ? ($sale['discounts'] ?? collect()) : ($sale->discounts ?? collect());
        $amount = 0;

        foreach ($discounts as $discount) {
            $amount += (float) ($discount['discount_amount'] ?? $discount->discount_amount ?? 0);
        }

        return $amount;
    }

    protected function personName(MembershipSale $sale): string
    {
        return trim(($sale->person?->name ?? '') . ' ' . ($sale->person?->surname ?? '')) ?: '-';
    }

    protected function planName(MembershipSale $sale): string
    {
        return $sale->membershipPlan?->translations?->firstWhere('locale', app()->getLocale())?->name
            ?? $sale->membershipPlan?->name
            ?? '-';
    }

    protected function trainerName($membership): string
    {
        return trim(($membership?->trainer?->name ?? '') . ' ' . ($membership?->trainer?->surname ?? '')) ?: '-';
    }
}
