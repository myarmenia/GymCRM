<?php

namespace Tests\Feature;

use App\Models\ContactNote;
use App\Models\Gym;
use App\Models\MembershipCategory;
use App\Models\MembershipPlan;
use App\Models\PaymentMethod;
use App\Models\Person;
use App\Models\Role;
use App\Models\SalaryPayableAssignment;
use App\Models\SalespersonCommission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManagerMembershipSaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_sales_assign_commission_only_when_a_sales_manager_is_selected(): void
    {
        Bus::fake();
        $gym = Gym::query()->create(['name' => 'Central']);
        $managerRole = Role::query()->firstOrCreate([
            'name' => 'manager', 'guard_name' => 'web', 'g_name' => 'manager',
        ]);
        $salesRole = Role::query()->firstOrCreate([
            'name' => 'sales_manager', 'guard_name' => 'web', 'g_name' => 'sales_manager',
        ]);
        $manager = User::factory()->create(['gym_id' => $gym->id, 'active' => true]);
        $manager->assignRole($managerRole);
        $seller = User::factory()->create(['gym_id' => $gym->id, 'active' => true]);
        $seller->assignRole($salesRole);
        $blocked = User::factory()->create(['gym_id' => $gym->id, 'active' => false]);
        $blocked->assignRole($salesRole);

        $category = MembershipCategory::query()->create(['gym_id' => $gym->id, 'slug' => 'monthly']);
        $plan = MembershipPlan::query()->create([
            'membership_category_id' => $category->id,
            'gym_id' => $gym->id,
            'price' => 100,
            'price_value' => 10,
            'duration_type' => 'month',
            'duration_value' => 1,
            'active' => true,
        ]);
        $paymentMethod = PaymentMethod::query()->create(['slug' => 'cash']);
        $payload = [
            'membership_plan_id' => $plan->id,
            'start_date' => now()->toDateString(),
            'is_full_payment' => true,
            'amount' => 100,
            'payment_method_id' => $paymentMethod->id,
        ];
        $person = function (string $phone) use ($gym): Person {
            $person = Person::query()->create([
                'name' => 'Client',
                'email' => Str::uuid().'@example.com',
                'password' => 'password',
                'phone' => $phone,
            ]);
            $person->gyms()->attach($gym->id);
            return $person;
        };
        $route = fn (Person $customer) => route('membership_sale.store', [
            'locale' => 'en', 'person' => $customer->id,
        ]);
        $this->actingAs($manager);

        $unclaimed = $person('+37499123456');
        $this->get(route('membership_sale.create', ['locale' => 'en', 'person' => $unclaimed->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('MembershipSales/Create')
                ->where('salesManagerOptional', true)
                ->where('contactNoteManagerIdsByGym.'.$gym->id, null)
                ->where('currentSalesManagerId', null)
                ->etc());
        $this->postJson($route($unclaimed), $payload)->assertCreated();
        $this->assertSame(0, SalespersonCommission::query()->count());
        $this->assertSame(0, SalaryPayableAssignment::query()->where('source_type', 'salesperson_commission')->count());
        $this->assertSame(0, ContactNote::query()->count());

        $blockedCustomer = $person('+37499123457');
        ContactNote::query()->create([
            'user_id' => $blocked->id,
            'phone_number' => $blockedCustomer->phone,
            'note' => 'Blocked owner',
        ]);
        $this->get(route('membership_sale.create', ['locale' => 'en', 'person' => $blockedCustomer->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('salesManagerOptional', true)
                ->where('contactNoteManagerIdsByGym.'.$gym->id, null)
                ->etc());
        $this->postJson($route($blockedCustomer), [...$payload, 'sales_manager_id' => ''])->assertCreated();
        $this->assertSame(0, SalespersonCommission::query()->count());
        $this->assertSame(1, ContactNote::query()->count());

        $claimed = $person('+37499123458');
        ContactNote::query()->create([
            'user_id' => $seller->id,
            'phone_number' => $claimed->phone,
            'note' => 'Active owner',
        ]);
        $this->get(route('membership_sale.create', ['locale' => 'en', 'person' => $claimed->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('contactNoteManagerIdsByGym.'.$gym->id, $seller->id)
                ->etc());
        $this->postJson($route($claimed), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('sales_manager_id');
        $this->postJson($route($claimed), [...$payload, 'sales_manager_id' => $seller->id])->assertCreated();
        $this->assertSame($seller->id, SalespersonCommission::query()->sole()->salesperson_id);
        $this->assertSame($seller->id, SalaryPayableAssignment::query()
            ->where('source_type', 'salesperson_commission')->sole()->payee_id);
        $this->assertSame(2, ContactNote::query()->count());

        $chosen = $person('+37499123459');
        $this->postJson($route($chosen), [...$payload, 'sales_manager_id' => $seller->id])->assertCreated();
        $this->assertSame(2, SalespersonCommission::query()->count());
        $this->assertSame($seller->id, ContactNote::query()->where('phone_number', $chosen->phone)->sole()->user_id);
    }
}

