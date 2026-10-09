<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('phone_number', 32);
            $table->text('note');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'phone_number', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_notes');
    }
};
