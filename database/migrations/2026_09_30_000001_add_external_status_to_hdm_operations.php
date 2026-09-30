<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE hdm_operations MODIFY status ENUM('pending', 'success', 'failed', 'external') NOT NULL DEFAULT 'pending'");
        } else {
            Schema::table('hdm_operations', function (Blueprint $table): void {
                $table->enum('status', ['pending', 'success', 'failed', 'external'])
                    ->default('pending')
                    ->change();
            });
        }
    }

    public function down(): void
    {
        DB::table('hdm_operations')
            ->where('status', 'external')
            ->update(['status' => 'failed']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE hdm_operations MODIFY status ENUM('pending', 'success', 'failed') NOT NULL DEFAULT 'pending'");
        } else {
            Schema::table('hdm_operations', function (Blueprint $table): void {
                $table->enum('status', ['pending', 'success', 'failed'])
                    ->default('pending')
                    ->change();
            });
        }
    }
};
