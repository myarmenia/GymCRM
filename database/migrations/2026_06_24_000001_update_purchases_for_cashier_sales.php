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
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropForeign(['people_id']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->unsignedBigInteger('people_id')->nullable()->change();
            $table->foreign('people_id')->references('id')->on('people')->nullOnDelete();
        });

        Schema::table('purchases', function (Blueprint $table) {
            if (! Schema::hasColumn('purchases', 'warehouse_id')) {
                $table->foreignId('warehouse_id')
                    ->nullable()
                    ->after('gym_id')
                    ->constrained('warehouses')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('purchases', 'discount_percent')) {
                $table->decimal('discount_percent', 5, 2)
                    ->default(0)
                    ->after('tax');
            }

            if (! Schema::hasColumn('purchases', 'discount_amount')) {
                $table->decimal('discount_amount', 10, 2)
                    ->default(0)
                    ->after('discount_percent');
            }

            if (! Schema::hasColumn('purchases', 'cash_received')) {
                $table->decimal('cash_received', 10, 2)
                    ->nullable()
                    ->after('total');
            }

            if (! Schema::hasColumn('purchases', 'change_amount')) {
                $table->decimal('change_amount', 10, 2)
                    ->default(0)
                    ->after('cash_received');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            if (Schema::hasColumn('purchases', 'warehouse_id')) {
                $table->dropConstrainedForeignId('warehouse_id');
            }

            if (Schema::hasColumn('purchases', 'discount_percent')) {
                $table->dropColumn('discount_percent');
            }

            if (Schema::hasColumn('purchases', 'discount_amount')) {
                $table->dropColumn('discount_amount');
            }

            if (Schema::hasColumn('purchases', 'cash_received')) {
                $table->dropColumn('cash_received');
            }

            if (Schema::hasColumn('purchases', 'change_amount')) {
                $table->dropColumn('change_amount');
            }
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropForeign(['people_id']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->unsignedBigInteger('people_id')->nullable(false)->change();
            $table->foreign('people_id')->references('id')->on('people')->cascadeOnDelete();
        });
    }
};
