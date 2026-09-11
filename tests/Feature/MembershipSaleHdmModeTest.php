<?php

namespace Tests\Feature;

use App\Models\HdmCashier;
use App\Models\HdmConfig;
use App\Models\HdmOperation;
use App\Models\MembershipPlanPayment;
use App\Models\User;
use App\Services\Hdm\HdmOperationService;
use App\Services\Hdm\HdmPrepaymentTerminationService;
use App\Services\Hdm\HdmPrintService;
use App\Services\Hdm\HdmReturnService;
use App\Services\MembershipSales\MembershipSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MembershipSaleHdmModeTest extends TestCase
{
    use RefreshDatabase;

    private array $ids;

    private MembershipSaleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        $this->ids = $this->insertSaleDependencies();
        $user = User::findOrFail($this->ids['userId']);
        $user->assignRole(Role::create(['name' => 'owner', 'guard_name' => 'web', 'g_name' => 'owner']));
        $this->actingAs($user);
        $this->service = app(MembershipSaleService::class);
    }

    private function payload(bool $hdm = true, float $amount = 40): array
    {
        return [
            'person_id' => $this->ids['personId'],
            'membership_plan_id' => $this->ids['planId'],
            'start_date' => now()->toDateString(),
            'apply_discount' => true,
            'discount_type' => 'percent',
            'discount_value' => 20,
            'is_hdm' => $hdm,
            'is_partial_payment' => true,
            'is_full_payment' => false,
            'amount' => $amount,
            'payment_method_id' => $this->ids['paymentMethodId'],
            'reminder_scheduled_at' => now()->addDay()->toDateTimeString(),
            'reminder_recipient_ids' => [$this->ids['userId']],
        ];
    }

    public function test_create_preserves_mode_and_discounted_price_with_a_prepayment(): void
    {
        $sale = $this->service->store($this->payload());
        $this->assertTrue($sale->is_hdm);
        $this->assertSame(80.0, (float) $sale->final_price);
        $this->assertSame(40.0, (float) $sale->payments->first()->amount);
        $this->assertTrue($sale->payments->first()->is_hdm);
        $this->assertSame('partial', $sale->payment_status);
    }

    public function test_final_hdm_payment_closes_debt_and_prints_the_remainder(): void
    {
        $this->createHdmDevice();
        $sale = $this->service->store($this->payload());
        $this->markPrintedPrepaymentSuccessful($sale->payments->first(), 'PREPAYMENT', 10);

        $finalPayment = $this->service->storePayment($sale->id, [
            'is_full_payment' => true,
            'is_partial_payment' => false,
            'payment_method_id' => $this->ids['paymentMethodId'],
        ]);

        $this->assertSame(40.0, (float) $finalPayment->amount);
        $this->assertSame('paid', $sale->fresh()->payment_status);
        $this->assertSame(0.0, $this->service->paymentPageData($sale->id)['debtAmount']);

        $this->assertDatabaseHas('financial_transactions', [
            'source_type' => 'membership_plan_payment',
            'source_id' => $finalPayment->id,
            'amount' => 40,
        ]);

        $printResult = app(HdmPrintService::class)->preparePrintData($finalPayment);
        $this->assertTrue($printResult['success'], $printResult['message'] ?? '');
        $this->assertSame(2, $printResult['data']['receipt']['mode']);
        $this->assertSame(40.0, (float) $printResult['data']['receipt']['paidAmount']);
        $this->assertSame(40.0, (float) $printResult['data']['receipt']['prePaymentAmount']);
    }

    public function test_full_final_hdm_refund_returns_the_public_amount(): void
    {
        $this->createHdmDevice();
        $sale = $this->service->store($this->payload());
        $this->markPrintedPrepaymentSuccessful($sale->payments->first(), 'PREPAYMENT', 10);
        $finalPayment = $this->service->storePayment($sale->id, [
            'is_full_payment' => true,
            'is_partial_payment' => false,
            'payment_method_id' => $this->ids['paymentMethodId'],
        ]);
        $finalPrint = app(HdmPrintService::class)->preparePrintData($finalPayment);
        $this->markPreparedStepSuccessful($finalPrint, 'FINAL', 11);

        $refund = $this->service->storeRefund($sale->id, [
            'parent_payment_id' => $finalPayment->id,
            'is_full_refund' => true,
            'is_partial_refund' => false,
        ]);

        $this->assertSame(80.0, (float) $refund->amount);
        $returnPrint = app(HdmReturnService::class)->prepareReturnData($refund);
        $this->assertTrue($returnPrint['success'], $returnPrint['message'] ?? '');
        $this->assertSame(80.0, (float) $returnPrint['data']['entity']['total']);
        $this->markPreparedStepSuccessful($returnPrint, 'FINAL', 12);

        $this->assertDatabaseHas('financial_transactions', [
            'source_type' => 'membership_plan_payment',
            'source_id' => $refund->id,
            'direction' => 'expense',
            'amount' => 80,
        ]);
    }

    public function test_payments_page_keeps_hdm_mode_and_returns_remaining_debt(): void
    {
        $this->withoutVite();
        $sale = $this->service->store($this->payload());
        $this->get(route('membership_sale.payments', ['locale' => 'hy', 'id' => $sale->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('MembershipSales/Payments')
                ->where('membershipSale.is_hdm', true)
                ->where('debtAmount', 40));
    }

    public function test_partial_hdm_payment_prepares_a_prepayment_receipt(): void
    {
        $device = HdmConfig::create([
            'gym_id' => $this->ids['gymId'], 'name' => 'Reception',
            'ip' => '127.0.0.1', 'port' => 8080, 'password' => 'test', 'status' => true,
        ]);
        HdmCashier::create([
            'gym_id' => $this->ids['gymId'], 'user_id' => $this->ids['userId'],
            'hdm_config_id' => $device->id, 'name' => 'Test',
            'login' => '1', 'pin' => '1234', 'status' => true,
        ]);
        $sale = $this->service->store($this->payload());
        $result = app(HdmPrintService::class)->preparePrintData($sale->payments->first());
        $this->assertTrue($result['success'], $result['message'] ?? '');
        $this->assertTrue($result['need_print']);
        $this->assertSame(3, $result['data']['receipt']['mode']);
        $this->assertSame(40.0, (float) $sale->payments->first()->amount);
    }

    public function test_cashier_can_prepare_missing_hdm_receipt_after_device_is_enabled(): void
    {
        $sale = $this->service->store($this->payload());
        $payment = $sale->payments->first();
        $missingDeviceResult = app(HdmPrintService::class)->preparePrintData($payment);
        $this->assertFalse($missingDeviceResult['success']);
        $this->assertDatabaseCount('hdm_operations', 0);

        $this->createHdmDevice();
        $pagePayment = $this->service->paymentPageData($sale->id)['membershipSale']->payments->first();
        $this->assertTrue($pagePayment->can_retry_hdm_receipt);
        $this->assertNull($pagePayment->hdm_print_status);

        $response = $this->postJson(route('membership_sale.payments.hdm.retry', [
            'locale' => 'hy',
            'id' => $sale->id,
            'payment' => $payment->id,
        ]));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('need_print', true)
            ->assertJsonPath('print_data.receipt.mode', 3);
        $this->assertDatabaseCount('hdm_operations', 1);

        $this->postJson(route('membership_sale.payments.hdm.retry', [
            'locale' => 'hy',
            'id' => $sale->id,
            'payment' => $payment->id,
        ]))->assertUnprocessable();
        $this->assertDatabaseCount('hdm_operations', 1);
    }

    public function test_failed_hdm_receipt_retry_reuses_the_same_operation(): void
    {
        $this->createHdmDevice();
        $sale = $this->service->store($this->payload());
        $payment = $sale->payments->first();
        $prepared = app(HdmPrintService::class)->preparePrintData($payment);
        $operationId = $prepared['data']['operation_id'];
        app(HdmOperationService::class)->updateStatus([
            'operation_id' => $operationId,
            'status' => 'failed',
            'response' => ['error' => 'DEVICE_OFFLINE'],
        ]);

        $pagePayment = $this->service->paymentPageData($sale->id)['membershipSale']->payments->first();
        $this->assertTrue($pagePayment->can_retry_hdm_receipt);
        $this->assertSame('failed', $pagePayment->hdm_print_status);

        $response = $this->postJson(route('membership_sale.payments.hdm.retry', [
            'locale' => 'hy',
            'id' => $sale->id,
            'payment' => $payment->id,
        ]));

        $response->assertOk()
            ->assertJsonPath('print_data.operation_id', $operationId)
            ->assertJsonPath('print_data.receipt.mode', 3);
        $this->assertDatabaseCount('hdm_operations', 1);
    }

    public function test_invalid_hdm_prepayments_are_rejected_before_any_sale_or_payment_is_saved(): void
    {
        foreach ([0, 80, 90, 100, 200, 79.999] as $amount) {
            try {
                $this->service->store($this->payload(true, $amount));
                $this->fail('Expected amount validation error for '.$amount);
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('amount', $error->errors());
            }
        }
        $this->assertDatabaseCount('membership_sales', 1); // The fixture sale only.
        $this->assertDatabaseCount('membership_plan_payments', 0);
        $this->assertDatabaseCount('person_memberships', 0);
    }

    public function test_non_hdm_sale_and_later_payment_stay_non_hdm(): void
    {
        $sale = $this->service->store($this->payload(false));
        $this->assertFalse($sale->is_hdm);
        $payment = $this->service->storePayment($sale->id, [
            'is_full_payment' => true,
            'payment_method_id' => $this->ids['paymentMethodId'],
        ]);
        $this->assertFalse($payment->is_hdm);
        $this->assertSame(40.0, (float) $payment->amount);
    }

    public function test_additional_hdm_payment_inherits_mode_when_checkbox_is_omitted(): void
    {
        $sale = $this->service->store($this->payload());
        $payment = $this->service->storePayment($sale->id, [
            'is_full_payment' => true,
            'payment_method_id' => $this->ids['paymentMethodId'],
        ]);
        $this->assertTrue($payment->is_hdm);
        $this->assertSame('paid', $sale->fresh()->payment_status);
    }

    public function test_additional_partial_hdm_payment_cannot_equal_the_remaining_debt(): void
    {
        $sale = $this->service->store($this->payload());

        $this->postJson(route('membership_sale.payments.store', [
            'locale' => 'hy',
            'id' => $sale->id,
        ]), [
            'is_partial_payment' => true,
            'is_full_payment' => false,
            'amount' => 40,
            'payment_method_id' => $this->ids['paymentMethodId'],
            'is_hdm' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('amount');

        $this->assertCount(1, $sale->fresh()->payments);
        $this->assertSame(40.0, $this->service->paymentPageData($sale->id)['debtAmount']);
    }

    public function test_additional_partial_hdm_payment_below_the_remaining_debt_is_saved(): void
    {
        $sale = $this->service->store($this->payload());
        $payment = $this->service->storePayment($sale->id, [
            'is_partial_payment' => true,
            'is_full_payment' => false,
            'amount' => 39.99,
            'payment_method_id' => $this->ids['paymentMethodId'],
            'is_hdm' => true,
        ]);

        $this->assertSame(39.99, (float) $payment->amount);
        $this->assertTrue($payment->is_hdm);
        $this->assertSame('partial', $sale->fresh()->payment_status);
        $this->assertEqualsWithDelta(0.01, $this->service->paymentPageData($sale->id)['debtAmount'], 0.001);
    }

    public function test_payment_cannot_switch_mode_in_either_direction(): void
    {
        foreach ([true, false] as $mode) {
            DB::table('membership_sales')->where('id', $this->ids['saleId'])->update(['is_hdm' => $mode]);
            try {
                $this->service->storePayment($this->ids['saleId'], [
                    'is_hdm' => ! $mode,
                    'is_full_payment' => true,
                    'payment_method_id' => $this->ids['paymentMethodId'],
                ]);
                $this->fail('Expected immutable mode validation');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('is_hdm', $error->errors());
            }
        }
        $this->assertDatabaseCount('membership_plan_payments', 0);
    }

    public function test_sale_mode_cannot_be_changed_by_model_update(): void
    {
        $sale = $this->service->store($this->payload());
        try {
            $sale->update(['is_hdm' => false]);
            $this->fail('Expected immutable mode validation');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('is_hdm', $error->errors());
        }
        $this->assertTrue($sale->fresh()->is_hdm);
    }

    public function test_discount_edit_preserves_hdm_mode(): void
    {
        $sale = $this->service->store($this->payload());
        $this->service->update($sale->id, []);
        $this->assertTrue($sale->fresh()->is_hdm);
    }

    public function test_debt_sale_keeps_selected_mode_without_creating_a_payment(): void
    {
        $sale = $this->service->store([...$this->payload(), 'stay_debt' => true, 'payment_method_id' => null]);
        $this->assertTrue($sale->is_hdm);
        $this->assertCount(0, $sale->payments);
    }

    public function test_legacy_sale_with_unknown_mode_cannot_accept_a_new_payment(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->storePayment($this->ids['saleId'], [
            'is_full_payment' => true,
            'payment_method_id' => $this->ids['paymentMethodId'],
        ]);
    }

    public function test_create_endpoint_rejects_equal_discounted_amount(): void
    {
        $this->postJson(route('membership_sale.store', ['locale' => 'hy', 'person' => $this->ids['personId']]), [...$this->payload(true, 80), 'reminder_recipient_ids' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->assertDatabaseCount('membership_plan_payments', 0);
    }

    public function test_full_hdm_payment_is_allowed_and_uses_public_price(): void
    {
        $sale = $this->service->store([
            ...$this->payload(), 'is_partial_payment' => false, 'is_full_payment' => true,
        ]);
        $this->assertTrue($sale->is_hdm);
        $this->assertSame(80.0, (float) $sale->payments->first()->amount);
        $this->assertSame('paid', $sale->payment_status);
        $this->assertDatabaseHas('financial_transactions', [
            'source_type' => 'membership_plan_payment',
            'source_id' => $sale->payments->first()->id,
            'amount' => 80,
        ]);
    }

    public function test_refunds_inherit_the_original_sale_mode(): void
    {
        foreach ([true, false] as $mode) {
            $sale = $this->service->store($this->payload($mode));
            $refund = $this->service->storeRefund($sale->id, [
                'parent_payment_id' => $sale->payments->first()->id,
                'amount' => 10,
                'is_full_refund' => false,
            ]);
            $this->assertSame($mode, $refund->is_hdm);
            $this->assertSame($mode ? 'pending' : 'paid', $refund->status);
            DB::table('person_memberships')->where('membership_sale_id', $sale->id)->delete();
        }
    }

    public function test_non_hdm_partial_payment_uses_the_same_public_price_limit(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->store($this->payload(false, 80));
    }

    public function test_non_hdm_partial_payment_must_be_below_discounted_price(): void
    {
        foreach ([0, 80, 90, 100, 79.999] as $amount) {
            try {
                $this->service->store($this->payload(false, $amount));
                $this->fail('Expected validation error for '.$amount);
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('amount', $error->errors());
            }
        }
        $sale = $this->service->store($this->payload(false, 79.99));
        $this->assertSame(79.99, (float) $sale->payments->first()->amount);
        $this->assertSame('partial', $sale->payment_status);
    }

    public function test_fixed_manual_discount_is_rejected_by_service_and_http(): void
    {
        $payload = [...$this->payload(), 'discount_type' => 'fixed', 'reminder_recipient_ids' => null];
        $this->postJson(route('membership_sale.store', ['locale' => 'hy', 'person' => $this->ids['personId']]), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('discount_type');
        $this->expectException(ValidationException::class);
        $this->service->store($payload);
    }

    public function test_full_non_hdm_payment_uses_discounted_price(): void
    {
        $sale = $this->service->store([
            ...$this->payload(false), 'is_partial_payment' => false, 'is_full_payment' => true,
        ]);
        $this->assertSame(80.0, (float) $sale->payments->first()->amount);
        $this->assertSame('paid', $sale->payment_status);
        $this->assertFalse($sale->is_hdm);
    }

    public function test_unprinted_hdm_payment_blocks_plain_cancellation_and_refund_paths(): void
    {
        $sale = $this->service->store($this->payload(true, 40));

        $pageData = app(HdmPrepaymentTerminationService::class)->pageData($sale);

        $this->assertTrue($pageData['requires_workflow']);
        $this->assertFalse($pageData['can_start']);
        $this->assertNotNull($pageData['reason']);
        $this->assertSame(0.0, $pageData['available_prepayment']);

        try {
            $this->service->cancelMembership($sale->id);
            $this->fail('Expected ordinary cancellation to be blocked.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('membership_sale_id', $error->errors());
        }

        $this->assertNotSame('cancelled', $sale->fresh()->personMemberships->first()->status);
    }

    public function test_returned_final_receipt_cannot_be_terminated_again_through_old_prepayments(): void
    {
        $this->createHdmDevice();
        $sale = $this->service->store($this->payload(true, 40));
        $prepayment = $sale->payments->first();
        $this->markPrintedPrepaymentSuccessful($prepayment, 'prepayment-crn', 401);
        $finalPayment = $this->service->storePayment($sale->id, [
            'is_full_payment' => true,
            'payment_method_id' => $this->ids['paymentMethodId'],
            'is_hdm' => true,
        ]);
        $finalResult = app(HdmPrintService::class)->preparePrintData($finalPayment);
        $this->assertTrue($finalResult['success'], $finalResult['message'] ?? '');
        $this->assertSame(2, $finalResult['data']['receipt']['mode']);
        $this->markPreparedStepSuccessful(['data' => $finalResult['data']], 'final-crn', 402);
        $finalOperation = HdmOperation::query()->findOrFail($finalResult['data']['operation_id']);

        $refund = $sale->payments()->create([
            'amount' => 80,
            'payment_method_id' => $finalPayment->payment_method_id,
            'status' => 'paid',
            'type' => 'refund',
            'is_hdm' => true,
            'parent_payment_id' => $finalPayment->id,
        ]);
        HdmOperation::query()->create([
            'hdm_config_id' => $finalOperation->hdm_config_id,
            'hdm_cashier_id' => $finalOperation->hdm_cashier_id,
            'user_id' => $sale->user_id,
            'operationable_type' => MembershipPlanPayment::class,
            'operationable_id' => $refund->id,
            'transaction_type' => 'refund',
            'status' => 'success',
            'parent_operation_id' => $finalOperation->id,
            'crn' => 'final-crn',
            'rseq' => '403',
        ]);

        $pageData = app(HdmPrepaymentTerminationService::class)->pageData($sale->fresh());

        $this->assertFalse($pageData['requires_workflow']);
        $this->assertFalse($pageData['can_start']);
        $this->assertStringContainsString('վերադարձված', $pageData['reason']);
    }

    public function test_cashier_can_terminate_hdm_prepayment_with_a_manual_refund_amount(): void
    {
        $this->createHdmDevice();
        $sale = $this->service->store($this->payload(true, 40));
        $this->markPrintedPrepaymentSuccessful($sale->payments->first(), 'cash-crn', 101);

        $result = app(HdmPrepaymentTerminationService::class)->start($sale, [
            'refund_amount' => 25,
            'notes' => 'Manual cashier decision',
        ]);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['print_steps']);
        $this->assertSame('service', $result['print_steps'][0]['kind']);
        $this->assertSame(2, $result['print_steps'][0]['data']['receipt']['mode']);
        $this->assertSame(15.0, (float) $result['print_steps'][0]['data']['receipt']['prePaymentAmount']);
        $this->assertSame(15.0, (float) $result['print_steps'][0]['data']['receipt']['items'][0]['price']);
        $this->assertSame('refund', $result['print_steps'][1]['kind']);
        $this->assertSame('cash-crn', $result['print_steps'][1]['data']['receipt']['crn']);
        $this->assertSame(101, $result['print_steps'][1]['data']['receipt']['returnTicketId']);
        $this->assertSame(25.0, (float) $result['print_steps'][1]['data']['receipt']['cashAmountForReturn']);
        $this->assertSame(0.0, (float) $result['print_steps'][1]['data']['receipt']['cardAmountForReturn']);
        $this->assertSame(0.0, (float) $result['print_steps'][1]['data']['receipt']['prePaymentAmountForReturn']);
        $this->assertSame('cancelled', $sale->fresh()->personMemberships->first()->status);

        $this->markPreparedStepSuccessful($result['print_steps'][0], 'service-crn', 102);
        $this->markPreparedStepSuccessful($result['print_steps'][1], 'return-crn', 103);

        $refund = MembershipPlanPayment::query()->where('type', 'refund')->firstOrFail();
        $workflow = HdmOperation::query()
            ->where('transaction_type', HdmPrepaymentTerminationService::WORKFLOW_TRANSACTION_TYPE)
            ->firstOrFail();
        $this->assertSame('paid', $refund->fresh()->status);
        $this->assertSame('success', $workflow->fresh()->status);
        $this->assertSame(0.0, $this->service->availableRefundAmount($sale->fresh()));
    }

    public function test_termination_returns_each_prepayment_through_its_original_payment_method(): void
    {
        $this->createHdmDevice();
        $sale = $this->service->store($this->payload(true, 30));
        $cashPayment = $sale->payments->first();
        $this->markPrintedPrepaymentSuccessful($cashPayment, 'cash-crn', 201);

        $cardMethodId = DB::table('payment_methods')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'version' => 1,
            'slug' => 'card',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cardPayment = $this->service->storePayment($sale->id, [
            'is_partial_payment' => true,
            'is_full_payment' => false,
            'amount' => 20,
            'payment_method_id' => $cardMethodId,
            'is_hdm' => true,
        ]);
        $this->markPrintedPrepaymentSuccessful($cardPayment, 'card-crn', 202);

        $result = app(HdmPrepaymentTerminationService::class)->start($sale->fresh(), [
            'refund_amount' => 40,
        ]);

        $this->assertCount(3, $result['print_steps']);
        $this->assertSame(10.0, (float) $result['print_steps'][0]['data']['receipt']['prePaymentAmount']);

        $cashReturn = $result['print_steps'][1]['data']['receipt'];
        $cardReturn = $result['print_steps'][2]['data']['receipt'];
        $this->assertSame('cash-crn', $cashReturn['crn']);
        $this->assertSame(20.0, (float) $cashReturn['cashAmountForReturn']);
        $this->assertSame(0.0, (float) $cashReturn['cardAmountForReturn']);
        $this->assertSame('card-crn', $cardReturn['crn']);
        $this->assertArrayNotHasKey('cashAmountForReturn', $cardReturn);
        $this->assertArrayNotHasKey('cardAmountForReturn', $cardReturn);

        $refunds = MembershipPlanPayment::query()->where('type', 'refund')->orderBy('id')->get();
        $this->assertCount(2, $refunds);
        $this->assertSame($this->ids['paymentMethodId'], $refunds[0]->payment_method_id);
        $this->assertSame($cardMethodId, $refunds[1]->payment_method_id);
        $this->assertSame(20.0, (float) $refunds[0]->amount);
        $this->assertSame(20.0, (float) $refunds[1]->amount);
    }

    public function test_failed_termination_step_can_be_resumed_without_recreating_the_service_receipt(): void
    {
        $this->createHdmDevice();
        $sale = $this->service->store($this->payload(true, 40));
        $this->markPrintedPrepaymentSuccessful($sale->payments->first(), 'cash-crn', 301);
        $service = app(HdmPrepaymentTerminationService::class);
        $result = $service->start($sale, ['refund_amount' => 25]);

        $this->markPreparedStepSuccessful($result['print_steps'][0], 'service-crn', 302);
        $refundOperationId = $result['print_steps'][1]['data']['operation_id'];
        app(HdmOperationService::class)->updateStatus([
            'operation_id' => $refundOperationId,
            'status' => 'failed',
            'response' => ['error' => 'PRINTER_ERROR'],
        ]);

        $refund = MembershipPlanPayment::query()->where('type', 'refund')->firstOrFail();
        $this->assertSame('pending', $refund->fresh()->status);

        $resumed = $service->resume($sale->fresh());
        $this->assertCount(1, $resumed['print_steps']);
        $this->assertSame('refund', $resumed['print_steps'][0]['kind']);
        $this->assertSame($refundOperationId, $resumed['print_steps'][0]['data']['operation_id']);
        $this->assertSame(1, HdmOperation::query()
            ->where('transaction_type', 'sale')
            ->where('operationable_id', $refund->id)
            ->count());
    }

    private function createHdmDevice(): void
    {
        $device = HdmConfig::create([
            'gym_id' => $this->ids['gymId'],
            'name' => 'Reception',
            'ip' => '127.0.0.1',
            'port' => 8080,
            'password' => 'test',
            'status' => true,
        ]);
        HdmCashier::create([
            'gym_id' => $this->ids['gymId'],
            'user_id' => $this->ids['userId'],
            'hdm_config_id' => $device->id,
            'name' => 'Test',
            'login' => '1',
            'pin' => '1234',
            'status' => true,
        ]);
    }

    private function markPrintedPrepaymentSuccessful(
        MembershipPlanPayment $payment,
        string $crn,
        int $rseq,
    ): void {
        $result = app(HdmPrintService::class)->preparePrintData($payment);
        $this->assertTrue($result['success'], $result['message'] ?? '');
        $this->assertSame(3, $result['data']['receipt']['mode']);
        $this->markPreparedStepSuccessful(['data' => $result['data']], $crn, $rseq);
    }

    /** @param array{data: array<string, mixed>} $step */
    private function markPreparedStepSuccessful(array $step, string $crn, int $rseq): void
    {
        $result = app(HdmOperationService::class)->updateStatus([
            'operation_id' => $step['data']['operation_id'],
            'status' => 'success',
            'response' => ['success' => true],
            'crn' => $crn,
            'rseq' => $rseq,
        ]);
        $this->assertTrue($result['success']);
    }

    /** @return array{gym_id: int, user_id: int, sale_id: int, payment_method_id: int} */
    private function insertSaleDependencies(): array
    {
        $now = now();
        $gymId = DB::table('gyms')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'version' => 1,
            'name' => 'Gym',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $userId = DB::table('users')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'version' => 1,
            'gym_id' => $gymId,
            'name' => 'Seller',
            'surname' => 'User',
            'email' => Str::uuid().'@example.com',
            'password' => 'secret',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $personId = DB::table('people')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'version' => 1,
            'name' => 'Client',
            'email' => Str::uuid().'@example.com',
            'password' => 'secret',
            'phone' => '+37400000000',
            'type' => 'visitor',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $categoryId = DB::table('membership_categories')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'version' => 1,
            'gym_id' => $gymId,
            'active' => true,
            'slug' => (string) Str::uuid(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $planId = DB::table('membership_plans')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'version' => 1,
            'membership_category_id' => $categoryId,
            'gym_id' => $gymId,
            'price' => 100,
            'duration_type' => 'month',
            'duration_value' => 1,
            'visits_limit' => null,
            'guest_limit' => 0,
            'freeze_limit' => 0,
            'price_type' => 'fixed',
            'price_value' => 0,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $paymentMethodId = DB::table('payment_methods')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'version' => 1,
            'slug' => 'cash',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $saleId = DB::table('membership_sales')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'version' => 1,
            'user_id' => $userId,
            'person_id' => $personId,
            'gym_id' => $gymId,
            'membership_plan_id' => $planId,
            'total_price' => 100,
            'discount_amount' => 0,
            'final_price' => 100,
            'payment_status' => 'paid',
            'discount_membership_amount' => 0,
            'sold_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return compact('gymId', 'userId', 'saleId', 'paymentMethodId', 'personId', 'planId') + [
            'gym_id' => $gymId,
            'user_id' => $userId,
            'sale_id' => $saleId,
            'payment_method_id' => $paymentMethodId,
        ];
    }
}
