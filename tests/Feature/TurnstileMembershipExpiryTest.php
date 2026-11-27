<?php

namespace Tests\Feature;

use App\Events\TurnstileEntryDetected;
use App\Interfaces\AttendanceSheets\AttendanceSheetInterface;
use App\Interfaces\Turnstile\CheckEntryCodeInterface;
use App\Models\AttendanceSheet;
use App\Models\EntryCode;
use App\Models\EntryPermission;
use App\Models\Gym;
use App\Models\GymSetting;
use App\Models\MembershipCategory;
use App\Models\MembershipPlan;
use App\Models\MembershipSale;
use App\Models\Person;
use App\Models\PersonMembership;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Turnstile\TurnstileRepository;
use App\Services\Turnstile\EntryExitSystemService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TurnstileMembershipExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_current_memberships_allow_entry_while_expired_one_is_ignored(): void
    {
        [$service, $memberships, $timestamp] = $this->makeScan(['2026-09-27', '2026-10-24', '2027-09-24']);

        $response = $service->ees((object) [
            'mac' => 'TEST-MAC',
            'entry_code' => 'CARD-1#'.$timestamp,
            'entry_code_type' => 'standart',
        ]);

        $this->assertTrue($response->result['access_allowed']);
        $this->assertDatabaseCount('attendance_sheets', 1);
        $this->assertEqualsCanonicalizing(
            $memberships->slice(1)->pluck('id')->all(),
            AttendanceSheet::query()->firstOrFail()->personMemberships->modelKeys(),
        );
        $this->assertSame(5, $memberships[0]->fresh()->visits_left);
        $this->assertSame(4, $memberships[1]->fresh()->visits_left);
        $this->assertSame(4, $memberships[2]->fresh()->visits_left);
    }

    public function test_first_entry_sets_the_start_and_expiry_dates_once(): void
    {
        [$service, $memberships, $timestamp] = $this->makeScan(['2027-09-24']);
        $membership = $memberships[0];
        $membership->update([
            'status' => 'waiting',
            'start_date' => null,
            'end_date' => null,
            'valid_at' => null,
            'activation_start_mode' => GymSetting::FIRST_ENTRY,
        ]);

        $response = $service->ees((object) [
            'mac' => 'TEST-MAC',
            'entry_code' => 'CARD-1#'.$timestamp,
            'entry_code_type' => 'standart',
        ]);

        $this->assertTrue($response->result['access_allowed']);
        $membership->refresh();
        $this->assertSame('active', $membership->status);
        $this->assertSame('2026-09-28', $membership->start_date->toDateString());
        $this->assertSame('2026-10-27', $membership->end_date->toDateString());
        $this->assertSame('2026-10-27', $membership->valid_at->toDateString());
    }

    public function test_existing_membership_keeps_sold_dates_after_gym_mode_changes(): void
    {
        [$service, $memberships, $timestamp] = $this->makeScan(['2027-09-24']);
        $membership = $memberships[0];
        $membership->update(['status' => 'waiting', 'end_date' => '2027-09-24']);
        GymSetting::query()->create([
            'gym_id' => $membership->gym_id,
            'membership_selection_mode' => GymSetting::AUTO_UP_TO_TWO,
            'membership_start_mode' => GymSetting::FIRST_ENTRY,
        ]);

        $service->ees((object) [
            'mac' => 'TEST-MAC',
            'entry_code' => 'CARD-1#'.$timestamp,
            'entry_code_type' => 'standart',
        ]);

        $membership->refresh();
        $this->assertSame('2026-09-25', $membership->start_date->toDateString());
        $this->assertSame('2027-09-24', $membership->end_date->toDateString());
        $this->assertSame('2027-09-24', $membership->valid_at->toDateString());
    }

    public function test_manager_context_excludes_expired_membership(): void
    {
        [$service, $memberships, $timestamp] = $this->makeScan([
            '2026-09-27', '2026-10-24', '2027-09-24', '2027-10-24',
        ]);

        $response = $service->ees((object) [
            'mac' => 'TEST-MAC',
            'entry_code' => 'CARD-1#'.$timestamp,
            'entry_code_type' => 'standart',
        ]);

        $this->assertTrue($response->result['access_allowed']);
        $this->assertDatabaseCount('attendance_sheets', 0);
        Event::assertDispatched(TurnstileEntryDetected::class, function (TurnstileEntryDetected $event) use ($memberships): bool {
            $context = $event->payload['membership_activation_context'] ?? [];
            $ids = array_column($context['selectable_memberships'] ?? [], 'id');

            return ($event->payload['pending_attendance_selection'] ?? false)
                && count($ids) === 3
                && !in_array($memberships[0]->id, $ids, true)
                && count(array_intersect($memberships->slice(1)->pluck('id')->all(), $ids)) === 3;
        });
    }

    public function test_manager_selection_mode_waits_for_manager_with_two_valid_memberships(): void
    {
        [$service, $memberships, $timestamp] = $this->makeScan(['2027-09-24', '2027-10-24']);
        GymSetting::query()->create([
            'gym_id' => $memberships[0]->gym_id,
            'membership_selection_mode' => GymSetting::MANAGER_SELECT_MULTIPLE,
        ]);

        $response = $service->ees((object) [
            'mac' => 'TEST-MAC',
            'entry_code' => 'CARD-1#'.$timestamp,
            'entry_code_type' => 'standart',
        ]);

        $this->assertTrue($response->result['access_allowed']);
        $this->assertDatabaseCount('attendance_sheets', 0);
        Event::assertDispatched(TurnstileEntryDetected::class, fn (TurnstileEntryDetected $event): bool =>
            ($event->payload['pending_attendance_selection'] ?? false)
            && count($event->payload['membership_activation_context']['selectable_memberships'] ?? []) === 2
        );
        $this->assertSame(5, $memberships[0]->fresh()->visits_left);
        $this->assertSame(5, $memberships[1]->fresh()->visits_left);
    }

    public function test_manager_selection_mode_still_records_one_membership_automatically(): void
    {
        [$service, $memberships, $timestamp] = $this->makeScan(['2027-09-24']);
        GymSetting::query()->create([
            'gym_id' => $memberships[0]->gym_id,
            'membership_selection_mode' => GymSetting::MANAGER_SELECT_MULTIPLE,
        ]);

        $response = $service->ees((object) [
            'mac' => 'TEST-MAC',
            'entry_code' => 'CARD-1#'.$timestamp,
            'entry_code_type' => 'standart',
        ]);

        $this->assertTrue($response->result['access_allowed']);
        $this->assertDatabaseCount('attendance_sheets', 1);
        $this->assertSame([$memberships[0]->id], AttendanceSheet::query()->firstOrFail()->personMemberships->modelKeys());
        $this->assertSame(4, $memberships[0]->fresh()->visits_left);
    }

    public function test_manual_rfid_scan_uses_the_same_manager_selection_threshold(): void
    {
        [$service, $memberships, $timestamp, $manager] = $this->makeScan(['2027-09-24', '2027-10-24'], true);
        $manager->assignRole(Role::query()->firstOrCreate([
            'name' => 'manager', 'guard_name' => 'web', 'g_name' => 'manager',
        ]));
        GymSetting::query()->create([
            'gym_id' => $memberships[0]->gym_id,
            'membership_selection_mode' => GymSetting::MANAGER_SELECT_MULTIPLE,
        ]);

        $result = $service->manualScan($manager, 'CARD-1#'.$timestamp, 'enter');

        $this->assertTrue($result->result['access_allowed']);
        $this->assertDatabaseCount('attendance_sheets', 0);
        Event::assertDispatched(TurnstileEntryDetected::class, fn (TurnstileEntryDetected $event): bool =>
            ($event->payload['pending_attendance_selection'] ?? false)
            && count($event->payload['membership_activation_context']['selectable_memberships'] ?? []) === 2
        );
    }

    private function makeScan(array $validUntilDates, bool $manual = false): array
    {
        $gym = Gym::query()->create(['name' => 'Test gym']);
        $user = User::query()->create([
            'name' => 'Operator',
            'surname' => 'One',
            'email' => 'operator@example.test',
            'password' => bcrypt('password'),
            'gym_id' => $gym->id,
        ]);
        $person = Person::query()->create([
            'name' => 'Artur',
            'surname' => 'Test',
            'email' => 'artur@example.test',
            'password' => bcrypt('password'),
            'phone' => '+37499123456',
            'type' => 'visitor',
        ]);
        $person->gyms()->attach($gym->id);
        $category = MembershipCategory::query()->create([
            'gym_id' => $gym->id,
            'slug' => 'pool',
            'active' => true,
        ]);
        $plan = MembershipPlan::query()->create([
            'membership_category_id' => $category->id,
            'gym_id' => $gym->id,
            'price' => 10000,
            'duration_type' => 'month',
            'duration_value' => 1,
            'active' => true,
        ]);
        $sale = MembershipSale::query()->create([
            'user_id' => $user->id,
            'person_id' => $person->id,
            'gym_id' => $gym->id,
            'membership_plan_id' => $plan->id,
            'total_price' => 10000,
            'final_price' => 10000,
            'payment_status' => 'paid',
            'sold_at' => '2026-09-25 09:00:00',
        ]);
        $memberships = collect($validUntilDates)->map(fn (string $validUntil) => PersonMembership::query()->create([
            'membership_sale_id' => $sale->id,
            'user_id' => $user->id,
            'person_id' => $person->id,
            'gym_id' => $gym->id,
            'membership_plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => '2026-09-25',
            'valid_at' => $validUntil,
            'visits_left' => 5,
        ]));
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
        $turnstile = \Mockery::mock(TurnstileRepository::class);
        if (! $manual) {
            $turnstile->shouldReceive('getClientId')->once()->andReturn($gym->id);
        }
        $checker = \Mockery::mock(CheckEntryCodeInterface::class);
        $checker->shouldReceive('checkEntryCode')->once()->andReturn((object) ['result' => $entryCode]);
        Event::fake([TurnstileEntryDetected::class]);

        return [
            new EntryExitSystemService(
                $turnstile,
                $checker,
                app(AttendanceSheetInterface::class),
            ),
            $memberships,
            Carbon::create(2026, 9, 28, 10, 0, 0, 'Asia/Yerevan')->timestamp,
            $user,
        ];
    }
}
