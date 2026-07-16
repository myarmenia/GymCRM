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
        Schema::create('hdm_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hdm_config_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hdm_cashier_id')->nullable()->constrained()->nullOnDelete();

            $table->nullableMorphs('operationable'); //membership sale, shop

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // sale, refund, x_report, z_report, cash_in, cash_out
            $table->string('transaction_type');

            $table->string('cashier_number')->nullable();

            // Номер документа HDM
            $table->string('crn')->nullable();

            // Порядковый номер операции
            $table->string('rseq')->nullable();

            // Для возвратов ссылка на исходную операцию
            $table->foreignId('parent_operation_id')->nullable()->constrained('hdm_operations')->nullOnDelete();

            $table->enum('status', [
                'pending',
                'success',
                'failed',
            ])->default('pending');

            $table->json('request')->nullable();
            $table->json('response')->nullable();

            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index('transaction_type');
            $table->index('crn');
            $table->index('rseq');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hdm_operations');
    }
};
