<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_membership_plan', function (Blueprint $table) {
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_plan_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['discount_id', 'membership_plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_membership_plan');
    }
};
