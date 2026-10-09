<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_sales', function (Blueprint $table): void {
            // Keep the previous eight integer digits while allowing eight
            // decimal places for precise percentage values.
            $table->decimal('discount_value', 16, 8)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('membership_sales', function (Blueprint $table): void {
            $table->decimal('discount_value', 10, 2)->nullable()->change();
        });
    }
};
