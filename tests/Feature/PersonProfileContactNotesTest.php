<?php

namespace Tests\Feature;

use App\Models\ContactNote;
use App\Models\Gym;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Services\MembershipSales\ContactNoteOwnershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PersonProfileContactNotesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('sync.enabled', false);
    }

    public function test_profile_shows_phone_notes_and_saves_a_new_note_to_the_same_contact(): void
    {
        $gym = Gym::query()->create(['name' => 'Central']);
        $seller = $this->userWithRole('sales_manager', $gym);
        $person = $this->personInGym($gym);
        $first = app(ContactNoteOwnershipService::class)->create($seller, '099123456', 'First call');
        $otherGym = Gym::query()->create(['name' => 'Other']);
        $otherSeller = $this->userWithRole('sales_manager', $otherGym);
        app(ContactNoteOwnershipService::class)->create($otherSeller, '+37499123456', 'Private to other gym');

        $this->actingAs($seller)->get(route('person.profile', ['locale' => 'en', 'id' => $person->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('People/Profile')
                ->where('contactNotes.0.id', $first->id)
                ->has('contactNotes', 1)
                ->where('canAddContactNote', true)
                ->etc());

        $this->actingAs($seller)->post(route('person.profile.notes.store', [
            'locale' => 'en', 'id' => $person->id,
        ]), ['note' => 'Follow up'])->assertRedirect();

        $new = ContactNote::query()->where('note', 'Follow up')->sole();
        $this->assertSame($first->contact_profile_id, $new->contact_profile_id);
        $this->assertSame($seller->id, $new->user_id);

        $this->actingAs($seller)->get(route('person.profile', ['locale' => 'en', 'id' => $person->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('contactNotes.0.note', 'Follow up')
                ->where('contactNotes.1.note', 'First call')
                ->has('contactNotes', 2)
                ->etc());
    }

    public function test_other_roles_can_create_the_first_contact_note_with_a_gym_sales_manager(): void
    {
        $gym = Gym::query()->create(['name' => 'Central']);
        $seller = $this->userWithRole('sales_manager', $gym);
        $manager = $this->userWithRole('manager', $gym);
        $otherSeller = $this->userWithRole('sales_manager', Gym::query()->create(['name' => 'Other']));
        $person = $this->personInGym($gym);

        $this->actingAs($manager)->get(route('person.profile', ['locale' => 'en', 'id' => $person->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canAddContactNote', true)
                ->where('contactNoteRequiresSalesManager', true)
                ->where('contactNoteSalesManagers.0.id', $seller->id)
                ->etc());
        $this->actingAs($manager)->post(route('person.profile.notes.store', [
            'locale' => 'en', 'id' => $person->id,
        ]), ['note' => 'Missing owner'])->assertSessionHasErrors('sales_manager_id');
        $this->post(route('person.profile.notes.store', [
            'locale' => 'en', 'id' => $person->id,
        ]), ['note' => 'Wrong owner', 'sales_manager_id' => $otherSeller->id])
            ->assertSessionHasErrors('sales_manager_id');

        $this->actingAs($manager)->post(route('person.profile.notes.store', [
            'locale' => 'en', 'id' => $person->id,
        ]), ['note' => 'First call', 'sales_manager_id' => $seller->id])->assertRedirect();

        $note = ContactNote::query()->sole();
        $this->assertSame($seller->id, $note->user_id);
        $this->assertSame($manager->id, $note->writer_id);
        $this->assertSame('+37499123456', $note->phone_number);
        $this->assertSame('Client', $note->profile->name);
        $this->get(route('contact-notes.index', ['locale' => 'en']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('contacts.data.0.user_id', $seller->id)
                ->where('contacts.data.0.members.0.notes.0.writer.id', $manager->id)
                ->etc());
    }

    public function test_other_sales_manager_can_note_existing_contact_without_changing_its_owner(): void
    {
        $gym = Gym::query()->create(['name' => 'Central']);
        $owner = $this->userWithRole('sales_manager', $gym);
        $writer = $this->userWithRole('sales_manager', $gym);
        $person = $this->personInGym($gym);
        $first = app(ContactNoteOwnershipService::class)->create($owner, '099123456', 'First');

        $this->actingAs($writer)->get(route('person.profile', ['locale' => 'en', 'id' => $person->id]))
            ->assertInertia(fn (Assert $page) => $page->where('canAddContactNote', true)->etc());
        $this->actingAs($writer)->post(route('person.profile.notes.store', [
            'locale' => 'en', 'id' => $person->id,
        ]), ['note' => 'Second'])->assertRedirect();

        $second = ContactNote::query()->where('note', 'Second')->sole();
        $this->assertSame($first->contact_profile_id, $second->contact_profile_id);
        $this->assertSame($owner->id, $second->user_id);
        $this->assertSame($writer->id, $second->writer_id);
        $this->assertSame($owner->id, $first->profile->fresh()->user_id);
    }

    public function test_every_gym_role_can_add_a_note_to_an_existing_profile(): void
    {
        $gym = Gym::query()->create(['name' => 'Central']);
        $otherGym = Gym::query()->create(['name' => 'Other']);
        $seller = $this->userWithRole('sales_manager', $gym);
        $person = $this->personInGym($gym);
        $outside = Person::query()->create([
            'name' => 'Outside', 'phone' => '+37498123456', 'type' => 'visitor',
        ]);
        $outside->gyms()->attach($otherGym->id);
        $first = app(ContactNoteOwnershipService::class)->create($seller, '099123456', 'First');

        foreach (['manager', 'admin', 'super_admin', 'owner', 'founder', 'trainer'] as $role) {
            $viewer = $this->userWithRole($role, $gym);
            $this->actingAs($viewer)->get(route('person.profile', ['locale' => 'en', 'id' => $person->id]))
                ->assertInertia(fn (Assert $page) => $page
                    ->where('canAddContactNote', true)
                    ->where('contactNoteRequiresSalesManager', false)
                    ->etc());
            $this->post(route('person.profile.notes.store', [
                'locale' => 'en', 'id' => $person->id,
            ]), ['note' => 'Written by '.$role])->assertRedirect();
            $this->assertDatabaseHas('contact_notes', [
                'contact_profile_id' => $first->contact_profile_id,
                'user_id' => $seller->id,
                'writer_id' => $viewer->id,
                'note' => 'Written by '.$role,
            ]);
            $this->post(route('person.profile.notes.store', [
                'locale' => 'en', 'id' => $outside->id,
            ]), ['note' => 'Wrong gym'])->assertStatus($role === 'owner' ? 403 : 404);
        }
    }

    public function test_people_list_shows_the_latest_note_for_each_phone_in_the_current_gym(): void
    {
        $gym = Gym::query()->create(['name' => 'Central']);
        $seller = $this->userWithRole('sales_manager', $gym);
        $person = $this->personInGym($gym);
        app(ContactNoteOwnershipService::class)->create($seller, '099123456', 'Earlier note');
        app(ContactNoteOwnershipService::class)->create($seller, '+374 99 123456', 'Latest note');

        $otherGym = Gym::query()->create(['name' => 'Other']);
        $otherSeller = $this->userWithRole('sales_manager', $otherGym);
        app(ContactNoteOwnershipService::class)->create($otherSeller, '99123456', 'Other gym note');

        $this->actingAs($seller)->get(route('person.list', ['locale' => 'en']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('People/List')
                ->where('people.data.0.id', $person->id)
                ->where('people.data.0.latest_contact_note', 'Latest note')
                ->etc());
    }

    private function userWithRole(string $roleName, Gym $gym): User
    {
        $role = Role::query()->firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
            'g_name' => $roleName,
        ]);
        $user = User::factory()->create(['gym_id' => $gym->id]);
        $user->assignRole($role);

        return $user;
    }

    private function personInGym(Gym $gym): Person
    {
        $person = Person::query()->create([
            'name' => 'Client',
            'surname' => 'Visitor',
            'phone' => '+37499123456',
            'type' => 'visitor',
        ]);
        $person->gyms()->attach($gym->id);

        return $person;
    }
}
