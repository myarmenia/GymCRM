<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sheets', function (Blueprint $table): void {
            $table->foreignId('gym_id')
                ->nullable()
                ->after('membership_plan_id')
                ->constrained('gyms')
                ->nullOnDelete();
            $table->index(['gym_id', 'date'], 'attendance_gym_date_index');
        });

        DB::statement(
            'UPDATE attendance_sheets
             SET gym_id = (SELECT gym_id FROM membership_plans WHERE membership_plans.id = attendance_sheets.membership_plan_id)
             WHERE gym_id IS NULL AND membership_plan_id IS NOT NULL'
        );

        Schema::dropIfExists('entry_reports');
    }

    public function down(): void
    {
        Schema::create('entry_reports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('version')->default(1);
            $table->foreignId('client_id')->nullable()->index();
            $table->string('entry_code')->nullable()->index();
            $table->enum('owner_type', ['user', 'person'])->nullable()->index();
            $table->unsignedBigInteger('owner_id')->nullable()->index();
            $table->enum('action', ['entry', 'exit', 'unknown'])->default('unknown');
            $table->enum('status', ['success', 'denied'])->default('denied');
            $table->string('reason')->nullable()->index();
            $table->boolean('access_allowed')->default(false);
            $table->string('mac')->nullable()->index();
            $table->timestamp('device_time')->nullable();
            $table->timestamp('detected_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        Schema::table('attendance_sheets', function (Blueprint $table): void {
            $table->dropIndex('attendance_gym_date_index');
            $table->dropConstrainedForeignId('gym_id');
        });
    }
};
