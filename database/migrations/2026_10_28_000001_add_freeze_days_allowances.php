<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_plans', function (Blueprint $table): void {
            $table->unsignedInteger('freeze_days_limit')->nullable()->after('freeze_limit');
        });

        Schema::table('person_memberships', function (Blueprint $table): void {
            $table->unsignedInteger('freeze_days_used')->nullable()->after('freeze_used');
            $table->unsignedInteger('freeze_days_left')->nullable()->after('freeze_left');
        });

        Schema::table('person_membership_freezes', function (Blueprint $table): void {
            $table->unsignedInteger('duration_days')->nullable()->after('end_date');
            $table->unsignedInteger('counted_days')->nullable()->after('duration_days');
        });
    }

    public function down(): void
    {
        Schema::table('person_membership_freezes', fn (Blueprint $table) => $table->dropColumn(['duration_days', 'counted_days']));
        Schema::table('person_memberships', fn (Blueprint $table) => $table->dropColumn(['freeze_days_used', 'freeze_days_left']));
        Schema::table('membership_plans', fn (Blueprint $table) => $table->dropColumn('freeze_days_limit'));
    }
};
