<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_payable_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('version')->default(1);
            $table->foreignId('gym_id')->constrained()->restrictOnDelete();
            $table->foreignId('payee_id')->constrained('users')->restrictOnDelete();
            $table->enum('source_type', ['trainer_monthly_salary', 'salesperson_commission']);
            $table->foreignId('trainer_monthly_salary_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('salesperson_commission_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('trainer_commission_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('parent_assignment_id')
                ->nullable()
                ->constrained('salary_payable_assignments')
                ->restrictOnDelete();
            $table->string('root_key')->nullable()->unique();
            $table->decimal('amount', 12, 2);
            $table->decimal('available_amount', 12, 2);
            $table->timestamps();

            $table->index(['payee_id', 'gym_id', 'available_amount'], 'salary_assignment_payable_idx');
            $table->index(['source_type', 'trainer_monthly_salary_id'], 'salary_assignment_trainer_idx');
            $table->index(['source_type', 'salesperson_commission_id'], 'salary_assignment_salesperson_idx');
        });

        Schema::table('salary_payout_items', function (Blueprint $table) {
            $table->foreignId('salary_payable_assignment_id')
                ->nullable()
                ->after('salary_payout_id')
                ->constrained()
                ->restrictOnDelete();
        });

        Schema::create('salary_payable_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('version')->default(1);
            $table->foreignId('from_assignment_id')
                ->constrained('salary_payable_assignments')
                ->restrictOnDelete();
            $table->foreignId('to_assignment_id')
                ->constrained('salary_payable_assignments')
                ->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamp('transferred_at');
            $table->foreignId('transferred_by')->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('salary_payout_refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('version')->default(1);
            $table->foreignId('salary_payout_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamp('refunded_at');
            $table->foreignId('refunded_by')->constrained('users')->restrictOnDelete();
            $table->string('reference')->nullable();
            $table->text('reason');
            $table->timestamps();

            $table->index(['salary_payout_id', 'refunded_at']);
        });

        Schema::create('salary_payout_refund_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('version')->default(1);
            $table->foreignId('salary_payout_refund_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_payout_item_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('salary_payout_refund_items');
        Schema::dropIfExists('salary_payout_refunds');
        Schema::dropIfExists('salary_payable_transfers');

        Schema::table('salary_payout_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('salary_payable_assignment_id');
        });

        Schema::dropIfExists('salary_payable_assignments');
    }
};
