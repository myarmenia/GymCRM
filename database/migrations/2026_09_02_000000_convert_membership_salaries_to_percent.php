<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->enum('price_type', ['fixed', 'percent'])
                ->default('percent')
                ->change();
            $table->decimal('price_value', 12, 6)->default(0)->change();
        });

        Schema::table('membership_plan_trainers', function (Blueprint $table) {
            $table->enum('price_type', ['fixed', 'percent'])
                ->default('percent')
                ->change();
            $table->decimal('price_value', 12, 6)->default(0)->change();
        });

        Schema::table('trainer_commissions', function (Blueprint $table) {
            $table->decimal('salary_value', 12, 6)->default(0)->change();
        });

        Schema::table('salesperson_commissions', function (Blueprint $table) {
            $table->decimal('salary_value', 12, 6)->default(0)->change();
        });

        $this->convertMembershipPlans();
        $this->convertMembershipPlanTrainers();
    }

    public function down(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            $table->enum('price_type', ['fixed', 'percent'])
                ->default('fixed')
                ->change();
            $table->decimal('price_value', 12, 2)->default(0)->change();
        });

        Schema::table('membership_plan_trainers', function (Blueprint $table) {
            $table->enum('price_type', ['fixed', 'percent'])
                ->default('fixed')
                ->change();
            $table->decimal('price_value', 12, 2)->default(0)->change();
        });

        Schema::table('trainer_commissions', function (Blueprint $table) {
            $table->decimal('salary_value', 10, 2)->default(0)->change();
        });

        Schema::table('salesperson_commissions', function (Blueprint $table) {
            $table->decimal('salary_value', 10, 2)->default(0)->change();
        });
    }

    private function convertMembershipPlans(): void
    {
        DB::table('membership_plans')
            ->select(['id', 'price', 'price_type', 'price_value'])
            ->orderBy('id')
            ->chunkById(200, function ($plans): void {
                foreach ($plans as $plan) {
                    $percentage = $plan->price_type === 'fixed'
                        ? $this->percentageFromAmount($plan->price, $plan->price_value)
                        : $this->normalizePercentage($plan->price_value);

                    DB::table('membership_plans')
                        ->where('id', $plan->id)
                        ->update([
                            'price_type' => 'percent',
                            'price_value' => $percentage,
                        ]);
                }
            });
    }

    private function convertMembershipPlanTrainers(): void
    {
        DB::table('membership_plan_trainers')
            ->select([
                'id',
                'membership_plan_id',
                'price_type',
                'price_value',
                'total_price',
            ])
            ->orderBy('id')
            ->chunkById(200, function ($trainers): void {
                $prices = DB::table('membership_plans')
                    ->whereIn('id', $trainers->pluck('membership_plan_id')->unique())
                    ->pluck('price', 'id');

                foreach ($trainers as $trainer) {
                    $price = (float) ($prices[$trainer->membership_plan_id] ?? 0);
                    $fixedAmount = (float) ($trainer->total_price ?: $trainer->price_value);
                    $percentage = $trainer->price_type === 'fixed'
                        ? $this->percentageFromAmount($price, $fixedAmount)
                        : $this->normalizePercentage($trainer->price_value);

                    DB::table('membership_plan_trainers')
                        ->where('id', $trainer->id)
                        ->update([
                            'price_type' => 'percent',
                            'price_value' => $percentage,
                            'total_price' => $this->amount($price, $percentage),
                        ]);
                }
            });
    }

    private function percentageFromAmount(mixed $price, mixed $amount): float
    {
        $price = max((float) $price, 0);

        if ($price === 0.0) {
            return 0;
        }

        return $this->normalizePercentage((float) $amount / $price * 100);
    }

    private function normalizePercentage(mixed $percentage): float
    {
        return round(min(max((float) $percentage, 0), 100), 6);
    }

    private function amount(mixed $price, mixed $percentage): float
    {
        return round(max((float) $price, 0) * (float) $percentage / 100, 2);
    }
};
