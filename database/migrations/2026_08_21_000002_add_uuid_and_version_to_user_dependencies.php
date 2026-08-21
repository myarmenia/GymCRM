<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ramsey\Uuid\Uuid;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->unsignedBigInteger('version')->default(1)->after('uuid');
        });

        Schema::table('entry_codes', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->unsignedBigInteger('version')->default(1)->after('uuid');
        });

        DB::table('gyms')
            ->whereNull('uuid')
            ->orderBy('id')
            ->eachById(function (object $gym): void {
                DB::table('gyms')
                    ->where('id', $gym->id)
                    ->update([
                        'uuid' => (string) Uuid::uuid5(
                            Uuid::NAMESPACE_URL,
                            'sportcrm:gym:'.mb_strtolower(trim((string) $gym->name)).':'
                            .mb_strtolower(trim((string) $gym->address)).':'
                            .mb_strtolower(trim((string) $gym->phone)).':'
                            .mb_strtolower(trim((string) $gym->email)).':'.$gym->id,
                        ),
                    ]);
            });

        DB::table('entry_codes')
            ->join('gyms', 'gyms.id', '=', 'entry_codes.gym_id')
            ->whereNull('entry_codes.uuid')
            ->orderBy('entry_codes.id')
            ->select([
                'entry_codes.id',
                'entry_codes.token',
                'entry_codes.type',
                'gyms.uuid as gym_uuid',
            ])
            ->each(function (object $entryCode): void {
                DB::table('entry_codes')
                    ->where('id', $entryCode->id)
                    ->update([
                        'uuid' => (string) Uuid::uuid5(
                            Uuid::NAMESPACE_URL,
                            'sportcrm:entry-code:'.$entryCode->gym_uuid.':'
                            .trim((string) $entryCode->token).':'.trim((string) $entryCode->type),
                        ),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('entry_codes', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn(['uuid', 'version']);
        });

        Schema::table('gyms', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn(['uuid', 'version']);
        });
    }
};
