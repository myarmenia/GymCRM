<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table): void {
            $table->string('entry_code_type')
                ->default('rfId')
                ->after('logo');
        });
    }

    public function down(): void
    {
        Schema::table('gyms', function (Blueprint $table): void {
            $table->dropColumn('entry_code_type');
        });
    }
};
