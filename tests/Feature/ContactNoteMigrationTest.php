<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContactNoteMigrationTest extends TestCase
{
    public function test_existing_contact_notes_receive_sync_identity(): void
    {
        $original = config('database.default');
        config()->set('database.connections.legacy_notes', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ]);
        DB::purge('legacy_notes');
        DB::setDefaultConnection('legacy_notes');

        try {
            Schema::create('contact_notes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('phone_number');
                $table->text('note');
                $table->timestamp('created_at');
            });
            DB::table('contact_notes')->insert([
                'user_id' => 1, 'phone_number' => '123', 'note' => 'Already created',
                'created_at' => '2026-10-07 10:00:00',
            ]);

            $migration = require database_path('migrations/2026_10_07_000001_add_sync_identity_to_contact_notes.php');
            $migration->up();

            $note = DB::table('contact_notes')->first();
            $this->assertNotNull($note->uuid);
            $this->assertSame(1, (int) $note->version);
            $this->assertSame('Already created', $note->note);
        } finally {
            DB::setDefaultConnection($original);
            DB::purge('legacy_notes');
        }
    }
}
