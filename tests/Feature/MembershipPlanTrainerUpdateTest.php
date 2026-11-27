<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\MembershipCategory;
use App\Models\MembershipPlan;
use App\Models\ScheduleName;
use App\Models\User;
use App\Repositories\Memberships\MembershipPlanRepository;
use App\Services\Memberships\MembershipPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MembershipPlanTrainerUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_unchanged_edit_submission_keeps_trainers_without_writing_their_rows(): void
    {
        [$plan, $payload] = $this->fixture();
        $before = $plan->membershipPlanTrainers()->orderBy('id')->get()->toArray();
        $this->assertNotSame($before[0]['id'], $payload['trainers'][0]['trainer_id']);
        $version = $plan->version;
        $writes = [];
        DB::listen(function ($query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete)\b/i', $query->sql)
                && str_contains($query->sql, 'membership_plan_trainers')) {
                $writes[] = $query->sql;
            }
        });

        $editData = app(MembershipPlanRepository::class)->findForEdit($plan->id, 'hy');
        $this->withoutMiddleware()->put('/hy/membership-plan/update/'.$plan->id, $editData)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame($before, $plan->membershipPlanTrainers()->orderBy('id')->get()->toArray());
        $this->assertSame($version, $plan->fresh()->version);
        $this->assertSame([], $writes);
    }

    public function test_salary_edit_keeps_both_selected_trainers(): void
    {
        [$plan, $payload] = $this->fixture();
        $before = $plan->membershipPlanTrainers()->orderBy('id')->get();
        $payload['trainers'][0]['price_value'] = 30;

        app(MembershipPlanService::class)->update($plan->id, $payload);

        $after = $plan->membershipPlanTrainers()->orderBy('id')->get();
        $this->assertSame($before->pluck('id')->all(), $after->pluck('id')->all());
        $this->assertSame('30.000000', $after[0]->price_value);
        $this->assertSame('9000.00', $after[0]->total_price);
        $this->assertSame($before[1]->getAttributes(), $after[1]->getAttributes());
    }

    public function test_explicit_deselection_removes_only_the_deselected_trainer(): void
    {
        [$plan, $payload] = $this->fixture();
        $retainedTrainerId = $payload['trainers'][0]['trainer_id'];
        $payload['trainers'] = [$payload['trainers'][0]];

        app(MembershipPlanService::class)->update($plan->id, $payload);

        $this->assertSame([$retainedTrainerId], $plan->membershipPlanTrainers()->pluck('trainer_id')->all());
    }

    /** @return array{MembershipPlan, array<string, mixed>} */
    private function fixture(): array
    {
        config()->set('sync.enabled', false);
        $gym = Gym::query()->create(['name' => 'Trainer update gym']);
        $admin = User::factory()->create(['gym_id' => $gym->id]);
        $this->actingAs($admin);
        $trainers = User::factory()->count(2)->create(['gym_id' => $gym->id]);
        $category = MembershipCategory::query()->create([
            'gym_id' => $gym->id,
            'active' => true,
            'slug' => 'trainer-update',
        ]);
        $schedule = ScheduleName::query()->create(['name' => 'Morning', 'status' => 1]);
        $payload = [
            'membership_category_id' => $category->id,
            'price' => 30000,
            'main_price' => 30000,
            'price_type' => 'percent',
            'price_value' => 0,
            'duration_type' => 'month',
            'duration_value' => 1,
            'guest_limit' => 0,
            'freeze_limit' => 0,
            'freeze_days_limit' => 0,
            'active' => true,
            'translations' => ['hy' => ['name' => 'Trainer update plan', 'description' => null]],
            'schedule_name_id' => $schedule->id,
            'trainers' => $trainers->map(fn (User $trainer): array => [
                'trainer_id' => $trainer->id,
                'price_type' => 'percent',
                'price_value' => 20,
            ])->all(),
        ];

        return [app(MembershipPlanService::class)->store($payload), $payload];
    }
}
