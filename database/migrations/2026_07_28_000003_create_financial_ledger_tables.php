<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('direction', ['income', 'expense']);
            $table->boolean('is_system')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->foreignId('card_type_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('direction', ['income', 'expense']);
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3)->default('AMD');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->uuid('transaction_group_id')->nullable();
            $table->foreignId('reversal_of_id')
                ->nullable()
                ->unique()
                ->constrained('financial_transactions')
                ->restrictOnDelete();
            $table->enum('status', ['posted', 'reversed'])->default('posted');
            $table->timestamp('occurred_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('description')->nullable();
            $table->string('reference')->nullable();
            $table->json('metadata')->nullable();
            $table->string('idempotency_key')->unique();
            $table->timestamps();

            $table->index(['gym_id', 'occurred_at']);
            $table->index(['gym_id', 'direction', 'occurred_at']);
            $table->index(['payment_method_id', 'occurred_at']);
            $table->index(['source_type', 'source_id']);
        });

        $now = now();
        DB::table('financial_categories')->insert([
            ['code' => 'membership_payment', 'name' => 'Աբոնեմենտի վճարում', 'direction' => 'income', 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'membership_refund', 'name' => 'Աբոնեմենտի վերադարձ', 'direction' => 'expense', 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'product_sale', 'name' => 'Ապրանքի վաճառք', 'direction' => 'income', 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'product_refund', 'name' => 'Ապրանքի վերադարձ', 'direction' => 'expense', 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'salary_payout', 'name' => 'Աշխատավարձի վճարում', 'direction' => 'expense', 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'salary_refund', 'name' => 'Աշխատավարձի վերադարձ', 'direction' => 'income', 'is_system' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'manual_income', 'name' => 'Այլ մուտք', 'direction' => 'income', 'is_system' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'manual_expense', 'name' => 'Այլ ելք', 'direction' => 'expense', 'is_system' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
        Schema::dropIfExists('financial_categories');
    }
};
