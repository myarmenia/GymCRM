<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gym_settings')) {
            return;
        }

        Schema::create('gym_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->unique()->constrained('gyms')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('version')->default(1);
            $table->string('membership_selection_mode')->default('auto_up_to_two');
            $table->string('membership_start_mode')->default('sale_date');
            $table->timestamps();
        });

        DB::table('gyms')->whereNull('deleted_at')->orderBy('id')->chunkById(100, function ($gyms): void {
            foreach ($gyms as $gym) {
                DB::table('gym_settings')->insert([
                    'gym_id' => $gym->id,
                    'uuid' => (string) Str::uuid(),
                    'version' => 1,
                    'membership_selection_mode' => 'auto_up_to_two',
                    'membership_start_mode' => 'sale_date',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // This repairs a missing table; rolling back must preserve existing settings.
    }
};
