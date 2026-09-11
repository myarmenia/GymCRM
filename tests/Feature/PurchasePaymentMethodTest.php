<?php

namespace Tests\Feature;

use App\Interfaces\Purchase\PurchaseInterface;
use App\Models\CardType;
use App\Models\Gym;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\Warehouse;
use App\Services\Purchase\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchasePaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchases_use_the_shared_payment_method_relation(): void
    {
        $gym = Gym::query()->create(['name' => 'Main gym']);
        $cash = PaymentMethod::query()->create(['slug' => 'cash']);

        $purchase = Purchase::query()->create([
            'gym_id' => $gym->id,
            'token' => (string) Str::uuid(),
            'subtotal' => 1000,
            'tax' => 0,
            'discount' => 0,
            'total' => 1000,
            'status' => 'completed',
            'payment_method_id' => $cash->id,
        ]);

        $this->assertTrue(Schema::hasColumn('purchases', 'payment_method_id'));
        $this->assertFalse(Schema::hasColumn('purchases', 'payment_method'));
        $this->assertSame($cash->id, $purchase->paymentMethod->id);
        $this->assertSame('cash', $purchase->paymentMethod->slug);
    }

    public function test_purchase_history_filters_by_shared_payment_method_id(): void
    {
        $gym = Gym::query()->create(['name' => 'Main gym']);
        $cash = PaymentMethod::query()->create(['slug' => 'cash']);
        $card = PaymentMethod::query()->create(['slug' => 'card']);

        $cashPurchase = $this->createPurchase($gym, $cash, 1000);
        $this->createPurchase($gym, $card, 2000);

        $purchases = app(PurchaseInterface::class)->paginateHistory(
            gymId: $gym->id,
            locale: 'hy',
            paymentMethodId: $cash->id,
        );

        $this->assertCount(1, $purchases);
        $this->assertSame($cashPurchase->id, $purchases->first()->id);
        $this->assertTrue($purchases->first()->relationLoaded('paymentMethod'));
    }

    public function test_card_type_is_required_when_the_payment_method_has_card_types(): void
    {
        $gym = Gym::query()->create(['name' => 'Main gym']);
        Warehouse::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Cashier',
            'type' => 'cashier',
        ]);

        $card = PaymentMethod::query()->create(['slug' => 'card']);
        $visa = CardType::query()->create(['name' => 'Visa']);
        $card->cardTypes()->attach($visa);

        try {
            app(PurchaseService::class)->sell([
                'payment_method_id' => $card->id,
                'discount_percent' => 0,
                'cash_received' => 0,
                'items' => [],
            ], $gym->id, 1);

            $this->fail('Expected card type validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('card_type_id', $exception->errors());
        }
    }

    public function test_purchase_keeps_the_selected_card_type(): void
    {
        $gym = Gym::query()->create(['name' => 'Main gym']);
        $card = PaymentMethod::query()->create(['slug' => 'card']);
        $visa = CardType::query()->create(['name' => 'Visa']);
        $card->cardTypes()->attach($visa);

        $purchase = Purchase::query()->create([
            'gym_id' => $gym->id,
            'token' => (string) Str::uuid(),
            'subtotal' => 1000,
            'tax' => 0,
            'discount' => 0,
            'total' => 1000,
            'status' => 'completed',
            'payment_method_id' => $card->id,
            'card_type_id' => $visa->id,
        ]);

        $this->assertSame($visa->id, $purchase->cardType->id);
        $this->assertSame('Visa', $purchase->cardType->name);
    }

    public function test_migration_maps_legacy_online_purchases_to_transfer(): void
    {
        $gym = Gym::query()->create(['name' => 'Main gym']);
        PaymentMethod::query()->create(['slug' => 'cash']);
        PaymentMethod::query()->create(['slug' => 'card']);
        $transfer = PaymentMethod::query()->create(['slug' => 'transfer']);

        $migration = require database_path(
            'migrations/2026_07_28_000001_replace_purchase_payment_method_with_foreign_key.php'
        );

        $migration->down();

        $purchaseId = DB::table('purchases')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'version' => 1,
            'gym_id' => $gym->id,
            'token' => (string) Str::uuid(),
            'subtotal' => 3000,
            'tax' => 0,
            'discount' => 0,
            'total' => 3000,
            'status' => 'completed',
            'payment_method' => 'online',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();

        $this->assertDatabaseHas('purchases', [
            'id' => $purchaseId,
            'payment_method_id' => $transfer->id,
        ]);
        $this->assertFalse(Schema::hasColumn('purchases', 'payment_method'));
    }

    protected function createPurchase(Gym $gym, PaymentMethod $paymentMethod, float $total): Purchase
    {
        return Purchase::query()->create([
            'gym_id' => $gym->id,
            'token' => (string) Str::uuid(),
            'subtotal' => $total,
            'tax' => 0,
            'discount' => 0,
            'total' => $total,
            'status' => 'completed',
            'payment_method_id' => $paymentMethod->id,
        ]);
    }
}
