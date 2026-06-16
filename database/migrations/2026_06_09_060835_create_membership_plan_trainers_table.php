<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_plan_trainers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('membership_plan_id')
                ->constrained('membership_plans')
                ->cascadeOnDelete();

            $table->foreignId('trainer_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('price_type', ['fixed', 'percent'])->default('fixed');

            $table->decimal('price_value', 12, 2)->default(0);

            $table->decimal('total_price', 12, 2)->default(0);

            $table->timestamps();

            $table->unique(['membership_plan_id', 'trainer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_plan_trainers');
    }
};