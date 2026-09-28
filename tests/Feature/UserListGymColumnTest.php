<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserListGymColumnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('sync.enabled', false);
    }

    public function test_owner_receives_the_gym_column_and_user_gym_names(): void
    {
        $owner = $this->userWithRole('owner');
        $gym = Gym::query()->create(['name' => 'Central Gym']);
        User::factory()->create(['gym_id' => $gym->id]);

        $this->actingAs($owner)
            ->get(route('user.list', ['locale' => 'en']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/List')
                ->where('canViewGymColumn', true)
                ->where('users.data', fn ($users) => collect($users)->contains(
                    fn (array $user): bool => ($user['gym']['name'] ?? null) === 'Central Gym',
                ))
                ->etc());
    }

    public function test_non_owner_does_not_receive_the_gym_column_or_gym_relation(): void
    {
        $gym = Gym::query()->create(['name' => 'Central Gym']);
        $manager = $this->userWithRole('manager', $gym->id);
        User::factory()->create(['gym_id' => $gym->id]);

        $this->actingAs($manager)
            ->get(route('user.list', ['locale' => 'en']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/List')
                ->where('canViewGymColumn', false)
                ->where('users.data', fn ($users) => collect($users)->every(
                    fn (array $user): bool => ! array_key_exists('gym', $user),
                ))
                ->etc());
    }

    public function test_user_list_filter_shows_every_role_except_owner_to_an_admin(): void
    {
        foreach ([
            'owner' => 'owner',
            'super_admin' => 'owner',
            'admin' => 'super_admin',
            'manager' => 'super_admin',
            'trainer' => 'super_admin',
        ] as $name => $groupName) {
            Role::query()->create([
                'name' => $name,
                'guard_name' => 'web',
                'g_name' => $groupName,
            ]);
        }

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('user.list', ['locale' => 'en']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/List')
                ->where('roles', fn ($roles): bool => collect($roles)
                    ->pluck('value')
                    ->sort()
                    ->values()
                    ->all() === ['admin', 'manager', 'super_admin', 'trainer'])
                ->etc());
    }

    private function userWithRole(string $roleName, ?int $gymId = null): User
    {
        $role = Role::query()->firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
            'g_name' => $roleName,
        ]);
        $user = User::factory()->create(['gym_id' => $gymId]);
        $user->assignRole($role);

        return $user;
    }
}
