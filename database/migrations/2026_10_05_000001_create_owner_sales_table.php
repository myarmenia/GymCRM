<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_sales', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('version')->default(1);
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('payment_type', 20);
            $table->string('payment_status', 20)->default('unpaid');
            $table->date('starts_at');
            $table->date('ends_at');
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('expiry_notified_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['gym_id', 'payment_status', 'starts_at', 'ends_at'], 'owner_sales_access_index');
            $table->index(['status', 'ends_at', 'expiry_notified_at'], 'owner_sales_expiry_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_sales');
    }
};
