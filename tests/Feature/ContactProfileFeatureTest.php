<?php

namespace Tests\Feature;

use App\Models\ContactNote;
use App\Models\ContactProfile;
use App\Models\Gym;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Services\MembershipSales\ContactNoteOwnerResolver;
use App\Services\MembershipSales\ContactNoteOwnershipService;
use App\Services\MembershipSales\ContactProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactProfileFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_actions_resolve_each_phone_and_prefill_visitor_creation(): void
    {
        $gym = Gym::query()->create(['name' => 'Central']);
        $seller = User::factory()->create(['gym_id' => $gym->id, 'active' => true]);
        $seller->assignRole(Role::query()->firstOrCreate([
            'name' => 'sales_manager', 'guard_name' => 'web', 'g_name' => 'sales_manager',
        ]));
        $this->actingAs($seller)->post(route('contact-notes.store', ['locale' => 'en']), [
            'phone_number' => '099123456', 'note' => 'Interested',
            'name' => 'Main', 'surname' => 'Client', 'email' => 'main@example.test',
            'birth_date' => '2000-01-02', 'gender' => 'female',
        ])->assertRedirect();
        $primary = ContactProfile::query()->where('is_primary', true)->sole();
        $related = app(ContactProfileService::class)->addRelated($seller, $primary, [
            'phone_number' => '098765432', 'name' => 'Child',
        ]);
        $guest = Person::query()->create([
            'name' => 'Child', 'phone' => '+374 98 765432', 'type' => 'guest',
        ]);
        $guest->gyms()->attach($gym->id);

        $this->get(route('contact-notes.index', ['locale' => 'en']))
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('ContactNotes/Index')
                ->where('canSellMembership', true)
                ->where('contacts.data.0.existing_person_id', null)
                ->where('contacts.data.0.existing_person_blocked', false)
                ->where('contacts.data.0.members.0.existing_person_id', null)
                ->where('contacts.data.0.members.1.existing_person_id', $guest->id)
                ->where('contacts.data.0.members.1.existing_person_blocked', false)
                ->etc());
        $this->get(route('person.create', ['locale' => 'en', 'phone' => $primary->phone_number]))
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('People/Create')
                ->where('initialPhone', '099123456')
                ->where('initialContact.name', 'Main')
                ->where('initialContact.surname', 'Client')
                ->where('initialContact.email', 'main@example.test')
                ->where('initialContact.birth_date', '2000-01-02')
                ->where('initialContact.gender', 'female')
                ->etc());
        $this->get(route('person.create', ['locale' => 'en', 'phone' => $related->phone_number]))
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('initialContact.name', 'Child')
                ->where('initialContact.email', null)
                ->etc());
        $this->getJson(route('contact-notes.lookup', ['locale' => 'en', 'phone' => '099123456']))
            ->assertOk()->assertJsonPath('profile.name', 'Main')
            ->assertJsonPath('profile.email', 'main@example.test')
            ->assertJsonPath('profile.birth_date', '2000-01-02');

        $manager = User::factory()->create(['gym_id' => $gym->id, 'active' => true]);
        $manager->assignRole(Role::query()->firstOrCreate([
            'name' => 'manager', 'guard_name' => 'web', 'g_name' => 'manager',
        ]));
        $this->actingAs($manager)->get(route('contact-notes.index', ['locale' => 'en']))
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('ContactNotes/Index')
                ->where('canSellMembership', true)
                ->where('contacts.data.0.members.1.existing_person_id', $guest->id)
                ->etc());

        $this->actingAs($seller)->patch(route('person.block', ['locale' => 'en', 'id' => $guest->id]))
            ->assertRedirect();
        $this->get(route('contact-notes.index', ['locale' => 'en']))
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('ContactNotes/Index')
                ->where('contacts.data.0.members.1.existing_person_blocked', true)
                ->etc());

        $visitor = Person::query()->create([
            'name' => 'Main', 'phone' => '+374 99 123456', 'type' => 'visitor',
        ]);
        $visitor->gyms()->attach($gym->id);
        $this->get(route('contact-notes.index', ['locale' => 'en']))
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('ContactNotes/Index')
                ->where('contacts.data.0.existing_person_id', $visitor->id)
                ->where('contacts.data.0.members.0.existing_person_id', $visitor->id)
                ->where('contacts.data.0.members.1.existing_person_id', $guest->id)
                ->etc());
    }

    public function test_latest_blocked_note_on_an_initial_profile_can_be_replaced_by_current_manager(): void
    {
        $gym = Gym::query()->create(['name' => 'Central']);
        $role = Role::query()->firstOrCreate([
            'name' => 'sales_manager', 'guard_name' => 'web', 'g_name' => 'sales_manager',
        ]);
        $first = User::factory()->create(['gym_id' => $gym->id, 'active' => true]);
        $first->assignRole($role);
        $blocked = User::factory()->create(['gym_id' => $gym->id, 'active' => false]);
        $blocked->assignRole($role);
        $seller = User::factory()->create(['gym_id' => $gym->id, 'active' => true]);
        $seller->assignRole($role);

        $firstNote = app(ContactNoteOwnershipService::class)->create($first, '099123456', 'First');
        $firstNote->update(['created_at' => now()->subDays(2)]);
        $profile = ContactProfile::query()->sole();
        ContactNote::query()->create([
            'user_id' => $blocked->id,
            'contact_profile_id' => $profile->id,
            'phone_number' => '+37499123456',
            'note' => 'Latest blocked contact',
            'created_at' => now()->subDay(),
        ]);

        $this->assertNull(app(ContactNoteOwnerResolver::class)->forPhone('099123456', $gym->id));
        app(ContactNoteOwnershipService::class)->create($seller, '099123456', 'New sale');
        $this->assertSame($seller->id, $profile->fresh()->user_id);
        $this->assertSame($seller->id, app(ContactNoteOwnerResolver::class)
            ->forPhone('099123456', $gym->id)?->id);
    }

    public function test_related_phone_keeps_its_own_details_notes_and_sales_manager(): void
    {
        $gym = Gym::query()->create(['name' => 'Central']);
        $role = Role::query()->firstOrCreate([
            'name' => 'sales_manager', 'guard_name' => 'web', 'g_name' => 'sales_manager',
        ]);
        $owner = User::factory()->create(['gym_id' => $gym->id, 'active' => true]);
        $owner->assignRole($role);
        $other = User::factory()->create(['gym_id' => $gym->id, 'active' => true]);
        $other->assignRole($role);

        $this->actingAs($owner)->post(route('contact-notes.store', ['locale' => 'en']), [
            'phone_number' => '099123456', 'note' => 'Main contact',
            'name' => 'Main', 'surname' => 'Customer',
        ])->assertRedirect();
        $primary = ContactProfile::query()->where('is_primary', true)->sole();

        $this->post(route('contact-notes.add-related', ['locale' => 'en', 'contactProfile' => $primary->id]), [
            'phone_number' => '+374 98 765432', 'name' => 'Brother',
            'surname' => 'Customer', 'email' => 'brother@example.com',
            'birth_date' => '2000-01-02', 'gender' => 'male',
            'relationship' => 'Brother', 'note' => 'Interested',
        ])->assertRedirect();
        $related = ContactProfile::query()->where('is_primary', false)->sole();
        $this->assertSame($primary->group_uuid, $related->group_uuid);
        $this->assertSame($owner->id, $related->user_id);
        $this->assertSame($related->id, ContactNote::query()->where('note', 'Interested')->sole()->contact_profile_id);
        $this->assertSame($owner->id, app(ContactNoteOwnerResolver::class)->forPhone('098765432', $gym->id)?->id);

        $this->getJson(route('contact-notes.lookup', ['locale' => 'en', 'phone' => '098765432']))
            ->assertOk()->assertJsonPath('profile.name', 'Brother')
            ->assertJsonPath('profile.email', 'brother@example.com')
            ->assertJsonPath('profile.birth_date', '2000-01-02');

        $this->patch(route('contact-notes.update-profile', ['locale' => 'en', 'contactProfile' => $related->id]), [
            'name' => 'Updated Brother',
        ])->assertRedirect();
        $this->assertSame('Updated Brother', $related->fresh()->name);

        $this->post(route('contact-notes.add-related', ['locale' => 'en', 'contactProfile' => $primary->id]), [
            'phone_number' => '098765432',
        ])->assertSessionHasErrors('phone_number');
        $this->actingAs($other)->post(route('contact-notes.add-related', [
            'locale' => 'en', 'contactProfile' => $primary->id,
        ]), ['phone_number' => '097111111'])->assertForbidden();
    }

    public function test_inactive_manager_group_can_be_reassigned_on_a_new_sale(): void
    {
        $gym = Gym::query()->create(['name' => 'Central']);
        $role = Role::query()->firstOrCreate([
            'name' => 'sales_manager', 'guard_name' => 'web', 'g_name' => 'sales_manager',
        ]);
        $former = User::factory()->create(['gym_id' => $gym->id, 'active' => true]);
        $former->assignRole($role);
        $replacement = User::factory()->create(['gym_id' => $gym->id, 'active' => true]);
        $replacement->assignRole($role);
        app(ContactNoteOwnershipService::class)->create($former, '099123456', 'First');
        $primary = ContactProfile::query()->where('is_primary', true)->sole();
        app(ContactProfileService::class)->addRelated($former, $primary, ['phone_number' => '098765432']);
        $former->update(['active' => false]);

        app(ContactNoteOwnershipService::class)->create($replacement, '098765432', 'New sale');
        $this->assertSame(2, ContactProfile::query()->where('user_id', $replacement->id)->count());
        $this->assertSame($replacement->id, app(ContactNoteOwnerResolver::class)
            ->forPhone('099123456', $gym->id)?->id);
    }

    public function test_customer_creation_rejects_an_existing_phone_in_another_format(): void
    {
        $gym = Gym::query()->create(['name' => 'Central']);
        $role = Role::query()->firstOrCreate([
            'name' => 'sales_manager', 'guard_name' => 'web', 'g_name' => 'sales_manager',
        ]);
        $manager = User::factory()->create(['gym_id' => $gym->id, 'active' => true]);
        $manager->assignRole($role);
        $person = Person::query()->create(['name' => 'Existing', 'phone' => '099 123-456']);
        $person->gyms()->attach($gym->id);

        $this->actingAs($manager)->post(route('person.store', ['locale' => 'en']), [
            'name' => 'Duplicate', 'phone' => '+37499123456', 'type' => 'visitor',
            'birth_date' => '2000-01-01', 'entry_code_mode' => 'new', 'entry_code_token' => 'TEST-ENTRY',
        ])->assertSessionHasErrors('phone');
        $this->assertSame(1, Person::query()->count());
    }

    public function test_existing_standalone_contact_can_be_linked_without_losing_its_notes(): void
    {
        $gym = Gym::query()->create(['name' => 'Central']);
        $role = Role::query()->firstOrCreate([
            'name' => 'sales_manager', 'guard_name' => 'web', 'g_name' => 'sales_manager',
        ]);
        $manager = User::factory()->create(['gym_id' => $gym->id, 'active' => true]);
        $manager->assignRole($role);
        $this->actingAs($manager)->post(route('contact-notes.store', ['locale' => 'en']), [
            'phone_number' => '095584441', 'note' => 'Main note',
        ])->assertRedirect();
        $this->post(route('contact-notes.store', ['locale' => 'en']), [
            'phone_number' => '095584442', 'note' => 'Related note', 'name' => 'Brother',
            'relationship' => '095584441',
        ])->assertRedirect();
        $main = ContactProfile::query()->where('phone_normalized', '37495584441')->sole();
        $related = ContactProfile::query()->where('phone_normalized', '37495584442')->sole();
        $this->assertSame(2, ContactProfile::query()->where('is_primary', true)->count());

        $this->post(route('contact-notes.link-existing', [
            'locale' => 'en', 'contactProfile' => $main->id,
        ]), ['phone_number' => '+374 95 584442', 'relationship' => 'Brother'])->assertRedirect();

        $related->refresh();
        $this->assertFalse($related->is_primary);
        $this->assertSame($main->group_uuid, $related->group_uuid);
        $this->assertSame('Brother', $related->relationship);
        $this->assertSame('Brother', $related->name);
        $this->assertSame($related->id, ContactNote::query()->where('note', 'Related note')->sole()->contact_profile_id);
        $this->get(route('contact-notes.index', ['locale' => 'en']))
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->where('contacts.total', 1)
                ->where('contacts.data.0.id', $main->id)
                ->has('contacts.data.0.members', 2)
                ->etc());

        $this->post(route('contact-notes.link-existing', [
            'locale' => 'en', 'contactProfile' => $main->id,
        ]), ['phone_number' => '095584442'])->assertSessionHasErrors('phone_number');
    }
}
