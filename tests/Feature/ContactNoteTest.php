<?php

namespace Tests\Feature;

use App\Models\ContactNote;
use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContactNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_manager_creates_a_contact_and_adds_a_note_without_reentering_phone(): void
    {
        $gym = Gym::query()->create(['name' => 'Central']);
        $manager = $this->userWithRole('sales_manager', $gym->id);

        $this->actingAs($manager)->post(route('contact-notes.store', ['locale' => 'en']), [
            'phone_number' => '+374 99 123456',
            'note' => 'First call',
        ])->assertRedirect();

        $root = ContactNote::query()->sole();
        $this->actingAs($manager)->post(route('contact-notes.add-note', [
            'locale' => 'en', 'contactNote' => $root->id,
        ]), ['note' => 'Called again'])->assertRedirect();

        $this->assertDatabaseHas('contact_notes', [
            'user_id' => $manager->id,
            'phone_number' => '+374 99 123456',
            'note' => 'Called again',
        ]);

        $this->actingAs($manager)->get(route('contact-notes.index', ['locale' => 'en']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('ContactNotes/Index')
                ->where('contacts.total', 1)
                ->where('contacts.data.0.id', $root->id)
                ->where('contacts.data.0.children.0.note', 'Called again')
                ->where('canCreate', true)
                ->etc());
    }

    public function test_supervisor_can_filter_managers_and_sales_manager_cannot_see_others(): void
    {
        $gym = Gym::query()->create(['name' => 'Central']);
        $otherGym = Gym::query()->create(['name' => 'Other']);
        $first = $this->userWithRole('sales_manager', $gym->id);
        $second = $this->userWithRole('sales_manager', $gym->id);
        $outside = $this->userWithRole('sales_manager', $otherGym->id);
        $admin = $this->userWithRole('admin', $gym->id);

        foreach ([$first, $second, $outside] as $user) {
            ContactNote::create(['user_id' => $user->id, 'phone_number' => '123', 'note' => 'Call']);
        }

        $this->actingAs($admin)->get(route('contact-notes.index', ['locale' => 'en']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('contacts.total', 2)
                ->where('canCreate', false)
                ->etc());

        $this->actingAs($admin)->get(route('contact-notes.index', [
            'locale' => 'en', 'sales_manager_id' => $second->id,
        ]))->assertInertia(fn (Assert $page) => $page
            ->where('contacts.total', 1)
            ->where('contacts.data.0.user_id', $second->id)
            ->etc());

        $this->actingAs($first)->get(route('contact-notes.index', [
            'locale' => 'en', 'sales_manager_id' => $second->id,
        ]))->assertInertia(fn (Assert $page) => $page
            ->where('contacts.total', 1)
            ->where('contacts.data.0.user_id', $first->id)
            ->etc());

        $this->actingAs($admin)->post(route('contact-notes.store', ['locale' => 'en']), [
            'phone_number' => '321', 'note' => 'Forbidden',
        ])->assertForbidden();

        $otherNote = ContactNote::query()->where('user_id', $second->id)->firstOrFail();
        $this->actingAs($first)->post(route('contact-notes.add-note', [
            'locale' => 'en', 'contactNote' => $otherNote->id,
        ]), ['note' => 'Forbidden'])->assertForbidden();
    }

    public function test_phone_belongs_to_one_sales_manager_per_gym_but_can_be_used_in_another_gym(): void
    {
        $gym = Gym::query()->create(['name' => 'Central']);
        $otherGym = Gym::query()->create(['name' => 'Other']);
        $owner = $this->userWithRole('sales_manager', $gym->id);
        $competitor = $this->userWithRole('sales_manager', $gym->id);
        $outside = $this->userWithRole('sales_manager', $otherGym->id);

        $this->actingAs($owner)->post(route('contact-notes.store', ['locale' => 'en']), [
            'phone_number' => '+374 99 123-456', 'note' => 'First',
        ])->assertRedirect();

        $this->actingAs($competitor)->post(route('contact-notes.store', ['locale' => 'en']), [
            'phone_number' => '099123456', 'note' => 'Claim again',
        ])->assertSessionHasErrors('phone_number');
        $this->assertSame(1, ContactNote::query()->count());

        $this->actingAs($outside)->post(route('contact-notes.store', ['locale' => 'en']), [
            'phone_number' => '99123456', 'note' => 'Other gym',
        ])->assertRedirect();
        $this->assertSame(2, ContactNote::query()->count());

        $root = ContactNote::query()->where('user_id', $owner->id)->sole();
        $this->actingAs($owner)->post(route('contact-notes.add-note', [
            'locale' => 'en', 'contactNote' => $root->id,
        ]), ['note' => 'Follow up'])->assertRedirect();
        $this->assertSame(3, ContactNote::query()->count());
    }

    public function test_contacts_are_newest_first_and_filters_match_child_note_dates(): void
    {
        $manager = $this->userWithRole('sales_manager');
        $old = ContactNote::create([
            'user_id' => $manager->id, 'phone_number' => '111', 'note' => 'First',
        ]);
        $old->forceFill(['created_at' => '2026-01-01 10:00:00'])->save();

        $child = ContactNote::create([
            'user_id' => $manager->id, 'phone_number' => '111', 'note' => 'Follow up',
        ]);
        $child->forceFill(['created_at' => '2026-01-03 10:00:00'])->save();

        $new = ContactNote::create([
            'user_id' => $manager->id, 'phone_number' => '222', 'note' => 'New contact',
        ]);
        $new->forceFill(['created_at' => '2026-01-02 10:00:00'])->save();

        $this->actingAs($manager)->get(route('contact-notes.index', ['locale' => 'en']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('contacts.total', 2)
                ->where('contacts.data.0.id', $old->id)
                ->where('contacts.data.0.children.0.id', $child->id)
                ->where('contacts.data.1.id', $new->id)
                ->etc());

        $this->actingAs($manager)->get(route('contact-notes.index', [
            'locale' => 'en', 'date_from' => '2026-01-03', 'date_to' => '2026-01-03',
        ]))->assertInertia(fn (Assert $page) => $page
            ->where('contacts.total', 1)
            ->where('contacts.data.0.id', $old->id)
            ->etc());

        $this->actingAs($manager)->get(route('contact-notes.index', [
            'locale' => 'en', 'phone_number' => '22',
        ]))->assertInertia(fn (Assert $page) => $page
            ->where('contacts.total', 1)
            ->where('contacts.data.0.id', $new->id)
            ->etc());
    }

    private function userWithRole(string $roleName, ?int $gymId = null): User
    {
        $role = Role::query()->firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
            'g_name' => $roleName,
        ]);
        $user = User::factory()->create(['gym_id' => $gymId]);
        $user->assignRole($role);

        return $user;
    }
}
