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
use App\Services\MembershipSales\MembershipSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContactNoteMembershipSaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_contact_note_manager_is_excluded_and_does_not_block_an_active_seller(): void
    {
        Bus::fake();
        $gym = Gym::query()->create(['name' => 'Central']);
        $role = Role::query()->firstOrCreate([
            'name' => 'sales_manager',
            'guard_name' => 'web',
            'g_name' => 'sales_manager',
        ]);
        $inactiveManager = User::factory()->create(['gym_id' => $gym->id, 'active' => false]);
        $inactiveManager->assignRole($role);
        $seller = User::factory()->create(['gym_id' => $gym->id, 'active' => true]);
        $seller->assignRole($role);

        $person = Person::query()->create([
            'name' => 'Client',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
            'phone' => '+37499123456',
        ]);
        $person->gyms()->attach($gym->id);
        $category = MembershipCategory::query()->create([
            'gym_id' => $gym->id,
            'slug' => 'monthly',
        ]);
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
        ContactNote::query()->create([
            'user_id' => $inactiveManager->id,
            'phone_number' => '099 123-456',
            'note' => 'First contact',
        ]);

        $this->actingAs($seller)->get(route('membership_sale.create', [
            'locale' => 'en', 'person' => $person->id,
        ]))->assertInertia(fn (Assert $page) => $page
            ->component('MembershipSales/Create')
            ->where('contactNoteManagerIdsByGym.'.$gym->id, null)
            ->where('currentSalesManagerId', $seller->id)
            ->has('salesManagers', 1)
            ->where('salesManagers.0.id', $seller->id)
            ->etc());

        $route = route('membership_sale.store', ['locale' => 'en', 'person' => $person->id]);
        $payload = [
            'membership_plan_id' => $plan->id,
            'start_date' => now()->toDateString(),
            'is_full_payment' => true,
            'amount' => 100,
            'payment_method_id' => $paymentMethod->id,
        ];
        $this->postJson($route, [...$payload, 'sales_manager_id' => $inactiveManager->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sales_manager_id');
        $this->postJson($route, [...$payload, 'sales_manager_id' => $seller->id])
            ->assertCreated();
        $this->assertSame($seller->id, SalespersonCommission::query()->sole()->salesperson_id);
        $this->get(route('membership_sale.create', [
            'locale' => 'en', 'person' => $person->id,
        ]))->assertInertia(fn (Assert $page) => $page
            ->component('MembershipSales/Create')
            ->where('contactNoteManagerIdsByGym.'.$gym->id, $seller->id)
            ->has('salesManagers', 1)
            ->etc());
    }

    public function test_contact_note_owner_is_shown_and_receives_commission_when_another_manager_sells(): void
    {
        Bus::fake();
        $gym = Gym::query()->create(['name' => 'Central']);
        $role = Role::query()->firstOrCreate([
            'name' => 'sales_manager',
            'guard_name' => 'web',
            'g_name' => 'sales_manager',
        ]);
        $owner = User::factory()->create(['gym_id' => $gym->id, 'active' => true]);
        $owner->assignRole($role);
        $seller = User::factory()->create(['gym_id' => $gym->id, 'active' => true]);
        $seller->assignRole($role);

        $person = Person::query()->create([
            'name' => 'Client',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
            'phone' => '+37499123456',
        ]);
        $person->gyms()->attach($gym->id);

        $category = MembershipCategory::query()->create([
            'gym_id' => $gym->id,
            'slug' => 'monthly',
        ]);
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
        ContactNote::query()->create([
            'user_id' => $owner->id,
            'phone_number' => '099 123-456',
            'note' => 'First contact',
        ]);
        ContactNote::query()->create([
            'user_id' => $seller->id,
            'phone_number' => '+374 99 123 456',
            'note' => 'Later contact',
        ]);

        $this->actingAs($seller)->get(route('membership_sale.create', [
            'locale' => 'en', 'person' => $person->id,
        ]))->assertInertia(fn (Assert $page) => $page
            ->component('MembershipSales/Create')
            ->where('contactNoteManagerIdsByGym.'.$gym->id, $owner->id)
            ->where('currentSalesManagerId', $seller->id)
            ->etc());

        $payload = [
            'membership_plan_id' => $plan->id,
            'start_date' => now()->toDateString(),
            'is_full_payment' => true,
            'amount' => 100,
            'payment_method_id' => $paymentMethod->id,
            'sales_manager_id' => $seller->id,
        ];
        $route = route('membership_sale.store', ['locale' => 'en', 'person' => $person->id]);

        $this->postJson($route, $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sales_manager_id');
        $this->postJson($route, array_diff_key($payload, ['sales_manager_id' => true]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sales_manager_id');

        $this->postJson($route, [...$payload, 'sales_manager_id' => $owner->id])
            ->assertCreated();

        $commission = SalespersonCommission::query()->sole();
        $this->assertSame($owner->id, $commission->salesperson_id);
        $this->assertSame(10.0, (float) $commission->salary_amount);
        $this->assertSame($owner->id, SalaryPayableAssignment::query()
            ->where('source_type', 'salesperson_commission')
            ->sole()->payee_id);
        $this->assertSame(2, ContactNote::query()->count());

        $unclaimedPerson = Person::query()->create([
            'name' => 'New client',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
            'phone' => '+37499123457',
        ]);
        $unclaimedPerson->gyms()->attach($gym->id);
        $this->get(route('membership_sale.create', [
            'locale' => 'en', 'person' => $unclaimedPerson->id,
        ]))->assertInertia(fn (Assert $page) => $page
            ->component('MembershipSales/Create')
            ->where('contactNoteManagerIdsByGym.'.$gym->id, null)
            ->where('currentSalesManagerId', $seller->id)
            ->etc());
        $unclaimedRoute = route('membership_sale.store', ['locale' => 'en', 'person' => $unclaimedPerson->id]);
        $this->postJson($unclaimedRoute, array_diff_key($payload, ['sales_manager_id' => true]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sales_manager_id');
        $this->assertSame(0, ContactNote::query()->where('phone_number', $unclaimedPerson->phone)->count());
        $this->postJson($unclaimedRoute, [...$payload, 'sales_manager_id' => $owner->id])->assertCreated();
        $this->assertSame($owner->id, SalespersonCommission::query()
            ->orderByDesc('id')
            ->firstOrFail()->salesperson_id);
        $this->assertDatabaseHas('contact_notes', [
            'user_id' => $owner->id,
            'phone_number' => $unclaimedPerson->phone,
            'note' => 'Նոր վաճառք',
        ]);
        $this->assertSame(1, ContactNote::query()->where('phone_number', $unclaimedPerson->phone)->count());

        $this->actingAs($seller)->get(route('membership_sale.create', [
            'locale' => 'en', 'person' => $unclaimedPerson->id,
        ]))->assertInertia(fn (Assert $page) => $page
            ->component('MembershipSales/Create')
            ->where('contactNoteManagerIdsByGym.'.$gym->id, $owner->id)
            ->where('currentSalesManagerId', $seller->id)
            ->etc());
        $nextSale = [...$payload, 'start_date' => now()->addMonths(2)->toDateString()];
        $this->postJson($unclaimedRoute, $nextSale)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sales_manager_id');
        $this->postJson($unclaimedRoute, [...$nextSale, 'sales_manager_id' => $owner->id])->assertCreated();
        $this->assertSame($owner->id, SalespersonCommission::query()
            ->orderByDesc('id')->firstOrFail()->salesperson_id);
        $this->assertSame(1, ContactNote::query()->where('phone_number', $unclaimedPerson->phone)->count());

        $adminRole = Role::query()->firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
            'g_name' => 'super_admin',
        ]);
        $admin = User::factory()->create(['gym_id' => $gym->id, 'active' => true]);
        $admin->assignRole($adminRole);
        $adminCustomer = Person::query()->create([
            'name' => 'Admin client',
            'email' => Str::uuid().'@example.com',
            'password' => 'password',
            'phone' => '+37499123458',
        ]);
        $adminCustomer->gyms()->attach($gym->id);
        $adminRoute = route('membership_sale.store', ['locale' => 'en', 'person' => $adminCustomer->id]);
        $this->actingAs($admin)->get(route('membership_sale.create', [
            'locale' => 'en', 'person' => $adminCustomer->id,
        ]))->assertInertia(fn (Assert $page) => $page
            ->component('MembershipSales/Create')
            ->where('currentSalesManagerId', null)
            ->etc());
        $this->postJson($adminRoute, [...$payload, 'sales_manager_id' => $admin->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sales_manager_id');

        try {
            app(MembershipSaleService::class)->store([
                ...array_diff_key($payload, ['sales_manager_id' => true]),
                'person_id' => $adminCustomer->id,
            ]);
            $this->fail('A non-sales-manager must not receive a commission by default.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('sales_manager_id', $exception->errors());
        }
        $this->assertSame(3, SalespersonCommission::query()->count());
        $this->postJson($adminRoute, [...$payload, 'sales_manager_id' => $owner->id])->assertCreated();
        $this->assertSame(0, ContactNote::query()->where('phone_number', $adminCustomer->phone)->count());
    }
}
