<?php

namespace Tests\Feature;

use App\Events\TurnstileEntryDetected;
use App\Interfaces\Turnstile\CheckEntryCodeInterface;
use App\Interfaces\Turnstile\ClientIdFromTurnstileInterface;
use App\Models\AttendanceSheet;
use App\Models\EntryCode;
use App\Models\EntryPermission;
use App\Models\Guest;
use App\Models\Gym;
use App\Models\MembershipCategory;
use App\Models\MembershipPlan;
use App\Models\MembershipSale;
use App\Models\Person;
use App\Models\PersonMembership;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Turnstile\TurnstileRepository;
use App\Services\People\PersonVisitService;
use App\Services\People\GuestEntryService;
use App\Services\MembershipSales\MembershipSaleGuestService;
use App\Services\Turnstile\EntryExitSystemService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GuestEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_ees_guest_uses_hosts_visits_once_per_day_even_if_host_entered(): void
    {
        [$gym, $host, $guest, $membership, $code] = $this->fixture(2);
        $membership->update(['guest_left' => 2]);
        AttendanceSheet::query()->create([
            'relation_type' => Person::class,
            'relation_id' => $host->id,
            'gym_id' => $gym->id,
            'date' => '2026-09-28 09:00:00',
            'created_at' => '2026-09-28 09:00:00',
            'direction' => 'entry',
        ])->personMemberships()->sync([$membership->id]);

        $service = $this->eesService($gym, $code, 3);
        $first = $service->ees($this->scan('2026-09-28 10:00:00'));

        $this->assertTrue($first->result['access_allowed']);
        $this->assertSame(1, $membership->fresh()->visits_left);
        $this->assertSame(1, $membership->fresh()->guest_used);
        $this->assertSame(1, $membership->fresh()->guest_left);
        $entry = AttendanceSheet::query()->where('relation_id', $guest->id)->firstOrFail();
        $this->assertSame($membership->id, $entry->personMemberships->firstOrFail()->id);

        $duplicate = $service->ees($this->scan('2026-09-28 11:00:00'));
        $this->assertSame('entry_already_recorded_today', $duplicate->message);
        $this->assertTrue($duplicate->result['access_allowed']);
        $this->assertSame(1, $membership->fresh()->visits_left);
        $this->assertSame(1, $membership->fresh()->guest_left);
        $this->assertSame(1, AttendanceSheet::query()->where('relation_id', $guest->id)->count());

        $nextDay = $service->ees($this->scan('2026-09-29 10:00:00'));
        $this->assertTrue($nextDay->result['access_allowed']);
        $this->assertSame(0, $membership->fresh()->visits_left);
        $this->assertSame(2, $membership->fresh()->guest_used);
        $this->assertSame(0, $membership->fresh()->guest_left);
        $this->assertSame(2, AttendanceSheet::query()->where('relation_id', $guest->id)->count());
    }

    public function test_ees_guest_duplicate_remains_allowed_after_last_visit_was_used(): void
    {
        [$gym, $host, $guest, $membership, $code] = $this->fixture(1);
        $service = $this->eesService($gym, $code, 2);

        $first = $service->ees($this->scan('2026-09-28 10:00:00'));
        $duplicate = $service->ees($this->scan('2026-09-28 11:00:00'));

        $this->assertTrue($first->result['access_allowed']);
        $this->assertSame('entry_already_recorded_today', $duplicate->message);
        $this->assertTrue($duplicate->result['access_allowed']);
        $this->assertSame(0, $membership->fresh()->visits_left);
        $this->assertSame(1, AttendanceSheet::query()->where('relation_id', $guest->id)->count());
    }

    public function test_manual_guest_entry_shows_and_consumes_the_hosts_membership(): void
    {
        [$gym, $host, $guest, $membership] = $this->fixture(2);
        $manager = User::query()->where('gym_id', $gym->id)->firstOrFail();
        $manager->assignRole(Role::query()->create([
            'name' => 'manager', 'guard_name' => 'web', 'g_name' => 'manager',
        ]));
        $this->actingAs($manager);
        $service = app(PersonVisitService::class);

        $page = $service->pageData($guest->id);
        $this->assertContains($membership->id, $page['memberships']->modelKeys());

        $entry = $service->storeManualVisit($guest->id, 'entry', $membership->id, '2026-09-28T10:00');
        $this->assertSame($guest->id, $entry->relation_id);
        $this->assertSame($membership->id, $entry->personMemberships->firstOrFail()->id);
        $this->assertSame(1, $membership->fresh()->visits_left);
        $this->assertSame(1, $membership->fresh()->guest_left);

        $this->expectException(ValidationException::class);
        $service->storeManualVisit($guest->id, 'entry', $membership->id, '2026-09-28T12:00');
    }

    public function test_guest_without_valid_host_membership_is_denied(): void
    {
        [$gym, $host, $guest, $membership, $code] = $this->fixture(2);
        $membership->update(['valid_at' => '2026-09-27']);
        $service = $this->eesService($gym, $code, 1);

        $result = $service->ees($this->scan('2026-09-28 10:00:00'));

        $this->assertFalse($result->result['access_allowed']);
        $this->assertDatabaseCount('attendance_sheets', 0);
        $this->assertSame(2, $membership->fresh()->visits_left);
    }

    public function test_ees_reports_when_the_guest_entry_quota_is_exhausted(): void
    {
        [$gym, $host, $guest, $membership, $code] = $this->fixture(2);
        $membership->update(['guest_left' => 0]);
        $service = $this->eesService($gym, $code, 1);

        $result = $service->ees($this->scan('2026-09-28 10:00:00'));

        $this->assertFalse($result->result['access_allowed']);
        $this->assertSame('guest_entry_limit_reached', $result->result['reason']);
        Event::assertDispatched(TurnstileEntryDetected::class, function (TurnstileEntryDetected $event) use ($guest): bool {
            return ($event->payload['reason'] ?? null) === 'guest_entry_limit_reached'
                && ($event->payload['owner_type'] ?? null) === 'guest'
                && ($event->payload['person']['type'] ?? null) === 'guest'
                && ($event->payload['person']['id'] ?? null) === $guest->id;
        });
    }

    public function test_manager_selects_one_linked_membership_for_guest_with_multiple_options(): void
    {
        [$gym, $host, $guest, $firstMembership, $code] = $this->fixture(2);
        $secondMembership = $firstMembership->replicate(['uuid']);
        $secondMembership->save();
        Guest::query()->create([
            'guest_id' => $guest->id,
            'person_id' => $host->id,
            'person_membership_id' => $secondMembership->id,
        ]);
        $manager = User::query()->where('gym_id', $gym->id)->firstOrFail();
        $manager->assignRole(Role::query()->create([
            'name' => 'manager', 'guard_name' => 'web', 'g_name' => 'manager',
        ]));

        $service = $this->eesService($gym, $code, 1);
        $pending = $service->ees($this->scan('2026-09-28 10:00:00'));
        $this->assertTrue($pending->result['access_allowed']);
        $this->assertDatabaseCount('attendance_sheets', 0);
        Event::assertDispatched(TurnstileEntryDetected::class, function (TurnstileEntryDetected $event) use ($guest): bool {
            return ($event->payload['pending_attendance_selection'] ?? false)
                && ($event->payload['membership_activation_context']['guest_entry'] ?? false)
                && ($event->payload['person']['id'] ?? null) === $guest->id;
        });

        $result = $service->finalizeTurnstileMembershipSelection(
            [$secondMembership->id],
            $manager,
            [
                'action' => 'entry',
                'guest_id' => $guest->id,
                'entry_code' => 'GUEST-CARD',
                'detected_at' => '2026-09-28 10:00:00',
            ],
        );

        $entry = AttendanceSheet::query()->findOrFail($result['attendance_id']);
        $this->assertSame($guest->id, $entry->relation_id);
        $this->assertSame($secondMembership->id, $entry->personMemberships->firstOrFail()->id);
        $this->assertSame(2, $firstMembership->fresh()->visits_left);
        $this->assertSame(1, $secondMembership->fresh()->visits_left);
        $this->assertSame(1, $secondMembership->fresh()->guest_used);
        $this->assertSame(0, $secondMembership->fresh()->guest_left);
    }

    public function test_registering_a_guest_does_not_consume_a_guest_entry(): void
    {
        [$gym, $host, $guest, $membership, $code] = $this->fixture(2);
        $this->actingAs(User::query()->where('gym_id', $gym->id)->firstOrFail());
        $service = app(MembershipSaleGuestService::class);

        $page = $service->guestPageData($membership->membership_sale_id);
        $this->assertSame(1, $page['allowedGuestCount']);
        $this->assertSame(0, $page['usedGuestCount']);
        $this->assertSame(1, $page['remainingGuestCount']);
    }

    public function test_adding_guest_does_not_update_guest_entry_counters(): void
    {
        [$gym, $host, $guest, $membership, $code] = $this->fixture(2);
        $membership->guests()->delete();
        $membership->update(['guest_left' => 0]);
        $this->actingAs(User::query()->where('gym_id', $gym->id)->firstOrFail());
        $service = app(MembershipSaleGuestService::class);

        $service->storeGuest($membership->membership_sale_id, [
            'name' => $guest->name,
            'surname' => $guest->surname,
            'phone' => $guest->phone,
            'entry_code_id' => $code->id,
        ]);

        $this->assertSame(0, $membership->fresh()->guest_used);
        $this->assertSame(0, $membership->fresh()->guest_left);
        $this->assertSame(1, $membership->guests()->count());
    }

    public function test_guest_assignments_are_not_limited_by_remaining_guest_entries(): void
    {
        [$gym, $host, $guest, $membership] = $this->fixture(2);
        $extraGuest = $this->person('Extra', 'extra@example.test', '+37499333333', 'guest', $gym);
        Guest::query()->create([
            'guest_id' => $extraGuest->id,
            'person_id' => $host->id,
            'person_membership_id' => $membership->id,
        ]);
        $entryAt = Carbon::parse('2026-09-28 10:00:00', 'Asia/Yerevan');
        $service = app(GuestEntryService::class);

        $this->assertCount(1, $service->availableMemberships($guest, $gym->id, $entryAt));
        $this->assertCount(1, $service->availableMemberships($extraGuest, $gym->id, $entryAt));
    }

    private function fixture(int $visitsLeft): array
    {
        $gym = Gym::query()->create(['name' => 'Guest gym']);
        $operator = User::query()->create([
            'name' => 'Operator', 'surname' => 'Test', 'email' => 'operator@example.test',
            'password' => bcrypt('password'), 'gym_id' => $gym->id,
        ]);
        $host = $this->person('Host', 'host@example.test', '+37499111111', 'visitor', $gym);
        $guest = $this->person('Guest', 'guest@example.test', '+37499222222', 'guest', $gym);
        $category = MembershipCategory::query()->create([
            'gym_id' => $gym->id, 'slug' => 'gym', 'active' => true,
        ]);
        $plan = MembershipPlan::query()->create([
            'membership_category_id' => $category->id, 'gym_id' => $gym->id,
            'price' => 10000, 'duration_type' => 'month', 'duration_value' => 1,
            'guest_limit' => 1, 'active' => true,
        ]);
        $sale = MembershipSale::query()->create([
            'user_id' => $operator->id, 'person_id' => $host->id, 'gym_id' => $gym->id,
            'membership_plan_id' => $plan->id, 'total_price' => 10000,
            'final_price' => 10000, 'payment_status' => 'paid',
            'sold_at' => '2026-09-25 09:00:00',
        ]);
        $membership = PersonMembership::query()->create([
            'membership_sale_id' => $sale->id, 'user_id' => $operator->id,
            'person_id' => $host->id, 'gym_id' => $gym->id,
            'membership_plan_id' => $plan->id, 'status' => 'active',
            'start_date' => '2026-09-25', 'valid_at' => '2026-10-24',
            'visits_left' => $visitsLeft, 'guest_left' => 1,
        ]);
        Guest::query()->create([
            'guest_id' => $guest->id, 'person_id' => $host->id,
            'person_membership_id' => $membership->id,
        ]);
        $code = EntryCode::query()->create([
            'gym_id' => $gym->id, 'token' => 'GUEST-CARD', 'type' => 'rfId',
            'status' => true, 'activation' => true,
        ]);
        EntryPermission::query()->create([
            'entry_code_id' => $code->id, 'relation_id' => $guest->id,
            'relation_type' => Person::class, 'status' => true,
        ]);

        return [$gym, $host, $guest, $membership, $code];
    }

    private function person(string $name, string $email, string $phone, string $type, Gym $gym): Person
    {
        $person = Person::query()->create([
            'name' => $name, 'surname' => 'Test', 'email' => $email,
            'phone' => $phone, 'password' => bcrypt('password'), 'type' => $type,
        ]);
        $person->gyms()->attach($gym->id);

        return $person;
    }

    private function eesService(Gym $gym, EntryCode $code, int $scans): EntryExitSystemService
    {
        $turnstile = \Mockery::mock(TurnstileRepository::class);
        $turnstile->shouldReceive('getClientId')->times($scans)->andReturn($gym->id);
        $checker = \Mockery::mock(CheckEntryCodeInterface::class);
        $checker->shouldReceive('checkEntryCode')->times($scans)->andReturn((object) ['result' => $code]);
        app()->instance(ClientIdFromTurnstileInterface::class, $turnstile);
        app()->instance(CheckEntryCodeInterface::class, $checker);
        Event::fake([TurnstileEntryDetected::class]);

        return app(EntryExitSystemService::class);
    }

    private function scan(string $dateTime): object
    {
        return (object) [
            'mac' => 'TEST-MAC',
            'entry_code' => 'GUEST-CARD#'.Carbon::parse($dateTime, 'Asia/Yerevan')->timestamp,
            'entry_code_type' => 'standart',
        ];
    }
}
