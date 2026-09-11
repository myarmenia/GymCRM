<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_biometrics', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('version')->default(1);
            $table->foreignId('person_id')->unique()->constrained('people')->cascadeOnDelete();
            $table->unsignedSmallInteger('height')->nullable();
            $table->decimal('weight', 5, 2)->nullable();
            $table->string('goal')->nullable();
            $table->string('activity_level')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_biometrics');
    }
};
