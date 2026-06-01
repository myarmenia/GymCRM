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
        Schema::create('attendance_sheets', function (Blueprint $table) {
            $table->id();
            $table->morphs('relation');
            $table->string('entry_code')->nullable();
            $table->timestamp('date')->nullable();
            $table->string('type')->nullable();    // rfID, faceID
            $table->string('direction')->nullable(); // exit, enter
            $table->boolean('online')->nullable(); // exit, enter
            $table->string('local_ip')->nullable();
            $table->string('mac')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_sheets');
    }
};
