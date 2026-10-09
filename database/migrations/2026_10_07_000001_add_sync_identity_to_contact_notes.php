<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_notes', function (Blueprint $table) {
            $table->uuid('uuid')->nullable();
            $table->unsignedBigInteger('version')->default(1);
        });

        DB::table('contact_notes')->whereNull('uuid')->orderBy('id')->chunkById(500, function ($notes) {
            foreach ($notes as $note) {
                DB::table('contact_notes')->where('id', $note->id)->update(['uuid' => (string) Str::uuid()]);
            }
        });

        Schema::table('contact_notes', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('contact_notes', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn(['uuid', 'version']);
        });
    }
};
