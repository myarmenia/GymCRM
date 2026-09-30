<?php

namespace App\Services\Reports;

use App\Interfaces\Reports\MembershipSalesReportRepositoryInterface;
use App\Models\MembershipSale;
use App\Models\User;
use App\Services\HDM\HdmPrepaymentTerminationService;
use App\Services\MembershipSales\MembershipSaleService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MembershipSalesReportService
{
    public function __construct(
        protected MembershipSalesReportRepositoryInterface $membershipSalesReportRepository,
        protected MembershipSaleService $membershipSaleService,
    ) {}

    public function report(User $user, array $filters): array
    {
        $period = $this->resolvePeriod($filters);
        $reportFilters = $this->resolveReportFilters($filters);
        $summarySales = $this->membershipSalesReportRepository->salesForSummary(
            $user,
            $period['start_date'],
            $period['end_date'],
            $reportFilters
        );
        $paginatedSales = $this->membershipSalesReportRepository->paginatedSales(
            $user,
            $period['start_date'],
            $period['end_date'],
            $reportFilters
        );

        $paginatedSales->getCollection()->transform(fn (MembershipSale $sale) => $this->mapSale($sale));

        return [
            'filters' => array_merge($period, $reportFilters),
            'summary' => $this->summary($summarySales),
            'sales' => $paginatedSales,
            'totals' => $this->summary($paginatedSales->getCollection()),
        ];
    }

    public function exportData(User $user, array $filters): array
    {
        $period = $this->resolvePeriod($filters);
        $reportFilters = $this->resolveReportFilters($filters);
        $sales = $this->membershipSalesReportRepository
            ->salesForExport($user, $period['start_date'], $period['end_date'], $reportFilters)
            ->map(fn (MembershipSale $sale) => $this->mapSale($sale));

        return [
            'rows' => $sales,
            'columns' => $this->exportColumns(),
            'filters' => array_merge($period, $reportFilters),
            'filename' => 'membership-sales-report-'.now()->format('Y-m-d-H-i-s').'.xls',
            'title' => __('backend_messages.membership_report'),
            'summary' => $this->exportSummary($this->summary($sales)),
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

    protected function resolveReportFilters(array $filters): array
    {
        $reportFilter = in_array($filters['report_filter'] ?? null, [
            'discounted',
            'manual_discount',
            'membership_plan_discount',
            'fully_paid',
            'with_debt',
            'refund_due',
        ], true) ? $filters['report_filter'] : null;

        return [
            'report_filter' => $reportFilter,
        ];
    }

    protected function parseDate(?string $value, Carbon $fallback): Carbon
    {
        if (! $value) {
            return $fallback;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return $fallback;
        }
    }

    protected function exportColumns(): array
    {
        return [
            ['key' => 'id', 'title' => 'ID'],
            ['key' => 'customer', 'title' => __('backend_messages.client')],
            ['key' => 'membership_plan', 'title' => __('backend_messages.membership')],
            ['key' => 'trainer', 'title' => __('backend_messages.trainer')],
            ['key' => 'start_date', 'title' => __('backend_messages.start')],
            ['key' => 'end_date', 'title' => __('backend_messages.end')],
            ['key' => 'total_price', 'title' => __('backend_messages.price_label')],
            ['key' => 'manual_discount_amount', 'title' => __('backend_messages.manual_discount')],
            ['key' => 'membership_discount_amount', 'title' => __('backend_messages.membership_discount')],
            ['key' => 'gross_final_price', 'title' => __('backend_messages.gross_final_amount')],
            ['key' => 'refunded_amount', 'title' => __('backend_messages.refunded_amount')],
            ['key' => 'cancelled_amount', 'title' => __('backend_messages.cancelled_amount')],
            ['key' => 'final_price', 'title' => __('backend_messages.net_final_amount')],
            ['key' => 'paid_amount', 'title' => __('backend_messages.paid')],
            ['key' => 'debt', 'title' => __('backend_messages.debt')],
            ['key' => 'refund_due_amount', 'title' => __('backend_messages.refund_due_amount')],
            ['key' => 'status', 'title' => __('backend_messages.payment_state')],
            ['key' => 'created_at', 'title' => __('backend_messages.created')],
        ];
    }

    protected function exportSummary(array $summary): array
    {
        return [
            'title' => __('backend_messages.this_page_summary'),
            'rows' => [
                ['label' => __('backend_messages.memberships_sold'), 'value' => $summary['sold_memberships_count']],
                ['label' => __('backend_messages.cancelled_memberships'), 'value' => $summary['cancelled_memberships_count']],
                ['label' => __('backend_messages.initial_amount'), 'value' => $summary['total_amount']],
                ['label' => __('backend_messages.manual_discount'), 'value' => $summary['manual_discount_amount']],
                ['label' => __('backend_messages.membership_discount'), 'value' => $summary['membership_discount_amount']],
                ['label' => __('backend_messages.gross_final_amount'), 'value' => $summary['gross_final_amount']],
                ['label' => __('backend_messages.refunded_amount'), 'value' => $summary['refunded_amount']],
                ['label' => __('backend_messages.cancelled_amount'), 'value' => $summary['cancelled_amount']],
                ['label' => __('backend_messages.net_final_amount'), 'value' => $summary['final_amount']],
                ['label' => __('backend_messages.paid_amount'), 'value' => $summary['paid_amount']],
                ['label' => __('backend_messages.debt'), 'value' => $summary['debt']],
                ['label' => __('backend_messages.refund_due_amount'), 'value' => $summary['refund_due_amount']],
            ],
        ];
    }

    protected function summary(Collection $sales): array
    {
        $paidAmount = 0;
        $manualDiscountAmount = 0;
        $membershipDiscountAmount = 0;
        $totalAmount = 0;
        $grossFinalAmount = 0;
        $netFinalAmount = 0;
        $refundedAmount = 0;
        $cancelledAmount = 0;
        $cancelledMembershipsCount = 0;
        $debtAmount = 0;
        $refundDueAmount = 0;

        foreach ($sales as $sale) {
            $financials = $this->saleFinancials($sale);
            if ($this->isCancelled($sale)) {
                $cancelledMembershipsCount++;
            }

            $totalAmount += (float) (is_array($sale) ? ($sale['total_price'] ?? 0) : ($sale->total_price ?? 0));
            $grossFinalAmount += $financials['gross_final_amount'];
            $netFinalAmount += $financials['net_final_amount'];
            $refundedAmount += $financials['refunded_amount'];
            $cancelledAmount += $financials['cancelled_amount'];
            $manualDiscountAmount += (float) (is_array($sale) ? ($sale['manual_discount_amount'] ?? 0) : ($sale->discount_amount ?? 0));
            $membershipDiscountAmount += (float) (is_array($sale) ? ($sale['membership_discount_amount'] ?? 0) : $this->membershipDiscountAmount($sale));
            $paidAmount += $financials['net_paid_amount'];
            $debtAmount += (float) (is_array($sale)
                ? ($sale['debt'] ?? 0)
                : max($financials['net_final_amount'] - $financials['net_paid_amount'], 0));
            $refundDueAmount += (float) (is_array($sale) ? ($sale['refund_due_amount'] ?? 0) : 0);
        }

        return [
            'sold_memberships_count' => $sales->count(),
            'cancelled_memberships_count' => $cancelledMembershipsCount,
            'total_amount' => round($totalAmount, 2),
            'paid_amount' => round($paidAmount, 2),
            'debt' => round($debtAmount, 2),
            'manual_discount_amount' => round($manualDiscountAmount, 2),
            'membership_discount_amount' => round($membershipDiscountAmount, 2),
            'gross_final_amount' => round($grossFinalAmount, 2),
            'refunded_amount' => round($refundedAmount, 2),
            'cancelled_amount' => round($cancelledAmount, 2),
            'final_amount' => round($netFinalAmount, 2),
            'refund_due_amount' => round($refundDueAmount, 2),
        ];
    }

    protected function mapSale(MembershipSale $sale): array
    {
        $membership = $sale->personMemberships->first();
        $financials = $this->saleFinancials($sale);

        return [
            'id' => $sale->id,
            'person_id' => $sale->person_id,
            'customer' => $this->personName($sale),
            'membership_plan' => $this->planName($sale),
            'trainer' => $this->trainerName($membership),
            'start_date' => $membership?->start_date,
            'end_date' => $membership?->valid_at ?? $membership?->end_date,
            'total_price' => (float) $sale->total_price,
            'manual_discount_amount' => (float) $sale->discount_amount,
            'membership_discount_amount' => $this->membershipDiscountAmount($sale),
            'discount_amount' => (float) $sale->discount_amount + $this->membershipDiscountAmount($sale),
            'gross_final_price' => $financials['gross_final_amount'],
            'refunded_amount' => $financials['refunded_amount'],
            'cancelled_amount' => $financials['cancelled_amount'],
            'final_price' => $financials['net_final_amount'],
            'paid_amount' => $financials['net_paid_amount'],
            'debt' => max($financials['net_final_amount'] - $financials['net_paid_amount'], 0),
            'refund_due_amount' => $this->membershipSaleService->availableRefundAmount($sale),
            'status' => $this->isCancelled($sale) ? 'cancelled' : $sale->payment_status,
            'created_at' => $sale->created_at?->toDateTimeString(),
        ];
    }

    protected function paidAmount(MembershipSale|array $sale): float
    {
        return $this->paymentAmounts($sale)['net_paid_amount'];
    }

    /** @return array{paid_amount: float, refunded_amount: float, net_paid_amount: float} */
    protected function paymentAmounts(MembershipSale|array $sale): array
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

        return [
            'paid_amount' => round($paid, 2),
            'refunded_amount' => round($refunded, 2),
            'net_paid_amount' => round(max($paid - $refunded, 0), 2),
        ];
    }

    /** @return array{gross_final_amount: float, refunded_amount: float, cancelled_amount: float, net_final_amount: float, net_paid_amount: float} */
    protected function saleFinancials(MembershipSale|array $sale): array
    {
        if (is_array($sale)) {
            return [
                'gross_final_amount' => (float) ($sale['gross_final_price'] ?? 0),
                'refunded_amount' => (float) ($sale['refunded_amount'] ?? 0),
                'cancelled_amount' => (float) ($sale['cancelled_amount'] ?? 0),
                'net_final_amount' => (float) ($sale['final_price'] ?? 0),
                'net_paid_amount' => (float) ($sale['paid_amount'] ?? 0),
            ];
        }

        $paymentAmounts = $this->paymentAmounts($sale);
        $grossFinalAmount = (float) ($sale->final_price ?? 0);
        $cancelled = $this->isCancelled($sale);
        $retainedServiceAmount = $cancelled
            ? $this->retainedServiceAmount($sale)
            : 0.0;
        $overpaidAmount = max($paymentAmounts['paid_amount'] - $grossFinalAmount, 0);
        $refundAdjustment = max($paymentAmounts['refunded_amount'] - $overpaidAmount, 0);
        $cancelledAmount = $cancelled
            ? max($grossFinalAmount - $retainedServiceAmount - $refundAdjustment, 0)
            : 0.0;

        return [
            'gross_final_amount' => round($grossFinalAmount, 2),
            'refunded_amount' => $paymentAmounts['refunded_amount'],
            'cancelled_amount' => round($cancelledAmount, 2),
            'net_final_amount' => round(max($grossFinalAmount - $cancelledAmount - $refundAdjustment, 0), 2),
            'net_paid_amount' => $paymentAmounts['net_paid_amount'],
        ];
    }

    protected function isCancelled(MembershipSale|array $sale): bool
    {
        $paymentStatus = is_array($sale) ? ($sale['status'] ?? $sale['payment_status'] ?? null) : $sale->payment_status;
        $memberships = is_array($sale)
            ? collect($sale['person_memberships'] ?? [])
            : ($sale->personMemberships ?? collect());

        return $paymentStatus === 'cancelled'
            || $memberships->contains(fn ($membership): bool => ($membership['status'] ?? $membership->status ?? null) === 'cancelled');
    }

    protected function retainedServiceAmount(MembershipSale $sale): float
    {
        return round($sale->payments
            ->flatMap(fn ($payment) => $payment->hdmOperations)
            ->filter(fn ($operation): bool => $operation->transaction_type === HdmPrepaymentTerminationService::WORKFLOW_TRANSACTION_TYPE
                && $operation->status === 'success')
            ->sum(fn ($operation): float => (float) data_get($operation->request, 'service_amount', 0)), 2);
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
        return trim(($sale->person?->name ?? '').' '.($sale->person?->surname ?? '')) ?: '-';
    }

    protected function planName(MembershipSale $sale): string
    {
        return $sale->membershipPlan?->translations?->firstWhere('locale', app()->getLocale())?->name
            ?? $sale->membershipPlan?->name
            ?? '-';
    }

    protected function trainerName($membership): string
    {
        return trim(($membership?->trainer?->name ?? '').' '.($membership?->trainer?->surname ?? '')) ?: '-';
    }
}
