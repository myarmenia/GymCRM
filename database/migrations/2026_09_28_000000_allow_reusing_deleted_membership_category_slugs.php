<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ACTIVE_SLUG_INDEX = 'membership_categories_active_slug_unique';

    public function up(): void
    {
        Schema::table('membership_categories', function (Blueprint $table): void {
            $table->dropUnique('membership_categories_slug_unique');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('membership_categories', function (Blueprint $table): void {
                $table->string('active_slug')
                    ->nullable()
                    ->storedAs('CASE WHEN deleted_at IS NULL THEN slug ELSE NULL END');
                $table->unique('active_slug', self::ACTIVE_SLUG_INDEX);
            });

            return;
        }

        DB::statement(
            'CREATE UNIQUE INDEX '.self::ACTIVE_SLUG_INDEX
            .' ON membership_categories (slug) WHERE deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('membership_categories', function (Blueprint $table): void {
                $table->dropUnique(self::ACTIVE_SLUG_INDEX);
                $table->dropColumn('active_slug');
            });
        } else {
            DB::statement('DROP INDEX '.self::ACTIVE_SLUG_INDEX);
        }

        Schema::table('membership_categories', function (Blueprint $table): void {
            $table->unique('slug');
        });
    }
};
