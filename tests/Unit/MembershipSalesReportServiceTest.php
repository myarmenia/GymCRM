<?php

namespace Tests\Unit;

use App\Interfaces\Reports\MembershipSalesReportRepositoryInterface;
use App\Models\HdmOperation;
use App\Models\MembershipPlanPayment;
use App\Models\MembershipSale;
use App\Models\PersonMembership;
use App\Models\User;
use App\Services\MembershipSales\MembershipSaleService;
use App\Services\Reports\MembershipSalesReportService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class MembershipSalesReportServiceTest extends TestCase
{
    public function test_net_final_deducts_cancelled_sales_once_and_ignores_overpayment_refunds(): void
    {
        $cancelledSale = $this->sale(21, 200);
        $cancelledSale->setRelation('personMemberships', new EloquentCollection([
            new PersonMembership(['status' => 'cancelled']),
        ]));
        $cancelledSale->setRelation('payments', new EloquentCollection([
            new MembershipPlanPayment(['type' => 'payment', 'status' => 'paid', 'amount' => 200]),
            new MembershipPlanPayment(['type' => 'refund', 'status' => 'paid', 'amount' => 20]),
        ]));

        $overpaidSale = $this->sale(22, 100);
        $overpaidSale->setRelation('payments', new EloquentCollection([
            new MembershipPlanPayment(['type' => 'payment', 'status' => 'paid', 'amount' => 120]),
            new MembershipPlanPayment(['type' => 'refund', 'status' => 'paid', 'amount' => 20]),
        ]));

        /** @var MembershipSalesReportRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(MembershipSalesReportRepositoryInterface::class);
        $repository->method('salesForSummary')->willReturn(collect([$cancelledSale, $overpaidSale]));
        $repository->method('paginatedSales')->willReturn(new LengthAwarePaginator(
            collect([$cancelledSale, $overpaidSale]),
            2,
            20,
        ));

        /** @var MembershipSaleService&MockObject $saleService */
        $saleService = $this->createMock(MembershipSaleService::class);
        $saleService->method('availableRefundAmount')->willReturn(0.0);

        $report = (new MembershipSalesReportService($repository, $saleService))->report(new User, []);

        $this->assertSame(300.0, $report['summary']['gross_final_amount']);
        $this->assertSame(40.0, $report['summary']['refunded_amount']);
        $this->assertSame(180.0, $report['summary']['cancelled_amount']);
        $this->assertSame(100.0, $report['summary']['final_amount']);
        $this->assertSame(1, $report['summary']['cancelled_memberships_count']);
        $this->assertSame('cancelled', $report['sales']->getCollection()->first()['status']);
    }

    public function test_terminated_sale_keeps_the_settled_service_amount(): void
    {
        $sale = $this->sale(23, 100);
        $sale->setRelation('personMemberships', new EloquentCollection([
            new PersonMembership(['status' => 'cancelled']),
        ]));
        $payment = new MembershipPlanPayment(['type' => 'payment', 'status' => 'paid', 'amount' => 50]);
        $refund = new MembershipPlanPayment(['type' => 'refund', 'status' => 'paid', 'amount' => 20]);
        $refund->setRelation('hdmOperations', new EloquentCollection([
            new HdmOperation([
                'transaction_type' => 'membership_termination',
                'status' => 'success',
                'request' => ['service_amount' => 30],
            ]),
        ]));
        $sale->setRelation('payments', new EloquentCollection([$payment, $refund]));

        /** @var MembershipSalesReportRepositoryInterface&MockObject $repository */
        $repository = $this->createMock(MembershipSalesReportRepositoryInterface::class);
        $repository->method('salesForExport')->willReturn(collect([$sale]));

        /** @var MembershipSaleService&MockObject $saleService */
        $saleService = $this->createMock(MembershipSaleService::class);
        $saleService->method('availableRefundAmount')->willReturn(0.0);

        $export = (new MembershipSalesReportService($repository, $saleService))->exportData(new User, []);
        $row = $export['rows']->first();

        $this->assertSame(100.0, $row['gross_final_price']);
        $this->assertSame(20.0, $row['refunded_amount']);
        $this->assertSame(50.0, $row['cancelled_amount']);
        $this->assertSame(30.0, $row['final_price']);
        $summary = collect($export['summary']['rows'])->pluck('value', 'label');
        $this->assertSame(100.0, $summary[__('backend_messages.gross_final_amount')]);
        $this->assertSame(20.0, $summary[__('backend_messages.refunded_amount')]);
        $this->assertSame(50.0, $summary[__('backend_messages.cancelled_amount')]);
        $this->assertSame(30.0, $summary[__('backend_messages.net_final_amount')]);
    }

    private function sale(int $id, float $finalPrice): MembershipSale
    {
        $sale = new MembershipSale([
            'total_price' => $finalPrice,
            'discount_amount' => 0,
            'final_price' => $finalPrice,
            'payment_status' => 'paid',
            'sold_at' => now(),
        ]);
        $sale->id = $id;
        $sale->setRelation('person', null);
        $sale->setRelation('membershipPlan', null);
        $sale->setRelation('personMemberships', new EloquentCollection);
        $sale->setRelation('discounts', new EloquentCollection);
        $sale->setRelation('payments', new EloquentCollection);

        return $sale;
    }
}
