<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_sales', function (Blueprint $table) {
            // Null marks legacy sales whose mode cannot be determined safely.
            $table->boolean('is_hdm')->nullable();
        });

        DB::table('membership_sales')->orderBy('id')->chunkById(500, function ($sales) {
            $modes = DB::table('membership_plan_payments')
                ->whereIn('membership_sale_id', $sales->pluck('id'))
                ->select('membership_sale_id')
                ->selectRaw('MIN(is_hdm) as min_mode, MAX(is_hdm) as max_mode')
                ->groupBy('membership_sale_id')->get();

            foreach ($modes as $mode) {
                if ($mode->min_mode !== null && (int) $mode->min_mode === (int) $mode->max_mode) {
                    DB::table('membership_sales')->where('id', $mode->membership_sale_id)
                        ->update(['is_hdm' => (bool) $mode->min_mode]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('membership_sales', fn (Blueprint $table) => $table->dropColumn('is_hdm'));
    }
};
