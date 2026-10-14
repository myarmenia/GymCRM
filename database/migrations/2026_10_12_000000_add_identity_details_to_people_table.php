<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            $table->string('passport_series', 100)->nullable();
            $table->string('passport_issued_by')->nullable();
            $table->date('passport_issued_at')->nullable();
            $table->string('address', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table): void {
            $table->dropColumn(['passport_series', 'passport_issued_by', 'passport_issued_at', 'address']);
        });
    }
};
