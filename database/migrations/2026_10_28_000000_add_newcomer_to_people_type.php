<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->enum('type', ['visitor', 'guest', 'newcomer'])->default('visitor')->change();
        });
    }

    public function down(): void
    {
        DB::table('people')->where('type', 'newcomer')->update(['type' => 'visitor']);

        Schema::table('people', function (Blueprint $table) {
            $table->enum('type', ['visitor', 'guest'])->default('visitor')->change();
        });
    }
};
