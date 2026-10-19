<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Gym;
use App\Models\MembershipCategory;
use App\Models\MembershipPlan;
use App\Models\PaymentMethod;
use App\Models\Person;
use App\Models\PersonMembership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuestMembershipSaleTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_promotes_a_linked_guest_to_visitor_even_after_earlier_sales(): void
    {
        Bus::fake();
        $gym = Gym::query()->create(['name' => 'Central']);
        $role = Role::query()->firstOrCreate([
            'name' => 'manager', 'guard_name' => 'web', 'g_name' => 'manager',
        ]);
        $manager = User::factory()->create(['gym_id' => $gym->id, 'active' => true]);
        $manager->assignRole($role);
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
        $person = function (string $phone, string $type) use ($gym): Person {
            $person = Person::query()->create([
                'name' => 'Client',
                'email' => Str::uuid().'@example.com',
                'password' => 'password',
                'phone' => $phone,
                'type' => $type,
            ]);
            $person->gyms()->attach($gym->id);
            return $person;
        };
        $route = fn (Person $customer) => route('membership_sale.store', [
            'locale' => 'en', 'person' => $customer->id,
        ]);
        $this->actingAs($manager);

        $owner = $person('+37499123456', 'visitor');
        $this->postJson($route($owner), $payload)->assertCreated();
        $ownerMembership = PersonMembership::query()->where('person_id', $owner->id)->sole();

        $guest = $person('+37499123457', 'guest');
        Guest::query()->create([
            'person_membership_id' => $ownerMembership->id,
            'person_id' => $owner->id,
            'guest_id' => $guest->id,
        ]);
        $this->postJson($route($guest), [...$payload, 'sales_manager_id' => $manager->id])
            ->assertUnprocessable()->assertJsonValidationErrors('sales_manager_id');
        $this->assertSame('guest', $guest->fresh()->type);

        $this->postJson($route($guest), $payload)->assertCreated();
        $this->assertSame('visitor', $guest->fresh()->type);
        $this->assertSame(1, Guest::query()->where('guest_id', $guest->id)->count());

        $unlinked = $person('+37499123458', 'guest');
        $this->postJson($route($unlinked), $payload)->assertCreated();
        $this->assertSame('guest', $unlinked->fresh()->type);

        $existingMember = $person('+37499123459', 'visitor');
        $this->postJson($route($existingMember), $payload)->assertCreated();
        $existingMember->update(['type' => 'guest']);
        Guest::query()->create([
            'person_membership_id' => $ownerMembership->id,
            'person_id' => $owner->id,
            'guest_id' => $existingMember->id,
        ]);
        $this->postJson($route($existingMember), [
            ...$payload, 'start_date' => now()->addMonths(2)->toDateString(),
        ])->assertCreated();
        $this->assertSame('visitor', $existingMember->fresh()->type);
    }
}

