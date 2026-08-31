<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_payouts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('version')->default(1);
            $table->foreignId('gym_id')->constrained()->restrictOnDelete();
            $table->foreignId('payee_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('AMD');
            $table->enum('status', ['paid', 'voided'])->default('paid');
            $table->timestamp('paid_at');
            $table->foreignId('paid_by')->constrained('users')->restrictOnDelete();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->timestamps();

            $table->index(['gym_id', 'status', 'paid_at']);
            $table->index(['payee_id', 'status', 'paid_at']);
        });

        Schema::create('salary_payout_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('version')->default(1);
            $table->foreignId('salary_payout_id')->constrained()->cascadeOnDelete();
            $table->enum('source_type', ['trainer_monthly_salary', 'salesperson_commission']);
            $table->foreignId('trainer_monthly_salary_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('salesperson_commission_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('source_status', 32);
            $table->date('earned_for_date')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'trainer_monthly_salary_id']);
            $table->index(['source_type', 'salesperson_commission_id']);
        });

        Schema::table('trainer_monthly_salaries', function (Blueprint $table) {
            $table->foreignId('salary_payout_id')
                ->nullable()
                ->after('status')
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('salesperson_commissions', function (Blueprint $table) {
            $table->foreignId('salary_payout_id')
                ->nullable()
                ->after('paid_at')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('salesperson_commissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('salary_payout_id');
        });

        Schema::table('trainer_monthly_salaries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('salary_payout_id');
        });

        Schema::dropIfExists('salary_payout_items');
        Schema::dropIfExists('salary_payouts');
    }
};
