<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discounts', function (Blueprint $table): void {
            $table->foreignId('gym_id')
                ->nullable()
                ->after('version')
                ->constrained()
                ->nullOnDelete();
        });

        DB::table('discounts')
            ->orderBy('id')
            ->eachById(function (object $discount): void {
                $gymId = DB::table('discount_membership_plan as discount_plans')
                    ->join('membership_plans', 'membership_plans.id', '=', 'discount_plans.membership_plan_id')
                    ->where('discount_plans.discount_id', $discount->id)
                    ->whereNotNull('membership_plans.gym_id')
                    ->orderBy('membership_plans.id')
                    ->value('membership_plans.gym_id');

                if ($gymId !== null) {
                    DB::table('discounts')
                        ->where('id', $discount->id)
                        ->update(['gym_id' => $gymId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('discounts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('gym_id');
        });
    }
};
