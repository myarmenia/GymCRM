<?php

namespace Tests\Feature;

use App\Events\TurnstileEntryDetected;
use App\Models\EntryCode;
use App\Models\EntryPermission;
use App\Models\Gym;
use App\Models\Person;
use App\Models\Turnstile;
use App\Repositories\Turnstile\TurnstileRepository;
use App\Services\Turnstile\EntryExitSystemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TurnstileGymScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_mac_address_resolves_the_turnstile_gym_before_checking_an_entry_code(): void
    {
        $turnstileGym = Gym::query()->create(['name' => 'Turnstile gym']);
        $otherGym = Gym::query()->create(['name' => 'Other gym']);

        Turnstile::query()->create([
            'gym_id' => $turnstileGym->id,
            'mac' => 'AA:BB:CC:DD:EE:FF',
        ]);

        $turnstileGymCode = EntryCode::query()->create([
            'gym_id' => $turnstileGym->id,
            'token' => 'SHARED-CARD',
            'type' => 'rfId',
            'status' => true,
            'activation' => true,
        ]);
        EntryCode::query()->create([
            'gym_id' => $otherGym->id,
            'token' => 'SHARED-CARD',
            'type' => 'rfId',
            'status' => true,
            'activation' => true,
        ]);

        $repository = app(TurnstileRepository::class);
        $gymId = $repository->getClientId(' AA:BB:CC:DD:EE:FF ');
        $result = $repository->checkEntryCode('SHARED-CARD', $gymId, 'rfId', false);

        $this->assertSame($turnstileGym->id, $gymId);
        $this->assertTrue($result->result->is($turnstileGymCode));
    }

    public function test_unknown_or_unassigned_mac_address_has_no_gym_context(): void
    {
        $repository = app(TurnstileRepository::class);

        $this->assertNull($repository->getClientId('UNKNOWN'));
        $this->assertNull($repository->getClientId(''));
    }

    public function test_literal_duplicate_code_resolves_the_customer_for_the_scanning_turnstiles_gym(): void
    {
        $firstGym = Gym::query()->create(['name' => 'First gym']);
        $secondGym = Gym::query()->create(['name' => 'Second gym']);
        Turnstile::query()->create(['gym_id' => $firstGym->id, 'mac' => 'FIRST-MAC']);
        Turnstile::query()->create(['gym_id' => $secondGym->id, 'mac' => 'SECOND-MAC']);

        $firstPerson = Person::query()->create([
            'name' => 'First customer',
            'email' => 'first-customer@example.test',
            'password' => bcrypt('password'),
            'phone' => '+37455000001',
        ]);
        $secondPerson = Person::query()->create([
            'name' => 'Second customer',
            'email' => 'second-customer@example.test',
            'password' => bcrypt('password'),
            'phone' => '+37455000002',
        ]);
        $firstPerson->gyms()->attach($firstGym->id);
        $secondPerson->gyms()->attach($secondGym->id);

        $firstCode = EntryCode::query()->create([
            'gym_id' => $firstGym->id,
            'token' => '1',
            'type' => 'rfId',
            'status' => true,
            'activation' => true,
        ]);
        $secondCode = EntryCode::query()->create([
            'gym_id' => $secondGym->id,
            'token' => '1',
            'type' => 'rfId',
            'status' => true,
            'activation' => true,
        ]);
        EntryPermission::query()->create([
            'entry_code_id' => $firstCode->id,
            'relation_id' => $firstPerson->id,
            'relation_type' => Person::class,
            'status' => true,
        ]);
        EntryPermission::query()->create([
            'entry_code_id' => $secondCode->id,
            'relation_id' => $secondPerson->id,
            'relation_type' => Person::class,
            'status' => true,
        ]);

        Event::fake([TurnstileEntryDetected::class]);

        // entry_code_type is deliberately omitted: short literal codes must
        // not be converted to binary before their gym-scoped lookup.
        app(EntryExitSystemService::class)->ees((object) [
            'mac' => 'FIRST-MAC',
            'entry_code' => '1',
            'type' => 'rfId',
            'auto_add' => false,
        ]);

        Event::assertDispatched(TurnstileEntryDetected::class, function (TurnstileEntryDetected $event) use ($firstGym, $firstPerson): bool {
            return (int) $event->clientId === $firstGym->id
                && ($event->payload['entry_code'] ?? null) === '1'
                && ($event->payload['person']['id'] ?? null) === $firstPerson->id;
        });
    }
}
