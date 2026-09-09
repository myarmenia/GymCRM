<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sheets', function (Blueprint $table) {
            $table->foreignId('person_membership_id')
                ->nullable()
                ->after('membership_plan_id')
                ->constrained('person_memberships')
                ->nullOnDelete();

            $table->index(
                ['person_membership_id', 'direction', 'date'],
                'attendance_membership_direction_date_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sheets', function (Blueprint $table) {
            $table->dropIndex('attendance_membership_direction_date_idx');
            $table->dropConstrainedForeignId('person_membership_id');
        });
    }
};
