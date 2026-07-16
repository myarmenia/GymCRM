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
        Schema::create('hdm_cashiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('hdm_config_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name'); // Reception cashier A
            $table->string('login');
            $table->string('pin');

            $table->string('session_key')->nullable();
            $table->timestamp('session_expires_at')->nullable();
            $table->timestamp('last_login_at')->nullable();

            $table->boolean('status')->default(true);

            $table->timestamps();

            $table->index(['gym_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hdm_cashiers');
    }
};
