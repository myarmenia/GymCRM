<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'trainer_status')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('trainer_status', 20)->default('active')->after('active');
            });
        }

        if (! Schema::hasTable('trainer_freezes')) {
            Schema::create('trainer_freezes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('version')->default(1);
                $table->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
                $table->date('start_date');
                $table->date('end_date');
                $table->timestamps();
                $table->index(['trainer_id', 'start_date', 'end_date']);
            });
        }

        if (! Schema::hasTable('trainer_membership_freezes')) {
            Schema::create('trainer_membership_freezes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('version')->default(1);
                $table->foreignId('trainer_freeze_id')->constrained()->cascadeOnDelete();
                $table->foreignId('person_membership_id')->constrained()->cascadeOnDelete();
                $table->date('start_date');
                $table->date('end_date');
                $table->date('cancel_effective_date')->nullable();
                $table->unsignedInteger('next_shift_days')->default(0);
                $table->timestamps();
                $table->unique(['trainer_freeze_id', 'person_membership_id'], 'tm_freezes_trainer_membership_uniq');
                $table->index(['person_membership_id', 'start_date', 'end_date'], 'tm_freezes_membership_dates_idx');
            });
        }

        // MySQL may have created the table before the previous migration failed on its long unique name.
        if (! Schema::hasIndex('trainer_membership_freezes', 'tm_freezes_trainer_membership_uniq')) {
            Schema::table('trainer_membership_freezes', function (Blueprint $table): void {
                $table->unique(['trainer_freeze_id', 'person_membership_id'], 'tm_freezes_trainer_membership_uniq');
            });
        }
        if (! Schema::hasIndex('trainer_membership_freezes', 'tm_freezes_membership_dates_idx')) {
            Schema::table('trainer_membership_freezes', function (Blueprint $table): void {
                $table->index(['person_membership_id', 'start_date', 'end_date'], 'tm_freezes_membership_dates_idx');
            });
        }

        Schema::table('person_memberships', function (Blueprint $table): void {
            $table->string('status', 20)->default('active')->change();
        });
    }

    public function down(): void
    {
        DB::table('person_memberships')->where('status', 'trainer_frozen')->update(['status' => 'active']);
        Schema::table('person_memberships', function (Blueprint $table): void {
            $table->enum('status', ['waiting', 'active', 'frozen', 'expired', 'deleted', 'cancelled'])
                ->default('active')->change();
        });
        Schema::dropIfExists('trainer_membership_freezes');
        Schema::dropIfExists('trainer_freezes');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('trainer_status'));
    }
};
