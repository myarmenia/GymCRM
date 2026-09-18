<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('gyms', 'entry_code_type')) {
            return;
        }

        DB::table('gyms')
            ->where(function ($query): void {
                $query->whereNull('entry_code_type')
                    ->orWhereNotIn('entry_code_type', ['rfId', 'FaceId']);
            })
            ->update(['entry_code_type' => 'rfId']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE `gyms` MODIFY `entry_code_type` VARCHAR(255) NOT NULL DEFAULT 'rfId'"
            );
        }
    }

    public function down(): void
    {
        // The repaired values are valid domain data and must not be reverted to null.
    }
};
