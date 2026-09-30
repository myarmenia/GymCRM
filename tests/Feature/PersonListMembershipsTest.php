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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PersonListMembershipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_people_list_loads_memberships_with_every_status(): void
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

        foreach (['active', 'frozen', 'waiting', 'expired', 'cancelled'] as $status) {
            PersonMembership::query()->create([
                'membership_sale_id' => $sale->id,
                'user_id' => $user->id,
                'person_id' => $person->id,
                'gym_id' => $gym->id,
                'membership_plan_id' => $plan->id,
                'status' => $status,
            ]);
        }

        $listedPerson = (new PersonRepository(new Person))
            ->paginateForUser($user)
            ->getCollection()
            ->sole();

        $this->assertTrue($listedPerson->relationLoaded('memberships'));
        $this->assertEqualsCanonicalizing(
            ['active', 'frozen', 'waiting', 'expired', 'cancelled'],
            $listedPerson->memberships->pluck('status')->all(),
        );
        $this->assertTrue(
            $listedPerson->memberships->every(
                fn (PersonMembership $membership): bool => $membership->relationLoaded('membershipPlan')
                    && $membership->membershipPlan->relationLoaded('translations'),
            ),
        );
    }
}
