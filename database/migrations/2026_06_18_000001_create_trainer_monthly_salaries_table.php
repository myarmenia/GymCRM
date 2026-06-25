<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainer_monthly_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('person_membership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trainer_commission_id')->constrained()->cascadeOnDelete();
            $table->date('salary_month');
            $table->decimal('price', 10, 2)->default(0);
            $table->enum('status', ['pending', 'paid', 'transfer', 'cancel', 'reject'])->default('pending');
            $table->timestamps();

            $table->unique([
                'trainer_id',
                'person_membership_id',
                'trainer_commission_id',
                'salary_month',
            ], 'trainer_monthly_salary_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_monthly_salaries');
    }
};
