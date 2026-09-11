<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainer_commissions', function (Blueprint $table) {
            $table->enum('calculation_mode', ['prepaid', 'postpaid'])
                ->default('prepaid')
                ->after('is_kept');
            $table->decimal('initial_salary_amount', 10, 2)
                ->nullable()
                ->after('salary_amount');
            $table->unsignedSmallInteger('salary_start_installment')
                ->default(1)
                ->after('initial_salary_amount');
            $table->unsignedSmallInteger('salary_installment_count')
                ->nullable()
                ->after('salary_start_installment');
        });

        DB::table('trainer_commissions')
            ->whereNull('initial_salary_amount')
            ->update(['initial_salary_amount' => DB::raw('salary_amount')]);

        // The original composite unique index may be the supporting index for
        // the trainer foreign key in MySQL. Add a dedicated index first so the
        // unique index can be replaced safely.
        Schema::table('trainer_monthly_salaries', function (Blueprint $table) {
            $table->index('trainer_id', 'trainer_monthly_salaries_trainer_fk_idx');
        });

        Schema::table('trainer_monthly_salaries', function (Blueprint $table) {
            $table->dropUnique('trainer_monthly_salary_unique');
            $table->unsignedSmallInteger('installment_number')->nullable()->after('salary_month');
            $table->date('period_start')->nullable()->after('installment_number');
            $table->date('period_end')->nullable()->after('period_start');
            $table->string('cancellation_reason')->nullable()->after('status');
            $table->unique(
                ['trainer_commission_id', 'installment_number'],
                'trainer_salary_commission_installment_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('trainer_monthly_salaries', function (Blueprint $table) {
            $table->dropUnique('trainer_salary_commission_installment_unique');
            $table->dropColumn([
                'installment_number',
                'period_start',
                'period_end',
                'cancellation_reason',
            ]);
            $table->unique([
                'trainer_id',
                'person_membership_id',
                'trainer_commission_id',
                'salary_month',
            ], 'trainer_monthly_salary_unique');
        });

        Schema::table('trainer_monthly_salaries', function (Blueprint $table) {
            $table->dropIndex('trainer_monthly_salaries_trainer_fk_idx');
        });

        Schema::table('trainer_commissions', function (Blueprint $table) {
            $table->dropColumn([
                'calculation_mode',
                'initial_salary_amount',
                'salary_start_installment',
                'salary_installment_count',
            ]);
        });
    }
};
