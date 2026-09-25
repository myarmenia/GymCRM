<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\MembershipCategory;
use App\Models\MembershipPlan;
use App\Models\Role;
use App\Models\User;
use App\Services\MembershipSales\MembershipSaleService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

class InactiveTrainerMembershipSaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_trainer_cannot_be_resolved_for_membership_sale(): void
    {
        $gym = Gym::query()->create(['name' => 'Main gym']);
        $seller = User::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Active',
            'surname' => 'Seller',
            'email' => Str::uuid().'@example.com',
            'password' => Hash::make('password'),
            'active' => true,
        ]);
        $trainerRole = Role::query()->create([
            'name' => 'trainer',
            'guard_name' => 'web',
            'g_name' => 'trainer',
        ]);
        $trainerRole->id = 7;
        $trainerRole->save();
        $trainer = User::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Inactive',
            'surname' => 'Trainer',
            'email' => Str::uuid().'@example.com',
            'password' => Hash::make('password'),
            'active' => false,
        ]);
        $trainer->assignRole($trainerRole);
        $category = MembershipCategory::query()->create([
            'gym_id' => $gym->id,
            'slug' => 'personal',
        ]);
        $plan = MembershipPlan::query()->create([
            'membership_category_id' => $category->id,
            'gym_id' => $gym->id,
            'price' => 10000,
            'duration_type' => 'month',
            'duration_value' => 1,
            'active' => true,
        ]);
        $plan->trainers()->attach($trainer->id, [
            'price_type' => 'percent',
            'price_value' => 10,
            'total_price' => 1000,
        ]);

        $method = new ReflectionMethod(MembershipSaleService::class, 'getTrainer');

        $this->expectException(ModelNotFoundException::class);
        $method->invoke(
            app(MembershipSaleService::class),
            $trainer->id,
            $seller,
            $gym->id,
            $plan,
        );
    }
}
