<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salesperson_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salesperson_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('membership_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_membership_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('membership_plan_id')->constrained()->cascadeOnDelete();
            $table->enum('salary_type', ['fixed', 'percent']);
            $table->decimal('salary_value', 10, 2)->default(0);
            $table->decimal('salary_amount', 10, 2)->default(0);
            $table->decimal('sale_amount', 12, 2)->default(0);
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->dateTime('paid_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salesperson_commissions');
    }
};
