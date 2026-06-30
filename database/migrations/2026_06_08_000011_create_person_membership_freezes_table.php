<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_membership_freezes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_membership_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['person_membership_id', 'start_date', 'end_date'], 'pm_freezes_membership_dates_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_membership_freezes');
    }
};
