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
        Schema::create('hdm_configs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('version')->default(1);
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();

            $table->string('name'); // Ресепшен, Бар, Ресторан и т.д.

            $table->string('ip');
            $table->unsignedInteger('port');

            $table->string('password');

            $table->boolean('status')->default(true);

            $table->timestamps();

            $table->index(['gym_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hdm_configs');
    }
};
