<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\GymLanguage;
use App\Models\Lang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordIdentityBehaviorTest extends TestCase
{
    use RefreshDatabase;

    public function test_stateful_relationship_generates_uuid_and_increments_version(): void
    {
        $gym = Gym::query()->create(['name' => 'Main gym']);
        $lang = Lang::query()->create(['code' => 'en', 'name' => 'English']);

        $gym->languages()->attach($lang->id, ['active' => true]);

        $gymLanguage = GymLanguage::query()->firstOrFail();

        $this->assertNotNull($gymLanguage->uuid);
        $this->assertSame(1, $gymLanguage->version);
        $this->assertTrue($gymLanguage->active);

        $gym->languages()->updateExistingPivot($lang->id, ['active' => false]);

        $gymLanguage->refresh();

        $this->assertSame(2, $gymLanguage->version);
        $this->assertFalse($gymLanguage->active);
    }
}
