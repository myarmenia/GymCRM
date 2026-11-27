<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('gym_settings')->whereIn(
            'gym_id',
            DB::table('gyms')->whereNotNull('deleted_at')->select('id'),
        )->delete();
    }

    public function down(): void
    {
        // Deleted settings cannot be reconstructed from a gym.
    }
};
