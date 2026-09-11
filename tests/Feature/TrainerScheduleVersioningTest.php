<?php

namespace Tests\Feature;

use App\Models\ScheduleName;
use App\Models\TrainerSchedule;
use App\Models\TrainerSessionDuration;
use App\Models\TrainerSessionDurationSlot;
use App\Models\User;
use App\Repositories\TrainerSchedule\TrainerScheduleRepository;
use App\Services\Trainer\TrainerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainerScheduleVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_child_edits_soft_delete_and_restore_advance_the_root_version(): void
    {
        $trainer = User::query()->create([
            'name' => 'Trainer',
            'surname' => 'Test',
            'email' => 'trainer-schedule-version@example.com',
            'password' => 'password',
        ]);
        $schedule = ScheduleName::query()->create([
            'name' => 'Morning',
            'status' => 1,
        ]);
        $repository = new TrainerScheduleRepository(new TrainerSchedule);
        /** @var TrainerSchedule $root */
        $root = $repository->firstOrCreate($trainer->id, $schedule->id);
        $duration = TrainerSessionDuration::query()->create([
            'trainer_schedule_id' => $root->id,
            'title' => 'Individual',
            'minutes' => 60,
            'type' => 'individual',
            'price' => 5000,
        ]);
        $slot = TrainerSessionDurationSlot::query()->create([
            'session_duration_id' => $duration->id,
            'week_day' => 'Monday',
            'start_time' => '09:00',
            'end_time' => '10:00',
        ]);

        app(TrainerService::class)->saveTrainerScheduleData($trainer->id, [
            'schedule_names' => [$schedule->id],
            'session_durations' => [[
                'id' => $duration->id,
                'schedule_name_id' => $schedule->id,
                'title' => 'Updated individual',
                'minutes' => 75,
                'type' => 'individual',
                'price' => 6000,
                'slots' => [[
                    'id' => $slot->id,
                    'week_day' => 'Tuesday',
                    'start_time' => '10:00',
                    'end_time' => '11:15',
                ]],
            ]],
        ]);

        $this->assertSame(2, $root->fresh()->version);
        $this->assertSame(2, $duration->fresh()->version);
        $this->assertSame(2, $slot->fresh()->version);

        $repository->deleteMissingSchedules($trainer->id, collect());
        $deletedRoot = TrainerSchedule::query()->withTrashed()->findOrFail($root->id);

        $this->assertTrue($deletedRoot->trashed());
        $this->assertSame(3, $deletedRoot->version);
        $this->assertSame(0, TrainerSessionDuration::query()->count());
        $this->assertSame(0, TrainerSessionDurationSlot::query()->count());
        $this->assertFalse($trainer->scheduleNames()->whereKey($schedule->id)->exists());

        /** @var TrainerSchedule $restoredRoot */
        $restoredRoot = $repository->firstOrCreate($trainer->id, $schedule->id);

        $this->assertFalse($restoredRoot->trashed());
        $this->assertSame(4, $restoredRoot->version);
        $this->assertTrue($trainer->scheduleNames()->whereKey($schedule->id)->exists());
    }
}
