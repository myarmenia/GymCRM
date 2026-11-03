<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_notes', function (Blueprint $table) {
            $table->foreignId('writer_id')->nullable()->constrained('users')->nullOnDelete();
        });

        DB::table('contact_notes')->whereNull('writer_id')->update(['writer_id' => DB::raw('user_id')]);
    }

    public function down(): void
    {
        Schema::table('contact_notes', fn (Blueprint $table) => $table->dropConstrainedForeignId('writer_id'));
    }
};
