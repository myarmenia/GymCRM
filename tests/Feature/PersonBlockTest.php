<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PersonBlockTest extends TestCase
{
    use RefreshDatabase;

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
}
