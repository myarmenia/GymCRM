<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            $table->string('sync_origin')->nullable()->after('status')->index();
        });

        Schema::create('purchase_refunds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('version')->default(1);
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->foreignId('card_type_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamp('refunded_at');
            $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['purchase_id', 'refunded_at']);
        });

        Schema::create('purchase_refund_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('version')->default(1);
            $table->foreignId('purchase_refund_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('amount', 14, 2);
            $table->timestamps();

            $table->index(['purchase_item_id', 'purchase_refund_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_refund_items');
        Schema::dropIfExists('purchase_refunds');
        Schema::table('purchases', function (Blueprint $table): void {
            $table->dropColumn('sync_origin');
        });
    }
};
