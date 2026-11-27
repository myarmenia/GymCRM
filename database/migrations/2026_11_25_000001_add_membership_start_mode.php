<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gym_settings') && ! Schema::hasColumn('gym_settings', 'membership_start_mode')) {
            Schema::table('gym_settings', function (Blueprint $table): void {
                $table->string('membership_start_mode')->default('sale_date');
            });
        }

        if (! Schema::hasColumn('person_memberships', 'activation_start_mode')) {
            Schema::table('person_memberships', function (Blueprint $table): void {
                $table->string('activation_start_mode')->default('sale_date');
            });
        }
    }

    public function down(): void
    {
        Schema::table('person_memberships', fn (Blueprint $table) => $table->dropColumn('activation_start_mode'));
        if (Schema::hasTable('gym_settings') && Schema::hasColumn('gym_settings', 'membership_start_mode')) {
            Schema::table('gym_settings', fn (Blueprint $table) => $table->dropColumn('membership_start_mode'));
        }
    }
};
