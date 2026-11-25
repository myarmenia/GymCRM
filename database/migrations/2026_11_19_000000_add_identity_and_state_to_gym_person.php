<?php

use App\Models\GymPerson;
use App\Services\MembershipSales\ContactNotePhone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check every row before any DDL or data mutation. Never merge identities here.
        $seen = [];
        $changes = [];
        DB::table('people')->orderBy('id')->chunkById(500, function ($people) use (&$seen, &$changes): void {
            foreach ($people as $person) {
                if ($person->phone === null || trim($person->phone) === '') {
                    continue; // Legacy missing contacts require real data, not generated phone numbers.
                }
                $phone = ContactNotePhone::canonical($person->phone);
                if (! ContactNotePhone::isValidPersonPhone($person->phone)) {
                    throw new RuntimeException("Invalid legacy phone on people ID {$person->id}; correct it before migrating.");
                }
                if (isset($seen[$phone])) {
                    throw new RuntimeException("Normalized phone collision on people IDs {$seen[$phone]} and {$person->id}; reconcile these identities before migrating.");
                }
                $seen[$phone] = $person->id;
                if ($person->phone !== $phone) {
                    $changes[$person->id] = $phone;
                }
            }
        });
        if (DB::table('gym_person')->select('person_id', 'gym_id')->groupBy('person_id', 'gym_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Duplicate gym_person pairs must be reconciled before migrating.');
        }

        Schema::table('gym_person', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->unique();
            $table->unsignedBigInteger('version')->default(1);
            $table->string('type', 16)->default('visitor');
            $table->boolean('is_blocked')->default(false);
            $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('blocked_at')->nullable();
            $table->softDeletes();
            $table->unique(['person_id', 'gym_id']);
        });
        DB::transaction(function () use ($changes): void {
            foreach ($changes as $id => $phone) {
                DB::table('people')->where('id', $id)->update(['phone' => $phone]);
            }
            DB::table('gym_person')->orderBy('id')->chunkById(500, function ($links): void {
                foreach ($links as $link) {
                    $person = DB::table('people')->where('id', $link->person_id)->first();
                    $gym = DB::table('gyms')->where('id', $link->gym_id)->first();
                    DB::table('gym_person')->where('id', $link->id)->update([
                        'uuid' => GymPerson::identity($person->uuid, $gym->uuid),
                        'version' => max(1, (int) $person->version),
                        'type' => $person->type,
                        'is_blocked' => $person->is_blocked,
                    ]);
                }
            });
        });
        Schema::table('gym_person', fn (Blueprint $table) => $table->uuid('uuid')->nullable(false)->change());
    }

    public function down(): void
    {
        Schema::table('gym_person', function (Blueprint $table): void {
            $table->dropForeign(['blocked_by']);
            $table->dropUnique(['person_id', 'gym_id']);
            $table->dropUnique(['uuid']);
            $table->dropColumn(['uuid', 'version', 'type', 'is_blocked', 'blocked_by', 'blocked_at', 'deleted_at']);
        });
        // Phone formatting is intentionally canonical after a rollback as well.
    }
};
