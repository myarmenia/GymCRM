<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\InventoryCategory;
use App\Models\InventoryProduct;
use App\Models\MeasurementUnit;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSalePriceByWarehouseTest extends TestCase
{
    use RefreshDatabase;

    public function test_main_warehouse_does_not_require_or_store_a_sale_price(): void
    {
        [$payload, $gym] = $this->productPayload();
        $warehouse = Warehouse::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Main',
            'type' => 'main',
        ]);

        $this->post(route('products.store', ['locale' => 'en']), [
            ...$payload,
            'warehouse_id' => $warehouse->id,
        ])->assertRedirect();

        $this->assertNull(InventoryProduct::query()->sole()->default_sale_price);

        $this->post(route('products.store', ['locale' => 'en']), [
            ...$payload,
            'sku' => 'MAIN-2',
            'warehouse_id' => $warehouse->id,
            'default_sale_price' => 999,
        ])->assertRedirect();

        $this->assertNull(InventoryProduct::query()->where('sku', 'MAIN-2')->sole()->default_sale_price);
    }

    public function test_sales_warehouse_requires_sale_price_and_checks_purchase_price(): void
    {
        [$payload, $gym] = $this->productPayload();
        $warehouse = Warehouse::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Sales',
            'type' => 'cashier',
        ]);
        $payload['warehouse_id'] = $warehouse->id;

        $this->post(route('products.store', ['locale' => 'en']), $payload)
            ->assertSessionHasErrors('default_sale_price');

        $this->post(route('products.store', ['locale' => 'en']), [
            ...$payload,
            'default_sale_price' => 5,
        ])->assertSessionHasErrors('default_purchase_price');

        $this->post(route('products.store', ['locale' => 'en']), [
            ...$payload,
            'default_sale_price' => 15,
        ])->assertRedirect();

        $this->assertEquals(15, InventoryProduct::query()->sole()->default_sale_price);
    }

    private function productPayload(): array
    {
        $gym = Gym::query()->create(['name' => 'Product gym']);
        $user = User::factory()->create(['gym_id' => $gym->id, 'active' => true]);
        $user->assignRole(Role::query()->firstOrCreate([
            'name' => 'sales_manager',
            'guard_name' => 'web',
            'g_name' => 'sales_manager',
        ]));
        $this->actingAs($user);

        $category = InventoryCategory::query()->create([
            'gym_id' => $gym->id,
            'status' => true,
        ]);
        $subcategory = InventoryCategory::query()->create([
            'gym_id' => $gym->id,
            'parent_id' => $category->id,
            'status' => true,
        ]);
        $unit = MeasurementUnit::query()->create([
            'code' => 'pcs',
            'name' => 'Pieces',
            'type' => 'count',
            'status' => true,
        ]);

        return [[
            'category_id' => $category->id,
            'sub_category_id' => $subcategory->id,
            'measurement_unit_id' => $unit->id,
            'name' => ['en' => 'Test product'],
            'sku' => 'MAIN-1',
            'default_purchase_price' => 10,
            'min_stock_alert' => 0,
            'status' => true,
            'quantity' => 5,
            'reserved_quantity' => 0,
        ], $gym];
    }
}
