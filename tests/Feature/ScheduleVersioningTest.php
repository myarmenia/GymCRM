<?php

namespace Tests\Feature;

use App\Models\ScheduleName;
use App\Repositories\ScheduleName\ScheduleNameRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ScheduleVersioningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'schedule_test');
        config()->set('database.connections.schedule_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::purge('schedule_test');
        DB::setDefaultConnection('schedule_test');
        Schema::connection('schedule_test')->create('schedule_names', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->unsignedBigInteger('version')->default(1);
            $table->string('name');
            $table->integer('status')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::purge('schedule_test');

        parent::tearDown();
    }

    public function test_schedule_edit_advances_version_even_when_only_details_changed(): void
    {
        $repository = new ScheduleNameRepository(new ScheduleName);
        $schedule = $repository->createScheduleName('Evening', 1);

        $repository->updateScheduleName($schedule->id, 'Evening', 1);

        $this->assertSame(2, $schedule->fresh()->version);
    }
}
