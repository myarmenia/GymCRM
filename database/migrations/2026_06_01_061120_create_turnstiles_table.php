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
        Schema::create('turnstiles', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('gym_id')->unsigned()->nullable();
            $table->foreign('gym_id')->references('id')->on('gyms')->onDelete('cascade');
            $table->string('mac');
            $table->string('direction')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('turnstiles');
    }
};
