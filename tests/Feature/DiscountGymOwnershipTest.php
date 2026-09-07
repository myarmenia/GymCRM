<?php

namespace Tests\Feature;

use App\Http\Requests\Discounts\StoreDiscountRequest;
use App\Models\Discount;
use App\Models\Gym;
use App\Models\MembershipCategory;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Repositories\Discounts\DiscountRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class DiscountGymOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_discount_automatically_belongs_to_the_authenticated_users_gym(): void
    {
        [$user, $gym] = $this->userAndGym();
        $this->actingAs($user);

        $discount = Discount::query()->create([
            'type' => 'percent',
            'value' => 10,
            'status' => true,
        ]);

        $this->assertSame($gym->id, $discount->gym_id);
    }

    public function test_discount_list_is_scoped_to_the_users_gym(): void
    {
        [$user, $gym] = $this->userAndGym();
        $otherGym = Gym::query()->create(['name' => 'Other gym']);
        Discount::query()->create([
            'gym_id' => $gym->id,
            'type' => 'percent',
            'value' => 10,
            'status' => true,
        ]);
        Discount::query()->create([
            'gym_id' => $otherGym->id,
            'type' => 'percent',
            'value' => 20,
            'status' => true,
        ]);

        $discounts = (new DiscountRepository(new Discount))
            ->paginateForUser($user, 15);

        $this->assertCount(1, $discounts->items());
        $this->assertSame($gym->id, $discounts->items()[0]->gym_id);
    }

    public function test_discount_rejects_a_membership_plan_from_another_gym(): void
    {
        [$user] = $this->userAndGym();
        $otherGym = Gym::query()->create(['name' => 'Other gym']);
        $category = MembershipCategory::query()->create([
            'gym_id' => $otherGym->id,
            'active' => true,
            'slug' => 'other-gym-category',
        ]);
        $plan = MembershipPlan::query()->create([
            'membership_category_id' => $category->id,
            'gym_id' => $otherGym->id,
            'price' => 100,
            'duration_type' => 'month',
            'duration_value' => 1,
            'active' => true,
        ]);
        $request = new StoreDiscountRequest;
        $request->setUserResolver(fn (): User => $user);
        $rules = Arr::only($request->rules(), [
            'membership_plan_ids',
            'membership_plan_ids.*',
        ]);

        $validator = Validator::make([
            'membership_plan_ids' => [$plan->id],
        ], $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('membership_plan_ids.0', $validator->errors()->toArray());
    }

    /** @return array{User, Gym} */
    private function userAndGym(): array
    {
        $gym = Gym::query()->create(['name' => 'Main gym']);
        $user = User::query()->create([
            'name' => 'Manager',
            'surname' => 'Test',
            'email' => uniqid().'@example.com',
            'gym_id' => $gym->id,
            'password' => 'password',
        ]);

        return [$user, $gym];
    }
}
