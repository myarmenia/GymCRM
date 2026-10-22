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

    public function test_profile_can_create_the_first_contact_note_but_other_roles_cannot_add_one(): void
    {
        $gym = Gym::query()->create(['name' => 'Central']);
        $seller = $this->userWithRole('sales_manager', $gym);
        $manager = $this->userWithRole('manager', $gym);
        $person = $this->personInGym($gym);

        $this->actingAs($manager)->get(route('person.profile', ['locale' => 'en', 'id' => $person->id]))
            ->assertInertia(fn (Assert $page) => $page->where('canAddContactNote', false)->etc());
        $this->actingAs($manager)->post(route('person.profile.notes.store', [
            'locale' => 'en', 'id' => $person->id,
        ]), ['note' => 'Not allowed'])->assertForbidden();

        $this->actingAs($seller)->post(route('person.profile.notes.store', [
            'locale' => 'en', 'id' => $person->id,
        ]), ['note' => 'First call'])->assertRedirect();

        $note = ContactNote::query()->sole();
        $this->assertSame($seller->id, $note->user_id);
        $this->assertSame('+37499123456', $note->phone_number);
        $this->assertSame('Client', $note->profile->name);
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
