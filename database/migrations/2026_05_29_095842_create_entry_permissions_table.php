<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entry_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_code_id')->constrained()->onDelete('cascade');
            $table->morphs('relation');
            $table->boolean('status')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_permitions');
    }
};
