<?php

namespace Tests\Feature;

use App\Models\EntryCode;
use App\Models\EntryPermission;
use App\Models\Gym;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PersonBlockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('sync.enabled', false);
    }

    public function test_sales_manager_can_block_a_person_from_the_same_gym(): void
    {
        $gym = Gym::query()->create(['name' => 'Test gym']);
        $manager = User::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Manager',
            'surname' => 'User',
            'email' => 'manager@example.com',
            'password' => Hash::make('password'),
        ]);
        $manager->assignRole(Role::query()->create([
            'name' => 'sales_manager',
            'guard_name' => 'web',
            'g_name' => 'sales_manager',
        ]));
        $person = Person::query()->create([
            'name' => 'Client',
            'surname' => 'User',
            'email' => 'client@example.com',
            'password' => Hash::make('password'),
            'phone' => '+37499123456',
            'type' => 'visitor',
        ]);
        $person->gyms()->attach($gym->id);

        $this->actingAs($manager)
            ->patch(route('person.block', ['locale' => 'hy', 'id' => $person->id]))
            ->assertRedirect();

        $this->assertTrue($person->fresh()->is_blocked);
    }

    public function test_profile_shows_blocked_status_and_inactive_entry_code(): void
    {
        $gym = Gym::query()->create(['name' => 'Test gym']);
        $manager = User::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Manager',
            'surname' => 'User',
            'email' => 'profile-manager@example.com',
            'password' => Hash::make('password'),
        ]);
        $manager->assignRole(Role::query()->create([
            'name' => 'sales_manager',
            'guard_name' => 'web',
            'g_name' => 'sales_manager',
        ]));
        $person = Person::query()->create([
            'name' => 'Blocked',
            'surname' => 'Visitor',
            'type' => 'visitor',
            'birth_date' => '1990-01-01',
            'is_blocked' => true,
        ]);
        $person->gyms()->attach($gym->id);
        $entryCode = EntryCode::query()->create([
            'gym_id' => $gym->id,
            'token' => 'INACTIVE-1001',
            'type' => 'rfId',
            'status' => false,
            'activation' => true,
        ]);
        EntryPermission::query()->create([
            'entry_code_id' => $entryCode->id,
            'relation_type' => $person->getMorphClass(),
            'relation_id' => $person->id,
            'status' => true,
        ]);

        $this->actingAs($manager)
            ->get(route('person.profile', ['locale' => 'hy', 'id' => $person->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('People/Profile')
                ->where('person.is_blocked', true)
                ->where('entryCode.token', 'INACTIVE-1001')
                ->where('entryCode.status', false)
                ->etc());
    }
}
