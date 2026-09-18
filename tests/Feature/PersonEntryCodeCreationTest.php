<?php

namespace Tests\Feature;

use App\DTO\People\PersonDTO;
use App\Models\EntryCode;
use App\Models\EntryPermission;
use App\Models\Gym;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Services\People\PersonService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PersonEntryCodeCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_uses_the_gyms_entry_code_type(): void
    {
        $gym = Gym::query()->create([
            'name' => 'Main gym',
            'entry_code_type' => 'FaceId',
        ]);
        $user = $this->userWithRole($gym);

        $this->actingAs($user)
            ->get(route('person.create', ['locale' => 'hy']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('People/Create')
                ->where('entryCodeType', 'FaceId')
                ->has('entryCodes', 0));
    }

    public function test_person_can_be_created_with_a_new_entry_code_in_one_request(): void
    {
        $gym = Gym::query()->create([
            'name' => 'Main gym',
            'entry_code_type' => 'FaceId',
        ]);
        $user = $this->userWithRole($gym);

        $response = $this->actingAs($user)->post(
            route('person.store', ['locale' => 'hy']),
            $this->personPayload([
                'entry_code_mode' => 'new',
                'entry_code_token' => 'FACE-1001',
            ]),
        );

        $person = Person::query()->sole();
        $entryCode = EntryCode::query()->sole();
        $permission = EntryPermission::query()->sole();

        $response->assertRedirect(route('person.edit', [
            'locale' => 'hy',
            'id' => $person->id,
        ]));
        $this->assertSame($gym->id, $entryCode->gym_id);
        $this->assertSame('FACE-1001', $entryCode->token);
        $this->assertSame('FaceId', $entryCode->type);
        $this->assertTrue((bool) $entryCode->activation);
        $this->assertTrue(Str::isUuid($person->uuid));
        $this->assertTrue(Str::isUuid($entryCode->uuid));
        $this->assertTrue(Str::isUuid($permission->uuid));
        $this->assertSame($entryCode->id, $permission->entry_code_id);
        $this->assertSame(Person::class, $permission->relation_type);
        $this->assertSame($person->id, $permission->relation_id);
        $this->assertTrue($person->gyms()->whereKey($gym->id)->exists());
    }

    public function test_missing_or_invalid_gym_entry_code_type_falls_back_to_rfid(): void
    {
        config()->set('sync.enabled', false);
        $this->assertSame('rfId', (new Gym)->resolvedEntryCodeType());

        $gym = Gym::query()->create([
            'name' => 'Legacy gym',
            'entry_code_type' => '',
        ]);
        $user = $this->userWithRole($gym);

        $this->actingAs($user)->post(
            route('person.store', ['locale' => 'hy']),
            $this->personPayload([
                'entry_code_mode' => 'new',
                'entry_code_token' => 'RFID-LEGACY-1001',
            ]),
        )->assertSessionHasNoErrors();

        $this->assertSame('rfId', EntryCode::query()->sole()->type);
    }

    public function test_person_can_be_created_with_an_existing_available_entry_code(): void
    {
        $gym = Gym::query()->create([
            'name' => 'Main gym',
            'entry_code_type' => 'rfId',
        ]);
        $user = $this->userWithRole($gym);
        $entryCode = EntryCode::query()->create([
            'gym_id' => $gym->id,
            'token' => 'RFID-1001',
            'status' => true,
            'activation' => false,
            'type' => 'rfId',
        ]);

        $this->actingAs($user)->post(
            route('person.store', ['locale' => 'hy']),
            $this->personPayload([
                'entry_code_mode' => 'existing',
                'entry_code_id' => $entryCode->id,
            ]),
        )->assertSessionHasNoErrors();

        $person = Person::query()->sole();

        $this->assertSame(1, EntryCode::query()->count());
        $this->assertDatabaseHas('entry_permissions', [
            'entry_code_id' => $entryCode->id,
            'relation_type' => Person::class,
            'relation_id' => $person->id,
            'status' => true,
        ]);
        $this->assertTrue((bool) $entryCode->fresh()->activation);
    }

    public function test_duplicate_inline_entry_code_is_rejected_without_creating_a_person(): void
    {
        $gym = Gym::query()->create([
            'name' => 'Main gym',
            'entry_code_type' => 'rfId',
        ]);
        $user = $this->userWithRole($gym);
        EntryCode::query()->create([
            'gym_id' => $gym->id,
            'token' => 'RFID-DUPLICATE',
            'status' => true,
            'activation' => false,
            'type' => 'rfId',
        ]);

        $this->actingAs($user)->post(
            route('person.store', ['locale' => 'hy']),
            $this->personPayload([
                'entry_code_mode' => 'new',
                'entry_code_token' => 'RFID-DUPLICATE',
            ]),
        )->assertSessionHasErrors('entry_code_token');

        $this->assertDatabaseCount('people', 0);
        $this->assertDatabaseCount('entry_codes', 1);
        $this->assertDatabaseCount('entry_permissions', 0);
    }

    public function test_new_entry_code_is_rolled_back_when_person_creation_fails(): void
    {
        $gym = Gym::query()->create([
            'name' => 'Main gym',
            'entry_code_type' => 'rfId',
        ]);
        $user = $this->userWithRole($gym);
        Person::query()->create([
            'name' => 'Existing',
            'email' => 'john@example.com',
            'password' => Hash::make('password'),
            'phone' => '+37499000001',
            'type' => 'visitor',
            'birth_date' => '1990-01-01',
        ]);
        $this->actingAs($user);

        try {
            app(PersonService::class)->store(PersonDTO::fromArray($this->personPayload([
                'entry_code_mode' => 'new',
                'entry_code_token' => 'ROLLBACK-1001',
            ])));
            $this->fail('The duplicate person email should fail.');
        } catch (QueryException) {
            // The transaction must also remove the entry code created before the person insert.
        }

        $this->assertDatabaseMissing('entry_codes', ['token' => 'ROLLBACK-1001']);
        $this->assertDatabaseCount('entry_permissions', 0);
    }

    private function userWithRole(Gym $gym): User
    {
        $role = Role::query()->create([
            'name' => 'super_admin',
            'guard_name' => 'web',
            'g_name' => 'super_admin',
        ]);
        $user = User::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Admin',
            'surname' => 'User',
            'email' => Str::uuid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    /** @param array<string, mixed> $overrides */
    private function personPayload(array $overrides = []): array
    {
        return [
            'name' => 'John',
            'surname' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'password',
            'phone' => '+37499123456',
            'type' => 'visitor',
            'birth_date' => '1990-01-01',
            'gender' => 'male',
            ...$overrides,
        ];
    }
}
