<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_plan_schedules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('membership_plan_id')
                ->constrained('membership_plans')
                ->cascadeOnDelete();

            $table->foreignId('schedule_id')
                ->constrained('schedule_names')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['membership_plan_id', 'schedule_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_plan_schedules');
    }
};