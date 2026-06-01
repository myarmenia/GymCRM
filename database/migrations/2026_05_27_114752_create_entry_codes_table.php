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
        Schema::create('entry_codes', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('gym_id')->unsigned();
            $table->foreign('gym_id')->references('id')->on('gyms')->onDelete('cascade');
            $table->string('token');
            $table->boolean('status')->default(1);
            $table->boolean('activation')->default(0);
            $table->string('type');

            $table->unique(['token', 'gym_id']);
            $table->softDeletes();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entry_codes');
    }
};
