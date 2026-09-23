<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\Lang;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\LangSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GymLanguageManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('sync.enabled', false);
        $this->seed(LangSeeder::class);
        Lang::query()->create([
            'code' => 'de',
            'name' => 'Deutsch',
        ]);
    }

    public function test_owner_can_create_a_gym_with_languages_loaded_from_the_database(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->get(route('gym.create', ['locale' => 'en']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Gyms/Create')
                ->has('availableLanguages', 4)
                ->where('availableLanguages.3.code', 'de')
                ->where('selectedLanguageCodes', ['hy', 'ru', 'en', 'de'])
            );

        $this->actingAs($owner)
            ->post(route('gym.store', ['locale' => 'en']), [
                ...$this->gymPayload(),
                'name' => 'Database languages gym',
                'language_codes' => ['hy', 'de'],
            ])
            ->assertRedirect(route('gym.list', ['locale' => 'en']));

        $gym = Gym::query()->where('name', 'Database languages gym')->firstOrFail();

        $this->assertSame([
            'de' => true,
            'en' => false,
            'hy' => true,
            'ru' => false,
        ], $this->languageStates($gym));
    }

    public function test_owner_can_edit_the_active_languages_of_a_gym(): void
    {
        $owner = $this->owner();
        $gym = Gym::query()->create($this->gymPayload());
        $englishId = Lang::query()->where('code', 'en')->value('id');
        $gym->languages()->updateExistingPivot($englishId, ['active' => false]);

        $this->actingAs($owner)
            ->get(route('gym.edit', ['locale' => 'en', 'id' => $gym->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Gyms/Edit')
                ->has('availableLanguages', 4)
                ->where('availableLanguages.3.code', 'de')
                ->where('selectedLanguageCodes', ['hy', 'ru', 'de'])
            );

        $this->actingAs($owner)
            ->patch(route('gym.update', ['locale' => 'en', 'id' => $gym->id]), [
                ...$this->gymPayload(),
                'name' => 'Updated gym',
                'language_codes' => ['ru', 'en'],
            ])
            ->assertRedirect(route('gym.list', ['locale' => 'en']));

        $this->assertSame([
            'de' => false,
            'en' => true,
            'hy' => false,
            'ru' => true,
        ], $this->languageStates($gym->fresh()));
    }

    public function test_language_management_is_owner_only_and_requires_one_language(): void
    {
        $owner = $this->owner();
        $gym = Gym::query()->create($this->gymPayload());

        $this->actingAs($owner)
            ->from(route('gym.edit', ['locale' => 'en', 'id' => $gym->id]))
            ->patch(route('gym.update', ['locale' => 'en', 'id' => $gym->id]), [
                ...$this->gymPayload(),
                'language_codes' => [],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('language_codes');

        $regularUser = User::factory()->create();

        $this->actingAs($regularUser)
            ->get(route('gym.create', ['locale' => 'en']))
            ->assertForbidden();
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

    private function gymPayload(): array
    {
        return [
            'name' => 'Main gym',
            'address' => 'Main street 1',
            'phone' => null,
            'email' => null,
            'entry_code_type' => 'rfId',
            'trainer_salary_mode' => 'prepaid',
        ];
    }

    private function languageStates(Gym $gym): array
    {
        return $gym->languages()
            ->orderBy('langs.code')
            ->get()
            ->mapWithKeys(fn (Lang $language): array => [
                $language->code => (bool) $language->pivot->active,
            ])
            ->all();
    }
}
