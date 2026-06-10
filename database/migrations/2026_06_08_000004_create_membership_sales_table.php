<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_plan_id')->constrained()->cascadeOnDelete();
            $table->decimal('total_price', 10, 2)->default(0);
            $table->enum('discount_type', ['fixed', 'percent'])->nullable();
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('final_price', 10, 2)->default(0);
            $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'refunded', 'cancelled'])->default('unpaid');
            $table->text('notes')->nullable();
            $table->boolean('is_hdm')->default(false);
            $table->decimal('discount_membership_amount', 10, 2)->default(0);
            $table->dateTime('sold_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_sales');
    }
};
