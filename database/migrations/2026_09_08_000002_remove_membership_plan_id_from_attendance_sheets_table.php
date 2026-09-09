<?php

use App\Models\Person;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve pre-pivot history before removing the redundant plan foreign key.
        DB::statement(
            "INSERT INTO attendance_person_memberships (attendance_sheet_id, person_membership_id, created_at, updated_at)
             SELECT attendance.id,
                    (
                        SELECT memberships.id
                        FROM person_memberships AS memberships
                        WHERE memberships.person_id = attendance.relation_id
                          AND memberships.membership_plan_id = attendance.membership_plan_id
                          AND (attendance.gym_id IS NULL OR memberships.gym_id = attendance.gym_id)
                        ORDER BY memberships.id DESC
                        LIMIT 1
                    ),
                    attendance.created_at,
                    attendance.updated_at
             FROM attendance_sheets AS attendance
             WHERE attendance.relation_type = '".Person::class."'
               AND attendance.membership_plan_id IS NOT NULL
               AND NOT EXISTS (
                    SELECT 1 FROM attendance_person_memberships AS pivot
                    WHERE pivot.attendance_sheet_id = attendance.id
               )
               AND EXISTS (
                    SELECT 1 FROM person_memberships AS memberships
                    WHERE memberships.person_id = attendance.relation_id
                      AND memberships.membership_plan_id = attendance.membership_plan_id
                      AND (attendance.gym_id IS NULL OR memberships.gym_id = attendance.gym_id)
               )"
        );

        Schema::table('attendance_sheets', function ($table): void {
            $table->dropConstrainedForeignId('membership_plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sheets', function ($table): void {
            $table->foreignId('membership_plan_id')
                ->nullable()
                ->constrained('membership_plans')
                ->nullOnDelete();
        });
    }
};
