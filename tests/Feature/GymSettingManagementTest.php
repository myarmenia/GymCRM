<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\GymSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\TableService;
use Database\Seeders\LangSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GymSettingManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_gym_also_deletes_its_settings(): void
    {
        $this->actingAs($this->owner());
        $gym = Gym::query()->create(['name' => 'Deleted gym']);
        $otherGym = Gym::query()->create(['name' => 'Other gym']);
        $setting = $gym->setting()->create(['membership_selection_mode' => GymSetting::MANAGER_SELECT_MULTIPLE]);
        $otherSetting = $otherGym->setting()->create(['membership_selection_mode' => GymSetting::AUTO_UP_TO_TWO]);

        app(TableService::class)->delete('gyms', $gym->id);

        $this->assertSoftDeleted($gym);
        $this->assertDatabaseMissing('gym_settings', ['id' => $setting->id]);
        $this->assertDatabaseHas('gym_settings', ['id' => $otherSetting->id]);
    }

    public function test_owner_creates_gym_then_settings_and_can_edit_them(): void
    {
        config()->set('sync.enabled', false);
        $this->seed(LangSeeder::class);
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('gym.store', ['locale' => 'en']), [
            'name' => 'Settings gym',
            'address' => 'Main street',
            'entry_code_type' => 'rfId',
            'trainer_salary_mode' => 'prepaid',
            'language_codes' => ['en'],
        ])->assertRedirect(route('gym.settings.create', [
            'locale' => 'en',
            'gym' => Gym::query()->where('name', 'Settings gym')->value('id'),
        ]));

        $gym = Gym::query()->where('name', 'Settings gym')->firstOrFail();
        $this->assertNull($gym->setting);
        $this->actingAs($owner)
            ->get(route('gym.list', ['locale' => 'en']))
            ->assertRedirect(route('gym.settings.create', ['locale' => 'en', 'gym' => $gym->id]));

        $this->actingAs($owner)
            ->get(route('gym.settings.create', ['locale' => 'en', 'gym' => $gym->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Gyms/Settings')->where('isCreate', true));

        $this->actingAs($owner)
            ->post(route('gym.settings.store', ['locale' => 'en', 'gym' => $gym->id]), [
                'membership_selection_mode' => GymSetting::MANAGER_SELECT_MULTIPLE,
                'membership_start_mode' => GymSetting::FIRST_ENTRY,
            ])->assertRedirect(route('gym.list', ['locale' => 'en']));

        $this->assertSame(1, GymSetting::automaticMembershipLimitForGym($gym->id));
        $this->actingAs($owner)
            ->get(route('gym.list', ['locale' => 'en']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Gyms/List'));
        $this->assertSame(GymSetting::FIRST_ENTRY, $gym->setting()->value('membership_start_mode'));
        $this->actingAs($owner)
            ->get(route('gym.settings.edit', ['locale' => 'en', 'gym' => $gym->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Gyms/Settings')
                ->where('isCreate', false)
                ->where('mode', GymSetting::MANAGER_SELECT_MULTIPLE)
                ->where('startMode', GymSetting::FIRST_ENTRY));

        $this->actingAs($owner)
            ->patch(route('gym.settings.update', ['locale' => 'en', 'gym' => $gym->id]), [
                'membership_selection_mode' => GymSetting::AUTO_UP_TO_TWO,
                'membership_start_mode' => GymSetting::SALE_DATE,
            ])->assertRedirect(route('gym.list', ['locale' => 'en']));

        $this->assertSame(2, GymSetting::automaticMembershipLimitForGym($gym->id));
        $this->assertSame(GymSetting::SALE_DATE, $gym->setting()->value('membership_start_mode'));
        $this->assertDatabaseCount('gym_settings', 1);
    }

    public function test_only_owner_can_change_settings_and_mode_is_validated(): void
    {
        $gym = Gym::query()->create(['name' => 'Existing gym']);
        GymSetting::query()->create([
            'gym_id' => $gym->id,
            'membership_selection_mode' => GymSetting::AUTO_UP_TO_TWO,
        ]);
        $this->assertSame(GymSetting::SALE_DATE, $gym->setting()->value('membership_start_mode'));
        $regular = User::factory()->create(['gym_id' => $gym->id]);

        $this->actingAs($regular)
            ->get(route('gym.settings.edit', ['locale' => 'en', 'gym' => $gym->id]))
            ->assertForbidden();
        $this->actingAs($regular)
            ->patch(route('gym.settings.update', ['locale' => 'en', 'gym' => $gym->id]), [
                'membership_selection_mode' => GymSetting::MANAGER_SELECT_MULTIPLE,
            ])->assertForbidden();

        $this->actingAs($this->owner())
            ->patch(route('gym.settings.update', ['locale' => 'en', 'gym' => $gym->id]), [
                'membership_selection_mode' => 'invalid',
            ])->assertSessionHasErrors('membership_selection_mode');

        $this->assertSame(2, GymSetting::automaticMembershipLimitForGym($gym->id));
    }

    private function owner(): User
    {
        $role = Role::query()->firstOrCreate([
            'name' => 'owner',
            'guard_name' => 'web',
            'g_name' => 'owner',
        ]);
        $owner = User::factory()->create();
        $owner->assignRole($role);

        return $owner;
    }
}
