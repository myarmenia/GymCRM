<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainer_commissions', function (Blueprint $table) {
            $table->decimal('cancelled_unearned_amount', 10, 2)
                ->default(0)
                ->after('salary_installment_count');
            $table->timestamp('generation_stopped_at')
                ->nullable()
                ->after('cancelled_unearned_amount');
            $table->string('generation_stopped_reason')
                ->nullable()
                ->after('generation_stopped_at');
            $table->index('generation_stopped_at');
        });
    }

    public function down(): void
    {
        Schema::table('trainer_commissions', function (Blueprint $table) {
            $table->dropIndex(['generation_stopped_at']);
            $table->dropColumn([
                'cancelled_unearned_amount',
                'generation_stopped_at',
                'generation_stopped_reason',
            ]);
        });
    }
};
