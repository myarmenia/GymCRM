<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MembershipSaleHdmMigrationTest extends TestCase
{
    public function test_only_unambiguous_history_is_backfilled_without_changing_versions(): void
    {
        Schema::create('membership_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('version')->default(7);
        });
        Schema::create('membership_plan_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('membership_sale_id');
            $table->boolean('is_hdm');
            $table->softDeletes();
        });
        foreach ([1, 2, 3, 4] as $id) {
            DB::table('membership_sales')->insert(['id' => $id]);
        }
        foreach ([[1, true, null], [1, true, null], [2, false, null], [3, true, null], [3, false, '2026-09-01 00:00:00']] as [$saleId, $mode, $deletedAt]) {
            DB::table('membership_plan_payments')->insert([
                'membership_sale_id' => $saleId,
                'is_hdm' => $mode,
                'deleted_at' => $deletedAt,
            ]);
        }

        $migration = require database_path('migrations/2026_09_08_000001_add_is_hdm_to_membership_sales_table.php');
        $migration->up();
        $sales = DB::table('membership_sales')->orderBy('id')->get();
        $this->assertSame(1, $sales[0]->is_hdm);
        $this->assertSame(0, $sales[1]->is_hdm);
        $this->assertNull($sales[2]->is_hdm);
        $this->assertNull($sales[3]->is_hdm);
        $this->assertSame([7, 7, 7, 7], $sales->pluck('version')->all());
        $migration->down();
        $this->assertFalse(Schema::hasColumn('membership_sales', 'is_hdm'));
        Schema::drop('membership_plan_payments');
        Schema::drop('membership_sales');
    }
}
