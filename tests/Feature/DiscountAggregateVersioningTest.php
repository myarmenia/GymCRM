<?php

namespace Tests\Feature;

use App\Models\Discount;
use App\Models\Gym;
use App\Models\MembershipCategory;
use App\Models\MembershipPlan;
use App\Repositories\Discounts\DiscountRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountAggregateVersioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('sync.enabled', false);
    }

    public function test_changing_attached_membership_plans_advances_discount_version_once(): void
    {
        [$discount, $firstPlan, $secondPlan] = $this->dependencies();
        $discount->membershipPlans()->attach($firstPlan->id);

        $updated = $this->repository()->updateWithRelations(
            $discount->id,
            [],
            $this->translations($discount),
            [$secondPlan->id],
        );

        $this->assertSame(2, (int) $updated->version);
        $this->assertEquals([$secondPlan->id], $updated->membershipPlans->pluck('id')->all());
    }

    public function test_changing_a_translation_advances_discount_version_once(): void
    {
        [$discount, $firstPlan] = $this->dependencies();
        $discount->membershipPlans()->attach($firstPlan->id);

        $updated = $this->repository()->updateWithRelations(
            $discount->id,
            [],
            ['hy' => ['name' => 'Թարմացված զեղչ', 'description' => null]],
            [$firstPlan->id],
        );

        $this->assertSame(2, (int) $updated->version);
    }

    public function test_unchanged_aggregate_keeps_its_version(): void
    {
        [$discount, $firstPlan] = $this->dependencies();
        $discount->membershipPlans()->attach($firstPlan->id);

        $updated = $this->repository()->updateWithRelations(
            $discount->id,
            [],
            $this->translations($discount),
            [$firstPlan->id],
        );

        $this->assertSame(1, (int) $updated->version);
    }

    /** @return array{Discount, MembershipPlan, MembershipPlan} */
    private function dependencies(): array
    {
        $gym = Gym::query()->create(['name' => 'Main gym']);
        $category = MembershipCategory::query()->create([
            'gym_id' => $gym->id,
            'active' => true,
            'slug' => 'category-'.uniqid(),
        ]);
        $plans = collect(['First', 'Second'])->map(fn (string $name): MembershipPlan => MembershipPlan::query()->create([
            'membership_category_id' => $category->id,
            'gym_id' => $gym->id,
            'price' => 100,
            'duration_type' => 'month',
            'duration_value' => 1,
            'active' => true,
        ]),
        );
        $discount = Discount::query()->create([
            'gym_id' => $gym->id,
            'type' => 'percent',
            'value' => 10,
            'status' => true,
        ]);
        $discount->translations()->create([
            'locale' => 'hy',
            'name' => 'Զեղչ',
            'description' => null,
        ]);

        return [$discount, $plans[0], $plans[1]];
    }

    /** @return array<string, array{name: string, description: string|null}> */
    private function translations(Discount $discount): array
    {
        $translation = $discount->translations()->where('locale', 'hy')->firstOrFail();

        return [
            'hy' => [
                'name' => $translation->name,
                'description' => $translation->description,
            ],
        ];
    }

    private function repository(): DiscountRepository
    {
        return new DiscountRepository(new Discount);
    }
}
