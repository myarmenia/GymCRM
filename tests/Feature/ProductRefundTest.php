<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\InventoryProduct;
use App\Models\MeasurementUnit;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\Purchase\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductRefundTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_and_full_refunds_restore_stock_and_create_expenses(): void
    {

        [$purchase, $purchaseItem, $cash, $stock, $user, $gym] = $this->saleFixture();
        $service = app(PurchaseService::class);

        $firstRefund = $service->refund($purchase, [
            'payment_method_id' => $cash->id,
            'items' => [['purchase_item_id' => $purchaseItem->id, 'quantity' => 1]],
            'reason' => 'Partial return',
        ], $gym->id, $user->id);

        $this->assertSame('100.00', $firstRefund->amount);
        $this->assertSame('completed', $purchase->fresh()->status);
        $this->assertSame(11.0, (float) $stock->fresh()->quantity);
        $this->assertDatabaseHas('financial_transactions', [
            'source_type' => 'purchase_refund',
            'source_id' => $firstRefund->id,
            'direction' => 'expense',
            'amount' => 100,
        ]);

        $secondRefund = $service->refund($purchase->fresh(), [
            'payment_method_id' => $cash->id,
            'items' => [['purchase_item_id' => $purchaseItem->id, 'quantity' => 1]],
            'reason' => 'Final return',
        ], $gym->id, $user->id);

        $this->assertSame('100.00', $secondRefund->amount);
        $this->assertSame('refunded', $purchase->fresh()->status);
        $this->assertSame(12.0, (float) $stock->fresh()->quantity);
        $this->assertDatabaseCount('purchase_refunds', 2);
        $this->assertDatabaseCount('purchase_refund_items', 2);
        $this->assertSame(200.0, (float) DB::table('financial_transactions')
            ->where('source_type', 'purchase_refund')
            ->sum('amount'));
    }

    public function test_refund_cannot_exceed_the_remaining_sold_quantity(): void
    {

        [$purchase, $purchaseItem, $cash, , $user, $gym] = $this->saleFixture();

        try {
            app(PurchaseService::class)->refund($purchase, [
                'payment_method_id' => $cash->id,
                'items' => [['purchase_item_id' => $purchaseItem->id, 'quantity' => 3]],
            ], $gym->id, $user->id);

            $this->fail('Expected the refund quantity validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }

        $this->assertDatabaseCount('purchase_refunds', 0);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_sev_synced_sale_must_be_refunded_in_the_source_system(): void
    {
        [$purchase, $purchaseItem, $cash, , $user, $gym] = $this->saleFixture();
        $purchase->update(['sync_origin' => 'sev']);

        try {
            app(PurchaseService::class)->refund($purchase, [
                'payment_method_id' => $cash->id,
                'items' => [['purchase_item_id' => $purchaseItem->id, 'quantity' => 1]],
            ], $gym->id, $user->id);

            $this->fail('Expected synced sale refund validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('refund', $exception->errors());
        }

        $this->assertDatabaseCount('purchase_refunds', 0);
    }

    private function saleFixture(): array
    {
        $gym = Gym::query()->create(['name' => 'Main gym']);
        $user = User::factory()->create(['gym_id' => $gym->id]);
        $cash = PaymentMethod::query()->create(['slug' => 'cash']);
        $warehouse = Warehouse::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Cashier',
            'type' => 'cashier',
        ]);
        $categoryId = DB::table('inventory_categories')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'version' => 1,
            'gym_id' => $gym->id,
            'parent_id' => null,
            'sort_order' => 0,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $unit = MeasurementUnit::query()->create([
            'code' => 'pcs',
            'name' => 'Pieces',
            'type' => 'count',
            'status' => true,
        ]);
        $product = InventoryProduct::query()->create([
            'gym_id' => $gym->id,
            'category_id' => $categoryId,
            'sub_category_id' => $categoryId,
            'measurement_unit_id' => $unit->id,
            'sku' => 'SKU-REFUND',
            'default_purchase_price' => 50,
            'default_sale_price' => 100,
            'min_stock_alert' => 0,
            'status' => true,
        ]);
        $stock = WarehouseStock::query()->create([
            'gym_id' => $gym->id,
            'warehouse_id' => $warehouse->id,
            'inventory_product_id' => $product->id,
            'quantity' => 10,
            'reserved_quantity' => 0,
            'average_cost' => 50,
        ]);
        $purchase = Purchase::query()->create([
            'user_id' => $user->id,
            'gym_id' => $gym->id,
            'warehouse_id' => $warehouse->id,
            'token' => (string) Str::uuid(),
            'subtotal' => 200,
            'tax' => 0,
            'discount' => 0,
            'discount_percent' => 0,
            'discount_amount' => 0,
            'total' => 200,
            'cash_received' => 200,
            'change_amount' => 0,
            'status' => 'completed',
            'payment_method_id' => $cash->id,
        ]);
        $purchaseItem = PurchaseItem::query()->create([
            'purchase_id' => $purchase->id,
            'purchase_token' => $purchase->token,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100,
            'discount_percent' => 0,
            'discount_amount' => 0,
            'final_price' => 200,
        ]);

        return [$purchase, $purchaseItem, $cash, $stock, $user, $gym];
    }
}
