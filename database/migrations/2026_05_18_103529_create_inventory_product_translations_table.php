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
        Schema::create('inventory_product_translations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inventory_product_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('locale', 10);

            $table->string('name');

            $table->text('description')->nullable();

            $table->timestamps();

            $table->unique(
                ['inventory_product_id', 'locale'],
                'inv_prod_trans_prod_locale_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_product_translations');
    }
};
