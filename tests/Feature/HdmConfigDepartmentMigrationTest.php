<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HdmConfigDepartmentMigrationTest extends TestCase
{
    public function test_existing_devices_keep_their_identity_and_version_and_receive_department_one(): void
    {
        Schema::create('hdm_configs', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid');
            $table->unsignedBigInteger('version');
        });
        DB::table('hdm_configs')->insert(['id' => 42, 'uuid' => 'existing-device', 'version' => 8]);
        $migration = require database_path('migrations/2026_11_27_000000_add_dep_to_hdm_configs_table.php');

        $migration->up();

        $this->assertDatabaseHas('hdm_configs', ['id' => 42, 'uuid' => 'existing-device', 'version' => 8, 'dep' => 1]);
        $migration->down();
        $this->assertFalse(Schema::hasColumn('hdm_configs', 'dep'));
        $this->assertDatabaseHas('hdm_configs', ['id' => 42, 'uuid' => 'existing-device', 'version' => 8]);
    }
}
