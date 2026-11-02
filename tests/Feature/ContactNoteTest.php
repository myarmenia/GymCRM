<?php

namespace Tests\Feature;

use App\Models\ContactNote;
use App\Models\Gym;
use App\Models\Role;
use App\Models\User;
use App\Services\MembershipSales\ContactNoteOwnershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContactNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_gym_role_can_view_its_sales_managers_contacts_and_add_only_notes(): void
    {
        $gym = Gym::query()->create(['name' => 'Central']);
        $otherGym = Gym::query()->create(['name' => 'Other']);
        $seller = $this->userWithRole('sales_manager', $gym->id);
        $outsideSeller = $this->userWithRole('sales_manager', $otherGym->id);
        $owned = app(ContactNoteOwnershipService::class)->create($seller, '099123456', 'Central contact');
        $outside = app(ContactNoteOwnershipService::class)->create($outsideSeller, '098765432', 'Other contact');
        $outside->profile->update(['group_uuid' => $owned->profile->group_uuid]);

        foreach (['manager', 'admin', 'super_admin', 'owner', 'founder', 'trainer'] as $role) {
            $viewer = $this->userWithRole($role, $gym->id);
            $this->actingAs($viewer)->get(route('contact-notes.index', ['locale' => 'en']))
                ->assertInertia(fn (Assert $page) => $page
                    ->where('contacts.total', 1)
                    ->where('contacts.data.0.id', $owned->contact_profile_id)
                    ->has('contacts.data.0.members', 1)
                    ->where('canCreate', false)
                    ->where('canAddNote', true)
                    ->where('canShowPersonActions', in_array($role, ['manager', 'admin', 'super_admin'], true))
                    ->etc());

            $this->post(route('contact-notes.add-profile-note', [
                'locale' => 'en', 'contactProfile' => $owned->contact_profile_id,
            ]), ['note' => 'Note by '.$role])->assertRedirect();
            $this->assertDatabaseHas('contact_notes', [
                'contact_profile_id' => $owned->contact_profile_id,
                'user_id' => $seller->id,
                'writer_id' => $viewer->id,
                'note' => 'Note by '.$role,
            ]);
            $this->post(route('contact-notes.add-profile-note', [
                'locale' => 'en', 'contactProfile' => $outside->contact_profile_id,
            ]), ['note' => 'Wrong gym'])->assertForbidden();
            $this->post(route('contact-notes.store', ['locale' => 'en']), [
                'phone_number' => '123', 'note' => 'No new contact',
            ])->assertForbidden();
        }

        $otherUuid = (string) \Illuminate\Support\Str::uuid();
        $otherProfileId = DB::table('contact_profiles')->insertGetId([
            'uuid' => $otherUuid,
            'group_uuid' => $otherUuid,
            'gym_id' => $gym->id,
            'user_id' => $viewer->id,
            'is_primary' => true,
            'phone_number' => '091111111',
            'phone_normalized' => '091111111',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->get(route('contact-notes.index', ['locale' => 'en']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('contacts.total', 1)
                ->etc());
        $this->post(route('contact-notes.add-profile-note', [
            'locale' => 'en', 'contactProfile' => $otherProfileId,
        ]), ['note' => 'Not a sales manager contact'])->assertForbidden();

        $noGym = $this->userWithRole('owner');
        DB::table('users')->where('id', $noGym->id)->update(['gym_id' => null]);
        $noGym->refresh();
        $this->actingAs($noGym)->get(route('contact-notes.index', ['locale' => 'en']))
            ->assertForbidden();
        $this->post(route('contact-notes.add-profile-note', [
            'locale' => 'en', 'contactProfile' => $owned->contact_profile_id,
        ]), ['note' => 'No gym'])->assertForbidden();
    }

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
                ->where('contacts.data.0.id', $root->contact_profile_id)
                ->where('contacts.data.0.members.0.notes.0.note', 'Called again')
                ->where('canCreate', true)
                ->etc());
    }

    public function test_sales_managers_see_same_gym_contacts_and_can_add_notes_as_themselves(): void
    {
        $gym = Gym::query()->create(['name' => 'Central']);
        $otherGym = Gym::query()->create(['name' => 'Other']);
        $first = $this->userWithRole('sales_manager', $gym->id);
        $second = $this->userWithRole('sales_manager', $gym->id);
        $outside = $this->userWithRole('sales_manager', $otherGym->id);
        $admin = $this->userWithRole('admin', $gym->id);

        foreach ([$first, $second, $outside] as $user) {
            app(ContactNoteOwnershipService::class)->create($user, (string) $user->id.'123', 'Call');
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
            ->where('contacts.data.0.user_id', $second->id)
            ->etc());

        $this->actingAs($first)->get(route('contact-notes.index', ['locale' => 'en']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('contacts.total', 2)
                ->has('salesManagers', 2)
                ->etc());

        $this->actingAs($admin)->post(route('contact-notes.store', ['locale' => 'en']), [
            'phone_number' => '321', 'note' => 'Forbidden',
        ])->assertForbidden();

        $otherNote = ContactNote::query()->where('user_id', $second->id)->firstOrFail();
        $this->actingAs($first)->post(route('contact-notes.add-note', [
            'locale' => 'en', 'contactNote' => $otherNote->id,
        ]), ['note' => 'Shared follow up'])->assertRedirect();
        $shared = ContactNote::query()->where('note', 'Shared follow up')->sole();
        $this->assertSame($second->id, $shared->user_id);
        $this->assertSame($first->id, $shared->writer_id);
        $this->assertSame($otherNote->contact_profile_id, $shared->contact_profile_id);

        $this->actingAs($first)->post(route('contact-notes.add-profile-note', [
            'locale' => 'en', 'contactProfile' => $otherNote->contact_profile_id,
        ]), ['note' => 'From contact list'])->assertRedirect();
        $profileNote = ContactNote::query()->where('note', 'From contact list')->sole();
        $this->assertSame($second->id, $profileNote->user_id);
        $this->assertSame($first->id, $profileNote->writer_id);

        $this->actingAs($first)->get(route('contact-notes.index', [
            'locale' => 'en', 'sales_manager_id' => $second->id,
        ]))->assertInertia(fn (Assert $page) => $page
            ->where('contacts.data.0.members.0.notes.0.writer.id', $first->id)
            ->etc());

        $outsideNote = ContactNote::query()->where('user_id', $outside->id)->sole();
        $this->actingAs($first)->post(route('contact-notes.add-note', [
            'locale' => 'en', 'contactNote' => $outsideNote->id,
        ]), ['note' => 'Wrong gym'])->assertForbidden();
        $this->actingAs($first)->post(route('contact-notes.add-profile-note', [
            'locale' => 'en', 'contactProfile' => $outsideNote->contact_profile_id,
        ]), ['note' => 'Wrong gym'])->assertForbidden();
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
        $gym = Gym::query()->create(['name' => 'Central']);
        $manager = $this->userWithRole('sales_manager', $gym->id);
        $old = app(ContactNoteOwnershipService::class)->create($manager, '111', 'First');
        $old->forceFill(['created_at' => '2026-01-01 10:00:00'])->save();
        $old->profile->forceFill(['created_at' => '2026-01-01 10:00:00'])->save();

        $child = app(ContactNoteOwnershipService::class)->create($manager, '111', 'Follow up');
        $child->forceFill(['created_at' => '2026-01-03 10:00:00'])->save();

        $new = app(ContactNoteOwnershipService::class)->create($manager, '222', 'New contact');
        $new->forceFill(['created_at' => '2026-01-02 10:00:00'])->save();
        $new->profile->forceFill(['created_at' => '2026-01-02 10:00:00'])->save();

        $this->actingAs($manager)->get(route('contact-notes.index', ['locale' => 'en']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('contacts.total', 2)
                ->where('contacts.data.0.id', $old->contact_profile_id)
                ->where('contacts.data.0.members.0.notes.0.id', $child->id)
                ->where('contacts.data.1.id', $new->contact_profile_id)
                ->etc());

        $this->actingAs($manager)->get(route('contact-notes.index', [
            'locale' => 'en', 'date_from' => '2026-01-03', 'date_to' => '2026-01-03',
        ]))->assertInertia(fn (Assert $page) => $page
            ->where('contacts.total', 1)
            ->where('contacts.data.0.id', $old->contact_profile_id)
            ->etc());

        $this->actingAs($manager)->get(route('contact-notes.index', [
            'locale' => 'en', 'phone_number' => '22',
        ]))->assertInertia(fn (Assert $page) => $page
            ->where('contacts.total', 1)
            ->where('contacts.data.0.id', $new->contact_profile_id)
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
