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
        Schema::create('warehouse_stocks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('gym_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('warehouse_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('inventory_product_id')
                ->constrained()
                ->cascadeOnDelete();

            /*
    current quantity in warehouse
    */
            $table->decimal('quantity', 12, 3)
                ->default(0);

            /*
    reserved for orders/tasks
    */
            $table->decimal('reserved_quantity', 12, 3)
                ->default(0);

            /*
    average product cost
    */
            $table->decimal('average_cost', 12, 2)
                ->default(0);

            $table->timestamps();

            /*
    one product per warehouse
    */
            $table->unique([
                'warehouse_id',
                'inventory_product_id'
            ]);

            $table->index('gym_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_stocks');
    }
};
