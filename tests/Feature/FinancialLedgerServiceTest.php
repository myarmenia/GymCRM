<?php

namespace Tests\Feature;

use App\Models\FinancialCategory;
use App\Models\Gym;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\User;
use App\Services\Finance\FinancialLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FinancialLedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_automatic_source_is_idempotent_and_updates_summary(): void
    {
        $gym = Gym::query()->create(['name' => 'Main gym']);
        $cash = PaymentMethod::query()->create(['slug' => 'cash']);
        $purchase = Purchase::query()->create([
            'gym_id' => $gym->id,
            'token' => (string) Str::uuid(),
            'subtotal' => 12500,
            'tax' => 0,
            'discount' => 0,
            'total' => 12500,
            'status' => 'completed',
            'payment_method_id' => $cash->id,
        ]);

        $service = app(FinancialLedgerService::class);
        $service->recordProductSale($purchase);
        $service->recordProductSale($purchase);

        $this->assertDatabaseCount('financial_transactions', 1);

        $actor = $this->owner($gym);
        $summary = $service->pageData($actor, ['gym_id' => $gym->id])['summary'];

        $this->assertSame(12500.0, $summary['balance']);
        $this->assertSame(12500.0, $summary['cash_balance']);
        $this->assertSame(12500.0, $summary['income']);
        $this->assertSame(0.0, $summary['expense']);
    }

    public function test_manual_transaction_can_be_reversed_without_losing_history(): void
    {
        $gym = Gym::query()->create(['name' => 'Main gym']);
        $cash = PaymentMethod::query()->create(['slug' => 'cash']);
        $actor = $this->owner($gym);
        $service = app(FinancialLedgerService::class);

        $transaction = $service->createManual($actor, [
            'gym_id' => $gym->id,
            'direction' => 'income',
            'category_id' => FinancialCategory::query()->where('code', 'manual_income')->value('id'),
            'amount' => 5000,
            'payment_method_id' => $cash->id,
            'description' => 'Opening adjustment',
        ]);

        $reversal = $service->reverse($actor, $transaction, 'Incorrect entry');

        $this->assertDatabaseCount('financial_transactions', 2);
        $this->assertSame('expense', $reversal->direction);
        $this->assertSame($transaction->id, $reversal->reversal_of_id);
        $this->assertSame('reversed', $transaction->fresh()->status);
        $this->assertSame(0.0, $service->pageData($actor, ['gym_id' => $gym->id])['summary']['balance']);
    }

    public function test_authenticated_owner_can_open_the_finance_page(): void
    {
        $gym = Gym::query()->create(['name' => 'Main gym']);
        PaymentMethod::query()->create(['slug' => 'cash']);
        $actor = $this->owner($gym);

        $this->actingAs($actor)
            ->get('/hy/finance')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Finance/Index')
                ->has('transactions')
                ->has('summary')
                ->has('paymentMethods')
                ->has('creators'));
    }

    public function test_guest_is_redirected_to_the_localized_login_page(): void
    {
        $this->get('/hy/finance')->assertRedirect('/hy/login');
    }

    public function test_authenticated_owner_can_print_and_export_the_filtered_finance_report(): void
    {
        $gym = Gym::query()->create(['name' => 'Main gym']);
        PaymentMethod::query()->create(['slug' => 'cash']);
        $actor = $this->owner($gym);

        $this->actingAs($actor)
            ->get("/hy/finance/print?gym_id={$gym->id}&direction=income")
            ->assertOk()
            ->assertSee('Դրամարկղ')
            ->assertSee('Մուտք');

        $this->actingAs($actor)
            ->get("/hy/finance/export?gym_id={$gym->id}&direction=income")
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');
    }

    protected function owner(Gym $gym): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'owner', 'guard_name' => 'web'],
            ['g_name' => 'owner'],
        );
        $user = User::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Owner',
            'surname' => 'User',
            'email' => Str::uuid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
