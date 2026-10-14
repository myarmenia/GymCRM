<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContactProfileMigrationTest extends TestCase
{
    public function test_legacy_notes_for_the_same_phone_share_one_profile(): void
    {
        $original = config('database.default');
        config()->set('database.connections.legacy_profiles', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ]);
        DB::purge('legacy_profiles');
        DB::setDefaultConnection('legacy_profiles');

        try {
            Schema::create('gyms', fn (Blueprint $table) => $table->id());
            Schema::create('users', function (Blueprint $table) {
                $table->id(); $table->unsignedBigInteger('gym_id');
            });
            Schema::create('contact_notes', function (Blueprint $table) {
                $table->id(); $table->uuid('uuid'); $table->unsignedBigInteger('version');
                $table->unsignedBigInteger('user_id'); $table->string('phone_number');
                $table->text('note'); $table->timestamp('created_at');
            });
            DB::table('gyms')->insert(['id' => 1]);
            DB::table('users')->insert(['id' => 2, 'gym_id' => 1]);
            DB::table('contact_notes')->insert([
                ['uuid' => '10000000-0000-4000-8000-000000000001', 'version' => 1, 'user_id' => 2,
                    'phone_number' => '099 123-456', 'note' => 'First', 'created_at' => '2026-10-01 10:00:00'],
                ['uuid' => '10000000-0000-4000-8000-000000000002', 'version' => 1, 'user_id' => 2,
                    'phone_number' => '+37499123456', 'note' => 'Later', 'created_at' => '2026-10-02 10:00:00'],
            ]);

            $migration = require database_path('migrations/2026_10_13_000000_create_contact_profiles.php');
            $migration->up();
            $this->assertSame(1, DB::table('contact_profiles')->count());
            $profile = DB::table('contact_profiles')->first();
            $this->assertSame('10000000-0000-4000-8000-000000000001', $profile->uuid);
            $this->assertSame('37499123456', $profile->phone_normalized);
            $this->assertSame([$profile->id, $profile->id], DB::table('contact_notes')
                ->orderBy('id')->pluck('contact_profile_id')->all());
        } finally {
            DB::setDefaultConnection($original);
            DB::purge('legacy_profiles');
        }
    }
}
