<?php

namespace Tests\Feature;

use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClearGeneratedGuestEmailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_and_apply_only_clear_generated_guest_emails(): void
    {
        $generated = Person::query()->create([
            'name' => 'Generated',
            'email' => 'guest-78cbb21c-34e3-449d-a459-df219d9ba055@guest.local',
            'type' => 'guest',
        ]);
        $real = Person::query()->create([
            'name' => 'Real',
            'email' => 'guest-help@guest.local',
            'type' => 'guest',
        ]);

        $this->artisan('guests:clear-generated-emails')->assertSuccessful();
        $this->assertNotNull($generated->fresh()->email);

        $this->artisan('guests:clear-generated-emails --apply')->assertSuccessful();
        $this->assertNull($generated->fresh()->email);
        $this->assertSame(2, $generated->fresh()->version);
        $this->assertSame('guest-help@guest.local', $real->fresh()->email);
    }
}
