<?php

use App\Support\StableUuid;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('version')->default(1);
            $table->string('slug')->unique();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('reminder_categories')->insert([
            [
                'slug' => 'general',
                ...StableUuid::seedIdentity('reminder-categories', 'general'),
                'name' => 'Ընդհանուր հիշեցում',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'membership_payment_due',
                ...StableUuid::seedIdentity('reminder-categories', 'membership_payment_due'),
                'name' => 'Աբոնեմենտի վճարման օր',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('version')->default(1);
            $table->foreignId('gym_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->constrained('reminder_categories')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('about_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->dateTime('scheduled_at');
            $table->enum('status', ['scheduled', 'processing', 'sent', 'cancelled', 'failed'])
                ->default('scheduled');
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index(['source_type', 'source_id']);
            $table->index(['gym_id', 'created_at']);
        });

        Schema::create('reminder_recipients', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('version')->default(1);
            $table->foreignId('reminder_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->dateTime('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['reminder_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_recipients');
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('reminder_categories');
    }
};
