<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MobileAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sync.enabled' => false, 'mobile.registration_enabled' => true,
            'services.firebase.credentials' => '/nonexistent/mobile-test-credentials.json']);
    }

    private function person(array $attributes = []): Person
    {
        return Person::create([...[
            'name' => 'Original', 'phone' => '37477637949', 'email' => 'original@example.test',
            'password' => Hash::make('old-pass'), 'birth_date' => '1990-01-01', 'type' => 'guest',
        ], ...$attributes]);
    }

    private function registration(array $attributes = []): array
    {
        return [...[
            'name' => 'New person', 'phone' => '077637949', 'birth_date' => '1995-02-03',
            'password' => 'new-password', 'password_confirmation' => 'new-password',
        ], ...$attributes];
    }

    public function test_login_normalizes_all_three_phone_formats_without_requiring_a_gym_or_visitor_type(): void
    {
        $person = $this->person(['mobile_deleted' => true, 'is_blocked' => true]);
        $version = $person->version;
        foreach (['+37477637949', '37477637949', '077637949'] as $phone) {
            $this->postJson('/api/mobile/v1/auth/login', ['phone' => $phone, 'password' => 'old-pass'])
                ->assertOk()->assertJsonPath('data.person.id', $person->id)
                ->assertJsonPath('data.person.phone', '37477637949')->assertJsonMissingPath('data.person.password');
        }
        $this->assertFalse((bool) $person->fresh()->mobile_deleted);
        $this->assertSame($version, $person->fresh()->version);
        $this->assertSame('guest', $person->fresh()->type);
        $this->assertTrue($person->fresh()->is_blocked);
        $this->assertDatabaseCount('gym_person', 0);
    }

    public function test_email_login_and_invalid_or_unknown_phones_are_rejected(): void
    {
        $person = $this->person();
        foreach ([['email' => $person->email], ['phone' => 'abc077637949'], ['phone' => '123'], ['phone' => '077000000']] as $input) {
            $this->postJson('/api/mobile/v1/auth/login', [...$input, 'password' => 'old-pass'])
                ->assertUnprocessable()->assertJsonValidationErrors('phone');
        }
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_registration_creates_only_a_person_and_returns_a_usable_token(): void
    {
        $response = $this->postJson('/api/mobile/v1/auth/register', $this->registration())->assertCreated()
            ->assertJsonPath('data.person.phone', '37477637949')->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonMissingPath('data.person.password');
        $person = Person::firstOrFail();
        $this->assertNotEmpty($person->uuid);
        $this->assertSame(1, $person->version);
        $this->assertSame('1995-02-03', $person->birth_date);
        $this->assertNull($person->email);
        $this->assertTrue(Hash::check('new-password', $person->password));
        foreach (['gym_person', 'entry_permissions', 'membership_sales', 'person_memberships'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->withToken($response->json('data.access_token'));
        $this->getJson('/api/mobile/v1/me')->assertOk()->assertJsonPath('data.id', $person->id);
        $this->getJson('/api/mobile/v1/memberships')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/mobile/v1/gyms')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_new_registration_requires_name_birth_date_confirmation_and_strong_password(): void
    {
        $this->postJson('/api/mobile/v1/auth/register', ['phone' => '077637949', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'birth_date']);
        $this->postJson('/api/mobile/v1/auth/register', $this->registration(['birth_date' => today()->addDay()->toDateString()]))
            ->assertUnprocessable()->assertJsonValidationErrors('birth_date');
        $this->postJson('/api/mobile/v1/auth/register', $this->registration(['password_confirmation' => 'different']))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/mobile/v1/auth/register', $this->registration(['password' => 'short', 'password_confirmation' => 'short']))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertDatabaseCount('people', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_existing_account_registration_checks_password_and_preserves_identity_and_gym_state(): void
    {
        $person = $this->person();
        $gym = Gym::create(['name' => 'Gym']);
        $person->gyms()->attach($gym->id, ['type' => 'guest', 'is_blocked' => true]);
        $original = $person->only(['uuid', 'version', 'name', 'email', 'birth_date', 'password']);
        $pivot = $person->gymLink($gym->id)->getAttributes();
        $this->postJson('/api/mobile/v1/auth/register', $this->registration(['password' => 'wrong-pass', 'password_confirmation' => 'wrong-pass']))
            ->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->postJson('/api/mobile/v1/auth/register', $this->registration(['password' => 'old-pass', 'password_confirmation' => 'old-pass']))
            ->assertOk()->assertJsonPath('data.person.id', $person->id)->assertJsonPath('data.person.name', 'Original');
        $this->assertSame($original, $person->fresh()->only(array_keys($original)));
        $this->assertSame($pivot, $person->gymLink($gym->id)->getAttributes());
        $this->assertDatabaseCount('people', 1);
        $this->assertDatabaseCount('gym_person', 1);
    }

    public function test_existing_account_can_register_without_resubmitting_identity_fields_or_setting_a_first_password(): void
    {
        $person = $this->person(['password' => Hash::make('legacy')]);
        $this->postJson('/api/mobile/v1/auth/register', [
            'phone' => '077637949', 'password' => 'legacy', 'password_confirmation' => 'legacy',
        ])->assertOk()->assertJsonPath('data.person.id', $person->id);
        $person->update(['password' => null]);
        $this->postJson('/api/mobile/v1/auth/register', $this->registration())
            ->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->postJson('/api/mobile/v1/auth/login', ['phone' => '077637949', 'password' => 'new-password'])
            ->assertUnprocessable();
        $this->assertNull($person->fresh()->password);
        $this->assertDatabaseCount('people', 1);
    }

    public function test_soft_deleted_person_is_neither_logged_in_nor_recreated(): void
    {
        $person = $this->person();
        $person->delete();
        $this->postJson('/api/mobile/v1/auth/login', ['phone' => '077637949', 'password' => 'old-pass'])->assertUnprocessable();
        $this->postJson('/api/mobile/v1/auth/register', $this->registration())->assertUnprocessable();
        $this->assertDatabaseCount('people', 1);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertNotNull($person->fresh()->deleted_at);
    }

    public function test_password_change_preserves_current_token_revokes_other_devices_and_keeps_gym_state(): void
    {
        $person = $this->person();
        $gym = Gym::create(['name' => 'Gym']);
        $person->gyms()->attach($gym->id, ['type' => 'guest', 'is_blocked' => true]);
        $pivot = $person->gymLink($gym->id)->getAttributes();
        $current = $person->createToken('current', ['mobile']);
        $other = $person->createToken('other', ['mobile']);
        $this->withToken($current->plainTextToken)->putJson('/api/mobile/v1/me/password', [
            'current_password' => 'old-pass', 'password' => 'changed-password', 'password_confirmation' => 'changed-password',
        ])->assertOk()->assertJsonPath('data.password_changed', true);
        $this->assertTrue(Hash::check('changed-password', $person->fresh()->password));
        $this->assertSame(2, $person->fresh()->version);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $current->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $other->accessToken->id]);
        $this->assertSame($pivot, $person->gymLink($gym->id)->getAttributes());
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/mobile/v1/me')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($other->plainTextToken)->getJson('/api/mobile/v1/me')->assertUnauthorized();
        $this->postJson('/api/mobile/v1/auth/login', ['phone' => '077637949', 'password' => 'old-pass'])->assertUnprocessable();
        $this->postJson('/api/mobile/v1/auth/login', ['phone' => '077637949', 'password' => 'changed-password'])->assertOk();
    }

    public function test_password_change_rejects_wrong_current_password_mismatched_confirmation_and_unchanged_password(): void
    {
        $person = $this->person();
        $hash = $person->password;
        $this->withToken($person->createToken('current', ['mobile'])->plainTextToken);
        $this->putJson('/api/mobile/v1/me/password', [
            'current_password' => 'wrong', 'password' => 'changed-password', 'password_confirmation' => 'changed-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->putJson('/api/mobile/v1/me/password', [
            'current_password' => 'old-pass', 'password' => 'changed-password', 'password_confirmation' => 'wrong',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->putJson('/api/mobile/v1/me/password', [
            'current_password' => 'old-pass', 'password' => 'old-pass', 'password_confirmation' => 'old-pass',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertSame($hash, $person->fresh()->password);
        $this->assertSame(1, $person->fresh()->version);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_password_change_requires_a_person_session(): void
    {
        $data = ['current_password' => 'old-pass', 'password' => 'changed-password', 'password_confirmation' => 'changed-password'];
        $this->putJson('/api/mobile/v1/me/password', $data)->assertUnauthorized();
        $this->actingAs(User::factory()->create())->putJson('/api/mobile/v1/me/password', $data)->assertForbidden();
    }

    public function test_mobile_deactivation_and_relogin_preserve_crm_identity_and_links(): void
    {
        $person = $this->person();
        $gym = Gym::create(['name' => 'Gym']);
        $person->gyms()->attach($gym->id, ['is_blocked' => true]);
        $pivot = $person->gymLink($gym->id)->getAttributes();
        $this->withToken($person->createToken('current', ['mobile'])->plainTextToken)
            ->deleteJson('/api/mobile/v1/me')->assertOk();
        $this->assertTrue((bool) $person->fresh()->mobile_deleted);
        $this->assertNull($person->fresh()->deleted_at);
        $this->assertSame($pivot, $person->gymLink($gym->id)->getAttributes());
        $this->postJson('/api/mobile/v1/auth/login', ['phone' => '077637949', 'password' => 'old-pass'])->assertOk();
        $this->assertFalse((bool) $person->fresh()->mobile_deleted);
        $this->assertSame(1, $person->fresh()->version);
        $this->assertSame($pivot, $person->gymLink($gym->id)->getAttributes());
    }

    public function test_profile_phone_updates_use_canonical_uniqueness_and_birth_date_cannot_be_cleared(): void
    {
        $person = $this->person();
        $this->person(['phone' => '37477000000', 'email' => 'other@example.test']);
        $this->withToken($person->createToken('current', ['mobile'])->plainTextToken);
        $this->putJson('/api/mobile/v1/me', ['phone' => '+374 77 000 000'])->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->putJson('/api/mobile/v1/me', ['phone' => 'abc077111111'])->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->putJson('/api/mobile/v1/me', ['birth_date' => null])->assertUnprocessable()->assertJsonValidationErrors('birth_date');
        $this->putJson('/api/mobile/v1/me', ['phone' => '077111111'])->assertOk()->assertJsonPath('data.phone', '37477111111');
        $this->assertSame('37477111111', $person->fresh()->phone);
    }

    public function test_registration_respects_primary_identity_creation_switch(): void
    {
        config(['mobile.registration_enabled' => false]);
        $this->postJson('/api/mobile/v1/auth/register', $this->registration())->assertStatus(503);
        $this->assertDatabaseCount('people', 0);
        $person = $this->person();
        $this->postJson('/api/mobile/v1/auth/register', [
            'phone' => '077637949', 'password' => 'old-pass', 'password_confirmation' => 'old-pass',
        ])->assertOk()->assertJsonPath('data.person.id', $person->id);
    }
}
