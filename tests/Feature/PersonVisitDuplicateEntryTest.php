<?php

namespace Tests\Feature;

use App\Models\AttendanceSheet;
use App\Models\EntryCode;
use App\Models\EntryPermission;
use App\Models\Gym;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Services\People\PersonVisitService;
use App\Services\Turnstile\EntryExitSystemService;
use App\Events\TurnstileEntryDetected;
use App\Interfaces\AttendanceSheets\AttendanceSheetInterface;
use App\Interfaces\Turnstile\CheckEntryCodeInterface;
use App\Interfaces\Turnstile\ClientIdFromTurnstileInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PersonVisitDuplicateEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_visit_validates_membership_before_duplicate_entry(): void
    {
        $operator = User::query()->create([
            'name' => 'Owner',
            'surname' => 'User',
            'email' => 'owner@example.com',
            'password' => Hash::make('password'),
        ]);
        $operator->assignRole(Role::query()->create([
            'name' => 'owner',
            'guard_name' => 'web',
            'g_name' => 'owner',
        ]));

        $person = Person::query()->create([
            'name' => 'Client',
            'surname' => 'User',
            'email' => 'client@example.com',
            'password' => Hash::make('password'),
            'phone' => '+37499123456',
            'type' => 'visitor',
        ]);

        AttendanceSheet::query()->create([
            'relation_id' => $person->id,
            'relation_type' => Person::class,
            'date' => '2026-09-25 09:00:00',
            'direction' => 'entry',
            'type' => 'manual',
        ]);

        $this->actingAs($operator);

        try {
            app(PersonVisitService::class)->storeManualVisit(
                $person->id,
                'entry',
                null,
                '2026-09-25T17:00',
            );
            $this->fail('The duplicate entry should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Membership selection is required for manual entry.',
                $exception->errors()['membership_id'][0],
            );
        }

        $this->assertDatabaseCount('attendance_sheets', 1);
    }

    public function test_turnstile_api_validates_membership_before_duplicate_entry(): void
    {
        $gym = Gym::query()->create(['name' => 'Test gym']);
        $person = Person::query()->create([
            'name' => 'Client',
            'surname' => 'User',
            'email' => 'client@example.com',
            'password' => Hash::make('password'),
            'phone' => '+37499123456',
            'type' => 'visitor',
        ]);
        $person->gyms()->attach($gym->id);

        $entryCode = EntryCode::query()->create([
            'gym_id' => $gym->id,
            'token' => 'CARD-1',
            'type' => 'rfId',
            'status' => true,
            'activation' => true,
        ]);
        EntryPermission::query()->create([
            'entry_code_id' => $entryCode->id,
            'relation_id' => $person->id,
            'relation_type' => Person::class,
            'status' => true,
        ]);
        AttendanceSheet::query()->create([
            'relation_id' => $person->id,
            'relation_type' => Person::class,
            'gym_id' => $gym->id,
            'date' => '2026-09-25 09:00:00',
            'direction' => 'entry',
            'type' => 'rfId',
        ]);

        $turnstile = \Mockery::mock(ClientIdFromTurnstileInterface::class);
        $turnstile->shouldReceive('getClientId')->once()->andReturn($gym->id);
        $entryCodeChecker = \Mockery::mock(CheckEntryCodeInterface::class);
        $entryCodeChecker->shouldReceive('checkEntryCode')->once()->andReturn((object) [
            'result' => $entryCode,
        ]);

        Event::fake();

        $result = (new EntryExitSystemService(
            $turnstile,
            $entryCodeChecker,
            app(AttendanceSheetInterface::class),
        ))->ees((object) [
            'mac' => [],
            'entry_code' => 'CARD-1',
            'entry_code_type' => 'standart',
        ]);

        $this->assertFalse($result->result['access_allowed']);
        $this->assertSame('subscription_expired', $result->result['reason']);
        $this->assertDatabaseCount('attendance_sheets', 1);
        Event::assertDispatched(TurnstileEntryDetected::class, function (TurnstileEntryDetected $event) use ($person): bool {
            return $event->payload['reason'] === 'subscription_expired'
                && $event->payload['person']['id'] === $person->id
                && !isset($event->payload['membership_activation_context']);
        });
    }
}
