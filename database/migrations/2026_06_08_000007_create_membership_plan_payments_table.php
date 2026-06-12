<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_plan_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_sale_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2)->default(0);
            $table->foreignId('payment_method_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_type_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['unpaid', 'pending', 'paid', 'cancelled'])->default('pending');
            $table->enum('type', ['payment', 'refund'])->default('payment');
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_plan_payments');
    }
};
