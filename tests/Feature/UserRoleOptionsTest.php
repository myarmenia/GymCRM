<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserRoleOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_super_admin_group_roles_on_create_and_edit_without_changing_role_records(): void
    {
        config()->set('sync.enabled', false);
        $this->seed(RoleSeeder::class);
        $gym = Gym::query()->create(['name' => 'Central']);
        $admin = User::factory()->create(['gym_id' => $gym->id]);
        $admin->assignRole('admin');
        $staff = User::factory()->create(['gym_id' => $gym->id]);

        $expected = [
            'accountant', 'admin', 'cleaner', 'founder', 'manager', 'sales_manager', 'trainer',
        ];
        $assertRoles = fn ($roles): bool => collect($roles)
            ->pluck('name')
            ->sort()
            ->values()
            ->all() === $expected;

        $this->actingAs($admin)
            ->get(route('user.create', ['locale' => 'en']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/Create')
                ->where('roles', $assertRoles)
                ->etc());

        $this->get(route('user.edit', ['locale' => 'en', 'id' => $staff->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/Edit')
                ->where('roles', $assertRoles)
                ->etc());

        $this->assertSame('super_admin', Role::query()->where('name', 'admin')->value('g_name'));
        $this->assertSame('owner', Role::query()->where('name', 'super_admin')->value('g_name'));
    }

    public function test_other_roles_keep_their_existing_options(): void
    {
        $this->seed(RoleSeeder::class);
        $expectedForOwner = ['owner', 'super_admin'];
        $expectedForSuperAdmin = [
            'accountant', 'admin', 'cleaner', 'founder', 'manager', 'sales_manager', 'trainer',
        ];

        foreach (['owner' => $expectedForOwner, 'super_admin' => $expectedForSuperAdmin] as $role => $expected) {
            $user = User::factory()->create();
            $user->assignRole($role);

            $this->assertSame(
                $expected,
                Role::query()->availableFor($user)->pluck('name')->sort()->values()->all(),
            );
        }
    }
}
