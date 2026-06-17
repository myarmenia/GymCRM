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
        Schema::create('person_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trainer_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('status', [
                'waiting',
                'active',
                'frozen',
                'expired',
                'deleted',
                'cancelled',
            ])->default('active');

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('valid_at')->nullable();

            // progress only
            $table->integer('visits_used')->default(0);
            $table->integer('visits_left')->nullable();
            $table->integer('freeze_used')->default(0);
            $table->integer('guest_used')->default(0);
            $table->integer('freeze_left')->default(0);
            $table->integer('guest_left')->default(0);

            $table->foreignId('next_membership_id')
                ->nullable()
                ->constrained('person_memberships')
                ->nullOnDelete();

            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expired_at')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('person_memberships');
    }
};
