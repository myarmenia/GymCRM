<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserStatusVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_list_status_changes_advance_the_version_used_by_dashboard_sync(): void
    {
        $actor = User::factory()->create(['active' => true]);
        $target = User::factory()->create(['active' => true]);
        $this->actingAs($actor);

        $this->patchJson("/tables/users/{$target->id}/toggle-active")
            ->assertOk()
            ->assertJsonPath('active', false);
        $this->assertFalse($target->refresh()->active);
        $this->assertSame(2, $target->version);

        $this->patchJson("/tables/users/{$target->id}/toggle-active")
            ->assertOk()
            ->assertJsonPath('active', true);
        $this->assertTrue($target->refresh()->active);
        $this->assertSame(3, $target->version);
    }
}
