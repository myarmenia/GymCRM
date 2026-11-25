<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\GymPerson;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class GymPersonMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_preserves_uuid_identity_and_copies_type_and_block_state_to_every_gym(): void
    {
        config(['sync.enabled' => false]);
        $person = Person::create(['name' => 'Legacy', 'phone' => '077637949', 'type' => 'newcomer', 'is_blocked' => true]);
        $a = Gym::create(['name' => 'A']);
        $b = Gym::create(['name' => 'B']);
        $person->gyms()->attach([$a->id, $b->id]);
        $migration = require database_path('migrations/2026_11_19_000000_add_identity_and_state_to_gym_person.php');
        $migration->down();
        DB::table('people')->where('id', $person->id)->update(['phone' => '+374 (77) 637-949', 'version' => 7]);
        $migration->up();
        $this->assertSame('37477637949', $person->fresh()->phone);
        foreach ([$a, $b] as $gym) {
            $link = $person->gymLink($gym->id);
            $this->assertSame(GymPerson::identity($person->uuid, $gym->uuid), $link->uuid);
            $this->assertSame('newcomer', $link->type);
            $this->assertSame(7, $link->version);
            $this->assertTrue($link->is_blocked);
            $this->assertNull($link->blocked_by);
        }
    }

    public function test_normalized_collision_is_reported_before_any_phone_is_modified(): void
    {
        DB::table('people')->insert([
            ['uuid' => (string) Str::uuid(), 'name' => 'One', 'phone' => '077637949'],
            ['uuid' => (string) Str::uuid(), 'name' => 'Two', 'phone' => '+37477637949'],
        ]);
        $migration = require database_path('migrations/2026_11_19_000000_add_identity_and_state_to_gym_person.php');
        try {
            $migration->up();
            $this->fail('Expected a normalized phone collision.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Normalized phone collision', $exception->getMessage());
        }
        $this->assertSame(['077637949', '+37477637949'], DB::table('people')->orderBy('id')->pluck('phone')->all());
    }
}
