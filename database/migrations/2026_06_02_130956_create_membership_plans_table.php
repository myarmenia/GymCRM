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
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gym_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2)->default(0);
            $table->enum('duration_type', [
                'day',
                'month',
                'year',
                'visit',
                'period',
            ]);
            $table->integer('duration_value')->nullable();
            $table->integer('visits_limit')->nullable();
            $table->integer('guest_limit')->default(0);
            $table->integer('freeze_limit')->default(0);
            $table->boolean('active')->default(true);

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_plans');
    }
};
