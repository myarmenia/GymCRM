<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_payable_assignments', function (Blueprint $table) {
            $table->id();
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
            $table->foreignId('salary_payout_refund_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_payout_item_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });

        DB::table('salary_payable_assignments')->insertUsing(
            [
                'gym_id',
                'payee_id',
                'source_type',
                'trainer_monthly_salary_id',
                'trainer_commission_id',
                'amount',
                'available_amount',
                'created_at',
                'updated_at',
            ],
            DB::table('trainer_monthly_salaries')
                ->join('person_memberships', 'person_memberships.id', '=', 'trainer_monthly_salaries.person_membership_id')
                ->select([
                    'person_memberships.gym_id',
                    'trainer_monthly_salaries.trainer_id',
                    DB::raw("'trainer_monthly_salary'"),
                    'trainer_monthly_salaries.id',
                    'trainer_monthly_salaries.trainer_commission_id',
                    'trainer_monthly_salaries.price',
                    DB::raw('CASE WHEN trainer_monthly_salaries.status IN (\'pending\', \'transfer\') AND trainer_monthly_salaries.salary_payout_id IS NULL THEN trainer_monthly_salaries.price ELSE 0 END'),
                    'trainer_monthly_salaries.created_at',
                    'trainer_monthly_salaries.updated_at',
                ])
        );

        DB::table('salary_payable_assignments')->insertUsing(
            [
                'gym_id',
                'payee_id',
                'source_type',
                'salesperson_commission_id',
                'amount',
                'available_amount',
                'created_at',
                'updated_at',
            ],
            DB::table('salesperson_commissions')
                ->join('membership_sales', 'membership_sales.id', '=', 'salesperson_commissions.membership_sale_id')
                ->select([
                    'membership_sales.gym_id',
                    'salesperson_commissions.salesperson_id',
                    DB::raw("'salesperson_commission'"),
                    'salesperson_commissions.id',
                    'salesperson_commissions.salary_amount',
                    DB::raw('CASE WHEN salesperson_commissions.status = \'pending\' AND salesperson_commissions.salary_payout_id IS NULL THEN salesperson_commissions.salary_amount ELSE 0 END'),
                    'salesperson_commissions.created_at',
                    'salesperson_commissions.updated_at',
                ])
        );

        DB::table('salary_payout_items')
            ->where('source_type', 'trainer_monthly_salary')
            ->update([
                'salary_payable_assignment_id' => DB::raw(
                    '(SELECT spa.id FROM salary_payable_assignments spa
                    WHERE spa.trainer_monthly_salary_id = salary_payout_items.trainer_monthly_salary_id
                    ORDER BY spa.id LIMIT 1)'
                ),
            ]);

        DB::table('salary_payout_items')
            ->where('source_type', 'salesperson_commission')
            ->update([
                'salary_payable_assignment_id' => DB::raw(
                    '(SELECT spa.id FROM salary_payable_assignments spa
                    WHERE spa.salesperson_commission_id = salary_payout_items.salesperson_commission_id
                    ORDER BY spa.id LIMIT 1)'
                ),
            ]);
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
