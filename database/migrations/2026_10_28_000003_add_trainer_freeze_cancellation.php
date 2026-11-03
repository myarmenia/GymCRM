<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('trainer_freezes', 'cancel_effective_date')) {
            Schema::table('trainer_freezes', fn (Blueprint $table) => $table
                ->date('cancel_effective_date')->nullable()->after('end_date'));
        }
        if (! Schema::hasColumn('trainer_freezes', 'cancelled_at')) {
            Schema::table('trainer_freezes', fn (Blueprint $table) => $table
                ->timestamp('cancelled_at')->nullable()->after('cancel_effective_date'));
        }
    }

    public function down(): void
    {
        Schema::table('trainer_freezes', fn (Blueprint $table) => $table->dropColumn([
            'cancel_effective_date', 'cancelled_at',
        ]));
    }
};
