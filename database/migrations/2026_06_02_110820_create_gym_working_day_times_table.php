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
        Schema::create('gym_working_day_times', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('gym_id')->unsigned();
            $table->foreign('gym_id')->references('id')->on('gyms')->onDelete('cascade');
            $table->string('week_day')->nullable();
            $table->time('day_start_time')->nullable();
            $table->time('day_end_time')->nullable();
            $table->time('break_start_time')->nullable();
            $table->time('break_end_time')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gym_working_day_times');
    }
};
