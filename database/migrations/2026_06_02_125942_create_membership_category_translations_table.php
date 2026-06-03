<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('membership_category_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_category_id')->constrained()->cascadeOnDelete();

            $table->string('locale', 5);

            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(
                ['membership_category_id', 'locale'],
                'mct_cat_locale_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_category_translations');
    }
};
