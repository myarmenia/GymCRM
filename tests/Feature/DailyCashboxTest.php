<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureGymSubscriptionIsActive;
use App\Models\FinancialTransaction;
use App\Models\Gym;
use App\Models\MembershipCategory;
use App\Models\MembershipPlan;
use App\Models\MembershipPlanPayment;
use App\Models\MembershipSale;
use App\Models\PaymentMethod;
use App\Models\Person;
use App\Models\User;
use App\Services\Finance\DailyCashboxService;
use App\Services\Finance\FinancialLedgerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DailyCashboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        $this->withoutMiddleware(EnsureGymSubscriptionIsActive::class);
        config(['app.timezone' => 'Asia/Yerevan']);
        $this->travelTo(Carbon::parse('2026-11-26 00:30:00', 'Asia/Yerevan'));
    }

    public static function cashboxRoles(): array
    {
        return [['sales_manager'], ['manager']];
    }

    #[DataProvider('cashboxRoles')]
    public function test_cashbox_shows_only_own_today_receipts_and_sales_without_exposing_debt(string $role): void
    {
        $gym = Gym::query()->create(['name' => 'Main gym']);
        $actor = $this->employee($gym, $role);
        $other = $this->employee($gym, $role);
        $cash = PaymentMethod::query()->create(['slug' => 'cash']);
        $card = PaymentMethod::query()->create(['slug' => 'card']);
        $cash->translations()->create(['locale' => 'en', 'name' => 'Cash']);
        $card->translations()->create(['locale' => 'en', 'name' => 'Card']);

        $oldSale = $this->sale($other, '2026-11-20 12:00:00');
        $this->payment($oldSale, $actor, $cash, 10000, '2026-11-26 00:00:00');
        $this->payment($oldSale, $actor, $card, 3000, '2026-11-26 00:20:00');
        $this->payment($oldSale, $actor, $cash, 40000, '2026-11-25 23:59:59');
        $this->payment($oldSale, $actor, $cash, 7000, '2026-11-27 00:00:00');
        $this->payment($oldSale, $other, $cash, 9000, '2026-11-26 00:10:00');

        $todaySale = $this->sale($actor, '2026-11-26 00:05:00');
        $this->payment($todaySale, $actor, $cash, 2000, '2026-11-26 00:05:00');
        $unpaidSale = $this->sale($actor, '2026-11-26 00:15:00');
        $this->sale($other, '2026-11-26 00:25:00');

        $foreignGym = Gym::query()->create(['name' => 'Other gym']);
        $foreignActor = $this->employee($foreignGym, $role);
        $foreignSale = $this->sale($foreignActor, '2026-11-20 12:00:00');
        $this->payment($foreignSale, $actor, $cash, 8000, '2026-11-26 00:10:00');

        $this->actingAs($actor)
            ->get('/en/my-daily-cashbox?date=2026-11-25&user_id='.$other->id.'&gym_id='.$foreignGym->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Finance/DailyCashbox')
                ->where('date', '2026-11-26')
                ->where('summary.received_amount', 15000)
                ->where('summary.payments_count', 3)
                ->where('summary.today_sales_count', 2)
                ->has('summary.payment_methods', 2)
                ->where('summary.payment_methods.0.name', 'Cash')
                ->where('summary.payment_methods.0.amount', 12000)
                ->where('summary.payment_methods.1.amount', 3000)
                ->has('entries.data', 4)
                ->where('entries.data.0.previous_sale', true)
                ->where('entries.data.0.amount', 3000)
                ->where('entries.data.1.id', 'sale-'.$unpaidSale->id)
                ->where('entries.data.1.amount', 0)
                ->where('entries.data.2.previous_sale', false)
                ->where('entries.data.3.previous_sale', true)
                ->missing('entries.data.0.final_price')
                ->missing('entries.data.0.total_price')
                ->missing('entries.data.0.debt')
                ->missing('entries.data.0.paid_amount')
                ->missing('entries.data.0.payments')
                ->where('translations.app.daily_cashbox.title', 'My daily cashbox'));

        $data = app(DailyCashboxService::class)->pageData($actor);
        foreach ($data['entries']->items() as $entry) {
            $this->assertSame([
                'id', 'occurred_at', 'customer', 'membership', 'previous_sale', 'amount', 'payment_method', 'card_type',
            ], array_keys($entry));
        }
    }

    public function test_summary_covers_all_pages_and_counts_a_sale_once(): void
    {
        $gym = Gym::query()->create(['name' => 'Main gym']);
        $actor = $this->employee($gym, 'manager');
        $cash = PaymentMethod::query()->create(['slug' => 'cash']);
        $sale = $this->sale($actor, '2026-11-26 00:00:00');
        for ($i = 0; $i < 26; $i++) {
            $this->payment($sale, $actor, $cash, 100, '2026-11-26 00:10:00');
        }

        $this->actingAs($actor)->get('/en/my-daily-cashbox?page=2')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.received_amount', 2600)
                ->where('summary.payments_count', 26)
                ->where('summary.today_sales_count', 1)
                ->where('entries.total', 26)
                ->where('entries.current_page', 2)
                ->has('entries.data', 1));
    }

    public function test_invalid_and_refund_payments_are_excluded_and_deleted_sale_labels_are_preserved(): void
    {
        $gym = Gym::query()->create(['name' => 'Main gym']);
        $actor = $this->employee($gym, 'sales_manager');
        $cash = PaymentMethod::query()->create(['slug' => 'cash']);
        $sale = $this->sale($actor, '2026-11-20 12:00:00');
        $valid = $this->payment($sale, $actor, $cash, 1000, '2026-11-26 00:01:00');
        $pending = $this->payment($sale, $actor, $cash, 2000, '2026-11-26 00:02:00');
        $pending->update(['status' => 'pending']);
        $cancelled = $this->payment($sale, $actor, $cash, 3000, '2026-11-26 00:03:00');
        $cancelled->update(['status' => 'cancelled']);
        $refund = $this->payment($sale, $actor, $cash, 4000, '2026-11-26 00:04:00');
        $refund->update(['type' => 'refund']);
        $deleted = $this->payment($sale, $actor, $cash, 5000, '2026-11-26 00:05:00');
        $deleted->delete();
        $reversed = $this->payment($sale, $actor, $cash, 6000, '2026-11-26 00:06:00');
        FinancialTransaction::query()->where('source_type', 'membership_plan_payment')
            ->where('source_id', $reversed->id)->update(['status' => 'reversed']);
        $sale->person->delete();
        $sale->membershipPlan->delete();
        $sale->delete();

        $this->actingAs($actor)->get('/en/my-daily-cashbox')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.received_amount', 1000)
                ->where('summary.payments_count', 1)
                ->where('summary.today_sales_count', 0)
                ->has('entries.data', 1)
                ->where('entries.data.0.id', 'payment-'.$valid->id)
                ->where('entries.data.0.customer', 'Client Example')
                ->where('entries.data.0.membership', 'Monthly membership'));
    }

    public function test_guest_unsupported_role_and_unassigned_gym_cannot_open_cashbox(): void
    {
        $this->get('/hy/my-daily-cashbox')->assertRedirect('/hy/login');
        $gym = Gym::query()->create(['name' => 'Main gym']);
        $owner = $this->employee($gym, 'owner');
        $manager = $this->employee(null, 'manager');
        $this->actingAs($owner)->get('/hy/my-daily-cashbox')->assertForbidden();
        $this->actingAs($manager)->get('/hy/my-daily-cashbox')->assertForbidden();
    }

    public function test_empty_cashbox_is_available_in_all_supported_languages(): void
    {
        $gym = Gym::query()->create(['name' => 'Main gym']);
        $actor = $this->employee($gym, 'manager');
        foreach (['hy' => 'Իմ օրվա դրամարկղը', 'en' => 'My daily cashbox', 'ru' => 'Моя касса за сегодня'] as $locale => $title) {
            $this->actingAs($actor)->get('/'.$locale.'/my-daily-cashbox')->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('summary.received_amount', 0)
                    ->where('summary.today_sales_count', 0)
                    ->has('summary.payment_methods', 0)
                    ->has('entries.data', 0)
                    ->where('translations.app.daily_cashbox.title', $title));
        }
    }

    private function employee(?Gym $gym, string $role): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['name' => $role, 'guard_name' => 'web'], ['g_name' => $role],
        );
        $actor = User::factory()->create(['gym_id' => $gym?->id, 'active' => true]);
        $actor->assignRole($roleModel);

        return $actor;
    }

    private function sale(User $seller, string $soldAt): MembershipSale
    {
        $person = Person::query()->create([
            'name' => 'Client', 'surname' => 'Example', 'phone' => Str::uuid(),
            'email' => Str::uuid().'@example.com', 'password' => 'password',
        ]);
        $person->gyms()->attach($seller->gym_id);
        $category = MembershipCategory::query()->create([
            'gym_id' => $seller->gym_id, 'slug' => 'category-'.Str::uuid(),
        ]);
        $plan = MembershipPlan::query()->create([
            'gym_id' => $seller->gym_id, 'membership_category_id' => $category->id,
            'price' => 60000, 'duration_type' => 'month', 'duration_value' => 1, 'active' => true,
        ]);
        $plan->translations()->create(['locale' => 'en', 'name' => 'Monthly membership']);

        return MembershipSale::query()->create([
            'user_id' => $seller->id, 'gym_id' => $seller->gym_id, 'person_id' => $person->id,
            'membership_plan_id' => $plan->id, 'sold_at' => $soldAt,
            'total_price' => 60000, 'final_price' => 60000, 'payment_status' => 'partial',
        ]);
    }

    private function payment(MembershipSale $sale, User $collector, PaymentMethod $method, float $amount, string $at): MembershipPlanPayment
    {
        $payment = MembershipPlanPayment::query()->create([
            'membership_sale_id' => $sale->id, 'payment_method_id' => $method->id,
            'amount' => $amount, 'type' => 'payment', 'status' => 'paid',
            'created_at' => $at, 'updated_at' => $at,
        ]);
        app(FinancialLedgerService::class)->recordMembershipPayment($payment, $collector->id);

        return $payment;
    }
}
