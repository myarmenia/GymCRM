<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trainer_session_duration_slots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('session_duration_id')
                ->constrained('trainer_session_durations')
                ->cascadeOnDelete();

            $table->string('week_day');
            $table->time('start_time');
            $table->time('end_time');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainer_session_duration_slots');
    }
};
