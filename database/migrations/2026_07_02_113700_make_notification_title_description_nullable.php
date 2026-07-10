<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
            $table->text('description')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('notifications')->whereNull('title')->update(['title' => '']);
        DB::table('notifications')->whereNull('description')->update(['description' => '']);

        Schema::table('notifications', function (Blueprint $table) {
            $table->string('title')->nullable(false)->change();
            $table->text('description')->nullable(false)->change();
        });
    }
};
