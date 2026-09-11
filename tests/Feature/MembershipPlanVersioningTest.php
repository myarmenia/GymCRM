<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\MembershipCategory;
use App\Models\ScheduleName;
use App\Models\User;
use App\Services\Memberships\MembershipPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipPlanVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_persists_each_child_once_and_schedule_without_a_trainer(): void
    {
        [$user, $category, $schedule] = $this->dependencies();
        $this->actingAs($user);

        $membershipPlan = app(MembershipPlanService::class)->store(
            $this->payload($category->id, $schedule->id),
        );

        $this->assertSame('175.00', $membershipPlan->price);
        $this->assertSame('percent', $membershipPlan->price_type);
        $this->assertSame('10.000000', $membershipPlan->price_value);
        $this->assertSame(1, $membershipPlan->version);
        $this->assertSame(1, $membershipPlan->translations()->count());
        $this->assertSame(1, $membershipPlan->schedules()->count());
        $this->assertSame(0, $membershipPlan->membershipPlanTrainers()->count());
        $this->assertSame($schedule->id, $membershipPlan->schedules()->firstOrFail()->id);
    }

    public function test_create_recalculates_trainer_salary_from_the_canonical_percentage(): void
    {
        [$user, $category, $schedule] = $this->dependencies();
        $this->actingAs($user);
        $payload = $this->payload($category->id, $schedule->id);
        $payload['trainers'] = [[
            'trainer_id' => $user->id,
            'price_type' => 'fixed',
            'price_value' => 16.666667,
            'total_price' => 9999,
        ]];

        $membershipPlan = app(MembershipPlanService::class)->store($payload);
        $trainerSalary = $membershipPlan->membershipPlanTrainers()->firstOrFail();

        $this->assertSame('percent', $trainerSalary->price_type);
        $this->assertSame('16.666667', $trainerSalary->price_value);
        $this->assertSame('29.17', $trainerSalary->total_price);
    }

    public function test_shared_child_update_advances_root_version_once(): void
    {
        [$user, $category, $schedule] = $this->dependencies();
        $this->actingAs($user);
        $service = app(MembershipPlanService::class);
        $membershipPlan = $service->store($this->payload($category->id, $schedule->id));
        $payload = $this->payload($category->id, $schedule->id);
        $payload['translations']['hy']['name'] = 'Թարմացված աբոնեմենտ';

        $service->update($membershipPlan->id, $payload);

        $this->assertSame(2, $membershipPlan->fresh()->version);
        $this->assertSame(
            'Թարմացված աբոնեմենտ',
            $membershipPlan->translations()->firstOrFail()->name,
        );
    }

    /** @return array{User, MembershipCategory, ScheduleName} */
    private function dependencies(): array
    {
        $gym = Gym::query()->create(['name' => 'Main gym']);
        $user = User::query()->create([
            'name' => 'Admin',
            'surname' => 'Test',
            'email' => 'membership-plan-admin@example.com',
            'gym_id' => $gym->id,
            'password' => 'password',
        ]);
        $category = MembershipCategory::query()->create([
            'gym_id' => $gym->id,
            'active' => true,
            'slug' => 'premium',
        ]);
        $schedule = ScheduleName::query()->create([
            'name' => 'Morning',
            'status' => 1,
        ]);

        return [$user, $category, $schedule];
    }

    /** @return array<string, mixed> */
    private function payload(int $categoryId, int $scheduleId): array
    {
        return [
            'membership_category_id' => $categoryId,
            'price' => 175,
            'price_type' => 'percent',
            'price_value' => 10,
            'duration_type' => 'month',
            'duration_value' => 1,
            'visits_limit' => null,
            'start_date' => null,
            'end_date' => null,
            'guest_limit' => 0,
            'freeze_limit' => 0,
            'active' => true,
            'translations' => [
                'hy' => [
                    'name' => 'Աբոնեմենտ',
                    'description' => null,
                ],
            ],
            'schedule_name_id' => $scheduleId,
            'trainers' => [],
        ];
    }
}
