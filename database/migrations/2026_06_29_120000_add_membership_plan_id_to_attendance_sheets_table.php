<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sheets', function (Blueprint $table) {
            $table->foreignId('membership_plan_id')
                ->nullable()
                ->after('relation_id')
                ->constrained('membership_plans')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sheets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('membership_plan_id');
        });
    }
};
