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
        Schema::create('inventory_products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('gym_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('category_id')
                ->constrained('inventory_categories')
                ->cascadeOnDelete();

            $table->foreignId('sub_category_id')
                ->constrained('inventory_categories')
                ->cascadeOnDelete();

            $table->foreignId('measurement_unit_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('sku')->unique();

            $table->string('barcode')->nullable()->unique();

            $table->decimal('default_purchase_price', 12, 2)
                ->default(0);

            $table->decimal('default_sale_price', 12, 2)
                ->nullable();

            $table->decimal('min_stock_alert', 12, 3)
                ->default(0);

            $table->string('image')->nullable();

            $table->boolean('status')->default(true);

            $table->timestamps();

            $table->index('gym_id');
            $table->index('category_id');
            $table->index('measurement_unit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_products');
    }
};
