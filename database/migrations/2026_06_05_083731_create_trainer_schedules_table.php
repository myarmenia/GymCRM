<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainer_schedules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('schedule_name_id')
                ->constrained('schedule_names')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['user_id', 'schedule_name_id'], 'trainer_schedules_user_schedule_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_schedules');
    }
};
