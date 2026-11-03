<?php

namespace Tests\Feature;

use App\Models\ContactProfile;
use App\Models\Gym;
use App\Models\MembershipCategory;
use App\Models\MembershipPlan;
use App\Models\PaymentMethod;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NewcomerSingleVisitSaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_non_single_visit_sale_moves_newcomer_to_visitors(): void
    {
        config()->set('sync.enabled', false);
        $gym = Gym::query()->create(['name' => 'Central']);
        $seller = User::factory()->create(['gym_id' => $gym->id, 'active' => true]);
        $seller->assignRole(Role::query()->firstOrCreate([
            'name' => 'sales_manager', 'guard_name' => 'web', 'g_name' => 'sales_manager',
        ]));
        $category = MembershipCategory::query()->create(['gym_id' => $gym->id, 'slug' => 'regular']);
        $payment = PaymentMethod::query()->create(['slug' => 'cash']);
        $this->actingAs($seller);

        foreach ([['month', 1], ['visit', 2]] as [$duration, $limit]) {
            $person = Person::query()->create(['name' => 'Newcomer', 'type' => 'newcomer']);
            $person->gyms()->attach($gym->id);
            $plan = MembershipPlan::query()->create([
                'membership_category_id' => $category->id, 'gym_id' => $gym->id,
                'price' => 100, 'price_value' => 10,
                'duration_type' => $duration, 'duration_value' => 1,
                'visits_limit' => $limit, 'active' => true,
            ]);

            $this->postJson(route('membership_sale.store', [
                'locale' => 'en', 'person' => $person->id,
            ]), [
                'person_id' => $person->id,
                'membership_plan_id' => $plan->id,
                'sales_manager_id' => $seller->id,
                'start_date' => now()->toDateString(),
                'is_full_payment' => true, 'amount' => 100,
                'payment_method_id' => $payment->id,
            ])->assertCreated();
            $this->assertSame('visitor', $person->fresh()->type);
        }
    }

    public function test_contact_becomes_newcomer_and_only_one_visit_plans_can_be_sold(): void
    {
        config()->set('sync.enabled', false);
        $gym = Gym::query()->create(['name' => 'Central']);
        $seller = User::factory()->create(['gym_id' => $gym->id, 'active' => true]);
        $seller->assignRole(Role::query()->firstOrCreate([
            'name' => 'sales_manager', 'guard_name' => 'web', 'g_name' => 'sales_manager',
        ]));
        $this->actingAs($seller)->post(route('contact-notes.store', ['locale' => 'en']), [
            'phone_number' => '099123456', 'note' => 'Interested',
            'name' => 'New', 'surname' => 'Client', 'birth_date' => '2000-01-02',
        ])->assertRedirect();
        $contact = ContactProfile::query()->sole();

        $this->get(route('person.newcomer.create', ['locale' => 'en', 'contactProfile' => $contact->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('People/NewcomerCreate')
                ->where('initialPhone', '099123456')
                ->where('initialContact.name', 'New')
                ->etc());

        $response = $this->post(route('person.newcomer.store', ['locale' => 'en', 'contactProfile' => $contact->id]), [
            'name' => 'New', 'surname' => 'Client', 'birth_date' => '2000-01-02',
            'type' => 'visitor', 'phone' => '000000000',
            'entry_code_mode' => 'new', 'entry_code_token' => 'NEW-1001',
        ]);
        $person = Person::query()->sole();
        $response->assertRedirect(route('membership_sale.create', [
            'locale' => 'en', 'person' => $person->id, 'single_visit' => 1,
        ]));
        $this->assertSame('newcomer', $person->type);
        $this->assertSame($contact->phone_number, $person->phone);
        $this->assertSame('NEW-1001', $person->entryPermissions()->first()?->entryCode?->token);
        $this->assertTrue($person->gyms()->whereKey($gym->id)->exists());
        $this->get(route('person.list', ['locale' => 'en', 'section' => 'newcomers']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('people.data.0.id', $person->id)
                ->where('people.data.0.membership_sales_exists', false)
                ->etc());
        $this->get(route('contact-notes.index', ['locale' => 'en']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('contacts.data.0.existing_person_id', $person->id)
                ->etc());

        $category = MembershipCategory::query()->create(['gym_id' => $gym->id, 'slug' => 'visits']);
        $plan = fn (string $duration, int $limit) => MembershipPlan::query()->create([
            'membership_category_id' => $category->id, 'gym_id' => $gym->id,
            'price' => 100, 'price_value' => 10,
            ...(Schema::hasColumn('membership_plans', 'main_price') ? ['main_price' => 100] : []),
            'duration_type' => $duration, 'duration_value' => 1,
            'visits_limit' => $limit, 'active' => true,
        ]);
        $single = $plan('visit', 1);
        $multiple = $plan('visit', 2);
        $regular = $plan('month', 1);

        $this->get(route('membership_sale.create', [
            'locale' => 'en', 'person' => $person->id, 'single_visit' => 1,
        ]))->assertInertia(fn (Assert $page) => $page
            ->component('MembershipSales/Create')
            ->where('singleVisitOnly', true)
            ->has('membershipPlans', 1)
            ->where('membershipPlans.0.id', $single->id)
            ->etc());

        $payment = PaymentMethod::query()->create(['slug' => 'cash']);
        $url = route('membership_sale.single-visit.store', ['locale' => 'en', 'person' => $person->id]);
        $payload = [
            'sales_manager_id' => $seller->id, 'start_date' => now()->toDateString(),
            'is_full_payment' => true, 'amount' => 100, 'payment_method_id' => $payment->id,
        ];
        $this->postJson($url, [...$payload, 'membership_plan_id' => $multiple->id])
            ->assertUnprocessable()->assertJsonValidationErrors('membership_plan_id');
        $this->postJson($url, [...$payload, 'membership_plan_id' => $single->id])
            ->assertCreated();
        $this->assertDatabaseHas('membership_sales', [
            'person_id' => $person->id, 'membership_plan_id' => $single->id,
        ]);
        $this->assertSame('newcomer', $person->fresh()->type);
        $this->get(route('person.list', ['locale' => 'en', 'section' => 'newcomers']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('people.data.0.id', $person->id)
                ->where('people.data.0.membership_sales_exists', true)
                ->etc());
        $this->get(route('membership_sale.create', [
            'locale' => 'en', 'person' => $person->id, 'single_visit' => 1,
        ]))->assertInertia(fn (Assert $page) => $page
            ->where('singleVisitOnly', true)
            ->has('membershipPlans', 1)
            ->etc());
        $this->postJson($url, [...$payload, 'membership_plan_id' => $single->id])
            ->assertCreated();
        $this->assertSame(2, $person->membershipSales()->count());
        $this->assertSame('newcomer', $person->fresh()->type);
        $this->get(route('person.list', ['locale' => 'en', 'section' => 'newcomers']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('people.data.0.id', $person->id)
                ->etc());
        $versionBeforeRegularSale = (int) $person->fresh()->version;

        $this->postJson(route('membership_sale.store', ['locale' => 'en', 'person' => $person->id]), [
            ...$payload, 'membership_plan_id' => $regular->id,
        ])->assertCreated();
        $this->assertSame('visitor', $person->fresh()->type);
        $this->assertGreaterThan($versionBeforeRegularSale, (int) $person->fresh()->version);
        $this->get(route('person.list', ['locale' => 'en', 'section' => 'newcomers']))
            ->assertInertia(fn (Assert $page) => $page->where('people.total', 0)->etc());
        $this->get(route('person.list', ['locale' => 'en', 'section' => 'clients']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('people.data.0.id', $person->id)->etc());
    }
}
