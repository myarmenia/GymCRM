<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_plan_payments', function (Blueprint $table) {
            $table->foreignId('parent_payment_id')
                ->nullable()
                ->after('membership_sale_id')
                ->constrained('membership_plan_payments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('membership_plan_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_payment_id');
        });
    }
};
