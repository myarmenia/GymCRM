<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_person_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attendance_sheet_id')->constrained('attendance_sheets')->cascadeOnDelete();
            $table->foreignId('person_membership_id')->constrained('person_memberships')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['attendance_sheet_id', 'person_membership_id'], 'att_person_membership_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_person_memberships');
    }
};
