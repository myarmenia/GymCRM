<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hdm_configs', function (Blueprint $table): void {
            $table->softDeletes();
        });

        Schema::table('hdm_cashiers', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('hdm_cashiers', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });

        Schema::table('hdm_configs', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
