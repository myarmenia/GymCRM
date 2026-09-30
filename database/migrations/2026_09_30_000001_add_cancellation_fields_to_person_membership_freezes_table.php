<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('person_membership_freezes', function (Blueprint $table) {
            $table->date('cancel_effective_date')->nullable()->after('end_date');
            $table->timestamp('cancelled_at')->nullable()->after('cancel_effective_date');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')
                ->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable()->after('cancelled_by');
            $table->index(
                ['person_membership_id', 'cancel_effective_date'],
                'pm_freezes_membership_cancel_date_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('person_membership_freezes', function (Blueprint $table) {
            $table->dropIndex('pm_freezes_membership_cancel_date_idx');
            $table->dropForeign(['cancelled_by']);
            $table->dropColumn([
                'cancel_effective_date',
                'cancelled_at',
                'cancelled_by',
                'cancellation_reason',
            ]);
        });
    }
};
