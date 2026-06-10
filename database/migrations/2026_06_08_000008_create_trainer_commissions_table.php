<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainer_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('membership_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_membership_id')->constrained()->cascadeOnDelete();
            $table->enum('salary_type', ['fixed', 'percent']);
            $table->decimal('salary_value', 10, 2)->default(0);
            $table->decimal('salary_amount', 10, 2)->default(0);
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->dateTime('paid_at')->nullable();
            $table->boolean('is_kept')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_commissions');
    }
};
