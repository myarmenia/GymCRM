<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GymSettingsMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.gym_settings_migration_test', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        DB::purge('gym_settings_migration_test');
        DB::setDefaultConnection('gym_settings_migration_test');

        Schema::create('gyms', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->softDeletes();
        });
        Schema::create('person_memberships', fn (Blueprint $table) => $table->id());
        DB::table('gyms')->insert([
            ['id' => 1, 'name' => 'Active gym', 'deleted_at' => null],
            ['id' => 2, 'name' => 'Deleted gym', 'deleted_at' => '2026-11-20 10:00:00'],
        ]);
    }

    public function test_clean_deploy_runs_all_gym_settings_migrations_in_order(): void
    {
        $this->migration('2026_11_25_000000_create_gym_settings_table')->up();
        $this->migration('2026_11_25_000001_add_membership_start_mode')->up();
        $settingUuid = DB::table('gym_settings')->where('gym_id', 1)->value('uuid');
        $this->migration('2026_11_25_000002_restore_missing_gym_settings_table')->up();
        $this->migration('2026_11_26_000000_remove_settings_for_deleted_gyms')->up();

        $this->assertSame($settingUuid, DB::table('gym_settings')->where('gym_id', 1)->value('uuid'));
        $this->assertDatabaseHas('gym_settings', [
            'gym_id' => 1,
            'membership_selection_mode' => 'auto_up_to_two',
            'membership_start_mode' => 'sale_date',
        ]);
        $this->assertDatabaseMissing('gym_settings', ['gym_id' => 2]);
        $this->assertTrue(Schema::hasColumn('person_memberships', 'activation_start_mode'));
    }

    public function test_missing_table_with_create_migration_already_recorded_is_repaired(): void
    {
        $this->migration('2026_11_25_000001_add_membership_start_mode')->up();
        $this->migration('2026_11_25_000002_restore_missing_gym_settings_table')->up();
        DB::table('gym_settings')->insert([
            'gym_id' => 2,
            'uuid' => 'b1647337-441a-44a7-925a-4bd552b2e639',
            'version' => 1,
            'membership_selection_mode' => 'auto_up_to_two',
            'membership_start_mode' => 'sale_date',
        ]);
        $this->migration('2026_11_26_000000_remove_settings_for_deleted_gyms')->up();

        $this->assertDatabaseHas('gym_settings', ['gym_id' => 1]);
        $this->assertDatabaseMissing('gym_settings', ['gym_id' => 2]);
        $this->assertTrue(Schema::hasColumn('person_memberships', 'activation_start_mode'));
    }

    public function test_interrupted_initial_backfill_can_resume_without_changing_existing_settings(): void
    {
        $create = $this->migration('2026_11_25_000000_create_gym_settings_table');
        $create->up();
        $settingUuid = DB::table('gym_settings')->where('gym_id', 1)->value('uuid');
        DB::table('gyms')->insert(['id' => 3, 'name' => 'Another active gym', 'deleted_at' => null]);

        $create->up();

        $this->assertSame($settingUuid, DB::table('gym_settings')->where('gym_id', 1)->value('uuid'));
        $this->assertDatabaseHas('gym_settings', ['gym_id' => 3]);
        $this->assertDatabaseCount('gym_settings', 2);
    }

    private function migration(string $name): object
    {
        return require database_path("migrations/{$name}.php");
    }
}
