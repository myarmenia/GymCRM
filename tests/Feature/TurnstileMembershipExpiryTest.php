<?php

namespace Tests\Feature;

use App\Events\TurnstileEntryDetected;
use App\Interfaces\AttendanceSheets\AttendanceSheetInterface;
use App\Interfaces\Turnstile\CheckEntryCodeInterface;
use App\Models\AttendanceSheet;
use App\Models\EntryCode;
use App\Models\EntryPermission;
use App\Models\Gym;
use App\Models\MembershipCategory;
use App\Models\MembershipPlan;
use App\Models\MembershipSale;
use App\Models\Person;
use App\Models\PersonMembership;
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

    private function makeScan(array $validUntilDates): array
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
        $turnstile->shouldReceive('getClientId')->once()->andReturn($gym->id);
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
        ];
    }
}
