<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\GymSchedule;
use App\Models\Role;
use App\Models\ScheduleName;
use App\Models\TrainerSchedule;
use App\Models\User;
use App\Services\Memberships\MembershipPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipPlanScheduleActiveTrainerTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_options_only_include_active_trainers_on_the_selected_schedule(): void
    {
        config()->set('sync.enabled', false);

        $gym = Gym::query()->create(['name' => 'Main gym']);
        $adminRole = Role::query()->create([
            'name' => 'admin',
            'guard_name' => 'web',
            'g_name' => 'admin',
        ]);
        $trainerRole = Role::query()->forceCreate([
            'id' => 7,
            'name' => 'trainer',
            'guard_name' => 'web',
            'g_name' => 'trainer',
        ]);

        $admin = User::factory()->create(['gym_id' => $gym->id, 'active' => true]);
        $admin->assignRole($adminRole);
        $this->actingAs($admin);

        $schedule = ScheduleName::query()->create(['name' => 'Morning', 'status' => true]);
        GymSchedule::query()->create(['gym_id' => $gym->id, 'schedule_name_id' => $schedule->id]);

        $activeTrainer = User::factory()->create(['gym_id' => $gym->id, 'active' => true]);
        $inactiveTrainer = User::factory()->create(['gym_id' => $gym->id, 'active' => false]);
        foreach ([$activeTrainer, $inactiveTrainer] as $trainer) {
            $trainer->assignRole($trainerRole);
            TrainerSchedule::query()->create([
                'user_id' => $trainer->id,
                'schedule_name_id' => $schedule->id,
            ]);
        }

        $scheduleOptions = app(MembershipPlanService::class)
            ->getCreateData()['scheduleNames']
            ->firstWhere('id', $schedule->id);

        $this->assertNotNull($scheduleOptions);
        $this->assertSame([$activeTrainer->id], $scheduleOptions->trainers->pluck('id')->all());
    }
}
