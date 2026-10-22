<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\MembershipCategory;
use App\Models\MembershipPlan;
use App\Models\MembershipPlanTranslation;
use App\Models\MembershipSale;
use App\Models\Person;
use App\Models\PersonMembership;
use App\Models\User;
use App\Repositories\People\PersonRepository;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PersonListMembershipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_people_list_only_shows_memberships_currently_within_their_valid_dates(): void
    {
        $gym = Gym::query()->create(['name' => 'Main gym']);
        $ownerRole = Role::query()->create([
            'name' => 'owner',
            'guard_name' => 'web',
            'g_name' => 'owner',
        ]);
        $user = User::query()->create([
            'gym_id' => $gym->id,
            'name' => 'List',
            'surname' => 'Owner',
            'email' => Str::uuid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($ownerRole);

        $person = Person::query()->create([
            'name' => 'Membership customer',
            'email' => Str::uuid().'@example.com',
            'password' => Hash::make('password'),
            'phone' => '+37499123456',
        ]);
        $person->gyms()->attach($gym->id);

        $category = MembershipCategory::query()->create([
            'gym_id' => $gym->id,
            'slug' => 'list-memberships',
        ]);
        $plan = MembershipPlan::query()->create([
            'membership_category_id' => $category->id,
            'gym_id' => $gym->id,
            'price' => 10000,
            'duration_type' => 'month',
            'duration_value' => 1,
        ]);
        MembershipPlanTranslation::query()->create([
            'membership_plan_id' => $plan->id,
            'locale' => 'hy',
            'name' => 'Ամսական',
        ]);
        $sale = MembershipSale::query()->create([
            'user_id' => $user->id,
            'person_id' => $person->id,
            'gym_id' => $gym->id,
            'membership_plan_id' => $plan->id,
        ]);

        $today = Carbon::today('Asia/Yerevan');
        $yesterday = $today->copy()->subDay()->toDateString();
        $tomorrow = $today->copy()->addDay()->toDateString();
        $todayString = $today->toDateString();
        $visible = [
            ['status' => 'active', 'start_date' => $yesterday, 'end_date' => $tomorrow, 'valid_at' => $tomorrow],
            ['status' => 'frozen', 'start_date' => $yesterday, 'end_date' => $tomorrow, 'valid_at' => $tomorrow],
            ['status' => 'waiting', 'start_date' => $todayString, 'end_date' => $tomorrow, 'valid_at' => $tomorrow],
            ['status' => 'active', 'start_date' => $yesterday, 'end_date' => $todayString, 'valid_at' => $todayString],
        ];
        $hidden = [
            ['status' => 'active', 'start_date' => $yesterday, 'end_date' => $yesterday, 'valid_at' => $tomorrow],
            ['status' => 'active', 'start_date' => $yesterday, 'end_date' => $tomorrow, 'valid_at' => $yesterday],
            ['status' => 'waiting', 'start_date' => $tomorrow, 'end_date' => $tomorrow, 'valid_at' => $tomorrow],
            ['status' => 'expired', 'start_date' => $yesterday, 'end_date' => $tomorrow, 'valid_at' => $tomorrow],
            ['status' => 'cancelled', 'start_date' => $yesterday, 'end_date' => $tomorrow, 'valid_at' => $tomorrow],
        ];
        $visibleIds = [];

        foreach ([...$visible, ...$hidden] as $index => $attributes) {
            $membership = PersonMembership::query()->create([
                'membership_sale_id' => $sale->id,
                'user_id' => $user->id,
                'person_id' => $person->id,
                'gym_id' => $gym->id,
                'membership_plan_id' => $plan->id,
                ...$attributes,
            ]);
            if ($index < count($visible)) {
                $visibleIds[] = $membership->id;
            }
        }

        $listedPerson = (new PersonRepository(new Person))
            ->paginateForUser($user)
            ->getCollection()
            ->sole();

        $this->assertTrue($listedPerson->relationLoaded('memberships'));
        $this->assertEqualsCanonicalizing($visibleIds, $listedPerson->memberships->modelKeys());
        $this->assertTrue(
            $listedPerson->memberships->every(
                fn (PersonMembership $membership): bool => $membership->relationLoaded('membershipPlan')
                    && $membership->membershipPlan->relationLoaded('translations'),
            ),
        );

        $this->actingAs($user)->get(route('person.list', ['locale' => 'hy']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('People/List')
                ->has('people.data.0.memberships', count($visible))
                ->etc());
    }
}
