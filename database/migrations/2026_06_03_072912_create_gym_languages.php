<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gym_languages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('lang_id')
                ->constrained('langs')
                ->cascadeOnDelete();

            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->unique(['gym_id', 'lang_id'], 'gym_lang_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_languages');
    }
};
