<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureGymSubscriptionIsActive;
use App\Models\AttendanceSheet;
use App\Models\Document;
use App\Models\EntryCode;
use App\Models\EntryPermission;
use App\Models\Gym;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Services\Turnstile\EntryExitSystemService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use ReflectionClass;
use Tests\TestCase;

class MultiGymPersonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sync.enabled' => false]);
        Role::firstOrCreate(['name' => 'sales_manager', 'guard_name' => 'web'], ['g_name' => 'sales_manager']);
        $this->withoutMiddleware(EnsureGymSubscriptionIsActive::class);
    }

    private function actor(Gym $gym, string $role = 'admin'): User
    {
        $user = User::factory()->create(['gym_id' => $gym->id, 'active' => true]);
        $user->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'], ['g_name' => $role]));

        return $user;
    }

    private function person(): Person
    {
        return Person::create(['name' => 'Original', 'phone' => '+37477637949', 'email' => 'original@example.test',
            'password' => bcrypt('original-password'), 'birth_date' => '1990-01-01', 'type' => 'visitor']);
    }

    private function card(Person $person, Gym $gym, string $token): EntryCode
    {
        $code = EntryCode::create(['gym_id' => $gym->id, 'token' => $token, 'type' => 'rfId', 'status' => true, 'activation' => true]);
        EntryPermission::create(['entry_code_id' => $code->id, 'relation_type' => Person::class, 'relation_id' => $person->id, 'status' => true]);

        return $code;
    }

    public function test_registration_attaches_existing_identity_without_overwriting_it(): void
    {
        $a = Gym::create(['name' => 'A']);
        $b = Gym::create(['name' => 'B']);
        $person = $this->person();
        $person->update(['is_blocked' => true]);
        $person->gyms()->attach($a->id, ['is_blocked' => true]);
        $version = $person->version;
        $password = $person->password;
        $this->actingAs($this->actor($b))->post(route('person.store', ['locale' => 'hy']), [
            'phone' => '077637949', 'name' => 'Overwrite', 'email' => 'changed@example.test', 'password' => 'new-password',
            'type' => 'guest', 'entry_code_mode' => 'new', 'entry_code_token' => 'B-CARD',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('people', 1);
        $this->assertDatabaseCount('gym_person', 2);
        $this->assertSame('Original', $person->fresh()->name);
        $this->assertSame('original@example.test', $person->fresh()->email);
        $this->assertSame($password, $person->fresh()->password);
        $this->assertSame($version, $person->fresh()->version);
        $this->assertSame('37477637949', $person->fresh()->phone);
        $this->assertSame('visitor', $person->gymLink($a->id)->type);
        $this->assertSame('guest', $person->gymLink($b->id)->type);
        $this->assertTrue($person->gymLink($a->id)->is_blocked);
        $this->assertFalse($person->gymLink($b->id)->is_blocked);
    }

    public function test_phone_is_required_and_invalid_phone_is_rejected(): void
    {
        $gym = Gym::create(['name' => 'A']);
        $this->actingAs($this->actor($gym));
        foreach (['', 'abc', '123'] as $phone) {
            $this->post(route('person.store', ['locale' => 'hy']), ['phone' => $phone])->assertSessionHasErrors('phone');
        }
        $this->assertDatabaseCount('people', 0);
    }

    public function test_existing_identity_lookup_is_global_but_reports_current_gym_link(): void
    {
        $a = Gym::create(['name' => 'A']);
        $b = Gym::create(['name' => 'B']);
        $person = $this->person();
        $person->gyms()->attach($a->id);
        $this->actingAs($this->actor($b))->getJson(route('contact-notes.lookup', ['locale' => 'hy', 'phone' => '077637949']))
            ->assertOk()->assertJsonPath('existing_person_id', $person->id)->assertJsonPath('already_linked', false)
            ->assertJsonPath('person.name', 'Original');
    }

    public function test_card_and_type_edit_only_change_the_current_gym(): void
    {
        $a = Gym::create(['name' => 'A']);
        $b = Gym::create(['name' => 'B']);
        $person = $this->person();
        $person->gyms()->attach([$a->id, $b->id]);
        $cardA = $this->card($person, $a, 'A');
        $cardB = $this->card($person, $b, 'B');
        $newCard = EntryCode::create(['gym_id' => $b->id, 'token' => 'B2', 'type' => 'rfId', 'status' => true, 'activation' => false]);
        $this->actingAs($this->actor($b))->patch(route('person.update', ['locale' => 'hy', 'id' => $person->id]), [
            'type' => 'guest', 'entry_code_id' => $newCard->id, 'name' => 'Tampered', 'phone' => '091111111',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('entry_permissions', ['entry_code_id' => $cardA->id, 'relation_id' => $person->id]);
        $this->assertDatabaseHas('entry_permissions', ['entry_code_id' => $newCard->id, 'relation_id' => $person->id]);
        $this->assertDatabaseMissing('entry_permissions', ['entry_code_id' => $cardB->id]);
        $this->assertSame('Original', $person->fresh()->name);
        $this->assertSame('37477637949', $person->fresh()->phone);
        $this->assertSame('visitor', $person->gymLink($a->id)->type);
        $this->assertSame('guest', $person->gymLink($b->id)->type);
    }

    public function test_block_is_per_gym_and_preserves_the_first_actor_and_time(): void
    {
        $a = Gym::create(['name' => 'A']);
        $b = Gym::create(['name' => 'B']);
        $person = $this->person();
        $person->gyms()->attach([$a->id, $b->id]);
        $actor = $this->actor($b);
        $this->actingAs($actor)->patch(route('person.block', ['locale' => 'hy', 'id' => $person->id]))->assertRedirect();
        $link = $person->gymLink($b->id);
        $blockedAt = $link->blocked_at->toDateTimeString();
        $version = $link->version;
        $this->assertTrue($link->is_blocked);
        $this->assertSame($actor->id, $link->blocked_by);
        $this->assertFalse($person->gymLink($a->id)->is_blocked);
        $this->actingAs($this->actor($b))->patch(route('person.block', ['locale' => 'hy', 'id' => $person->id]))->assertRedirect();
        $this->assertSame($blockedAt, $link->fresh()->blocked_at->toDateTimeString());
        $this->assertSame($version, $link->fresh()->version);
        $this->assertSame($actor->id, $link->fresh()->blocked_by);
    }

    public function test_profile_scopes_cards_and_gyms_and_owner_can_select_a_linked_gym(): void
    {
        $a = Gym::create(['name' => 'A']);
        $b = Gym::create(['name' => 'B']);
        $person = $this->person();
        $person->gyms()->attach([$a->id, $b->id]);
        $this->card($person, $a, 'A');
        $this->card($person, $b, 'B');
        $person->gymLink($b->id)->update(['type' => 'guest']);
        $this->actingAs($this->actor($b))->get(route('person.profile', ['locale' => 'hy', 'id' => $person->id]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->where('selectedGymId', $b->id)
            ->where('entryCode.token', 'B')->where('person.type', 'guest')->has('person.gyms', 1));
        $this->actingAs($this->actor($a, 'owner'))->get(route('person.profile', ['locale' => 'hy', 'id' => $person->id, 'gym_id' => $b->id]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->has('profileGyms', 2)->where('entryCode.token', 'B'));
    }

    public function test_documents_are_scoped_and_unlinked_people_cannot_be_accessed(): void
    {
        $a = Gym::create(['name' => 'A']);
        $b = Gym::create(['name' => 'B']);
        $person = $this->person();
        $person->gyms()->attach($a->id);
        $doc = Document::create(['gym_id' => $a->id, 'owner_type' => Person::class, 'owner_id' => $person->id, 'type' => 'contract', 'file_path' => 'a.pdf']);
        $this->actingAs($this->actor($b))->getJson('/documents/person/'.$person->id)->assertForbidden();
        $this->deleteJson('/documents/'.$doc->id)->assertForbidden();
        $this->deleteJson('/tables/document/'.$doc->id)->assertForbidden();
        $person->gyms()->attach($b->id);
        $this->getJson('/documents/person/'.$person->id)->assertOk()->assertJsonCount(0);
    }

    public function test_owner_cannot_create_or_mutate_membership_sales(): void
    {
        $gym = Gym::create(['name' => 'A']);
        $person = $this->person();
        $this->actingAs($this->actor($gym, 'owner'));
        $this->get(route('membership_sale.create', ['locale' => 'hy', 'person' => $person->id]))->assertForbidden();
        $this->post(route('membership_sale.store', ['locale' => 'hy', 'person' => $person->id]), [])->assertForbidden();
    }

    public function test_removal_only_detaches_the_current_gym_and_reattachment_keeps_its_uuid(): void
    {
        $a = Gym::create(['name' => 'A']);
        $b = Gym::create(['name' => 'B']);
        $person = $this->person();
        $person->gyms()->attach([$a->id, $b->id]);
        $aCard = $this->card($person, $a, 'A');
        $this->card($person, $b, 'B');
        $uuid = $person->gymLink($b->id)->uuid;
        $this->actingAs($this->actor($b))->deleteJson('/tables/person/'.$person->id)->assertOk();
        $this->assertNotNull($person->fresh());
        $this->assertNull($person->gymLink($b->id));
        $this->assertDatabaseHas('entry_permissions', ['entry_code_id' => $aCard->id]);
        $this->post(route('person.store', ['locale' => 'hy']), [
            'phone' => '077637949', 'type' => 'visitor', 'entry_code_mode' => 'new', 'entry_code_token' => 'B-RESTORED',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($uuid, $person->gymLink($b->id)->uuid);
        $this->assertDatabaseCount('gym_person', 2);
    }

    public function test_turnstile_duplicate_detection_does_not_mix_gyms(): void
    {
        $a = Gym::create(['name' => 'A']);
        $b = Gym::create(['name' => 'B']);
        $person = $this->person();
        $at = Carbon::parse('2026-11-19 10:00:00', 'Asia/Yerevan');
        AttendanceSheet::create(['relation_type' => Person::class, 'relation_id' => $person->id, 'gym_id' => $a->id,
            'direction' => 'entry', 'date' => $at, 'created_at' => $at]);
        $reflection = new ReflectionClass(EntryExitSystemService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $check = $reflection->getMethod('isDuplicatePersonEntryOnDetectedDate');
        $this->assertTrue($check->invoke($service, $person, 'entry', $at, $a->id));
        $this->assertFalse($check->invoke($service, $person, 'entry', $at, $b->id));
    }
}
