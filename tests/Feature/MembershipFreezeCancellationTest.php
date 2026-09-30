<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Gym;
use App\Models\MembershipCategory;
use App\Models\MembershipPlan;
use App\Models\MembershipSale;
use App\Models\Person;
use App\Models\PersonMembership;
use App\Models\PersonMembershipFreeze;
use App\Models\Role;
use App\Models\User;
use App\Services\MembershipSales\MembershipFreezeStatusService;
use App\Services\MembershipSales\MembershipSaleFreezeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MembershipFreezeCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_future_freeze_cancellation_rolls_back_all_days_and_restores_the_freeze_limit(): void
    {
        $this->travelTo('2026-09-30 10:00:00');
        [$sale, $membership, $freeze, $nextMembership, $actor] = $this->fixture(
            freezeStart: '2026-10-02',
            freezeEnd: '2026-10-05',
            validAt: '2026-10-26',
            status: 'active',
        );
        $nextDates = [
            $nextMembership->start_date->toDateString(),
            $nextMembership->end_date->toDateString(),
            $nextMembership->valid_at->toDateString(),
        ];
        ActivityLog::query()->delete();

        app(MembershipSaleFreezeService::class)->cancelFreeze($sale->id, $freeze->id);

        $membership->refresh();
        $freeze->refresh();
        $this->assertSame('2026-10-22', $membership->valid_at->toDateString());
        $this->assertSame('active', $membership->status);
        $this->assertSame(1, $membership->freeze_left);
        $this->assertSame(0, $membership->freeze_used);
        $this->assertSame('2026-09-30', $freeze->cancel_effective_date->toDateString());
        $this->assertSame($actor->id, $freeze->cancelled_by);
        $this->assertNotNull($freeze->cancelled_at);
        $nextMembership->refresh();
        $this->assertSame($nextDates, [
            $nextMembership->start_date->toDateString(),
            $nextMembership->end_date->toDateString(),
            $nextMembership->valid_at->toDateString(),
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'membership_sale.freeze_cancelled',
            'loggable_id' => $sale->id,
        ]);
    }

    public function test_active_freeze_cancellation_rolls_back_only_remaining_days_and_activates_membership(): void
    {
        $this->travelTo('2026-09-30 10:00:00');
        [$sale, $membership, $freeze] = $this->fixture(
            freezeStart: '2026-09-28',
            freezeEnd: '2026-10-03',
            validAt: '2026-10-28',
            status: 'frozen',
        );

        app(MembershipSaleFreezeService::class)->cancelFreeze($sale->id, $freeze->id);

        $membership->refresh();
        $this->assertSame('2026-10-24', $membership->valid_at->toDateString());
        $this->assertSame('active', $membership->status);
        $this->assertSame(0, $membership->freeze_left);
        $this->assertSame(1, $membership->freeze_used);

        $statusResult = app(MembershipFreezeStatusService::class)->updateDailyStatuses('2026-09-30');
        $this->assertSame(0, $statusResult['frozen']);
        $this->assertSame('active', $membership->fresh()->status);
    }

    public function test_already_cancelled_freeze_cannot_be_cancelled_twice(): void
    {
        $this->travelTo('2026-09-30 10:00:00');
        [$sale, $membership, $freeze] = $this->fixture(
            freezeStart: '2026-10-02',
            freezeEnd: '2026-10-05',
            validAt: '2026-10-26',
            status: 'active',
        );
        $service = app(MembershipSaleFreezeService::class);
        $service->cancelFreeze($sale->id, $freeze->id);

        try {
            $service->cancelFreeze($sale->id, $freeze->id);
            $this->fail('An already cancelled freeze was cancelled twice.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('freeze', $exception->errors());
        }

        $this->assertSame('2026-10-22', $membership->fresh()->valid_at->toDateString());
    }

    public function test_cancelled_future_freeze_period_can_be_created_again(): void
    {
        $this->travelTo('2026-09-30 10:00:00');
        [$sale, $membership, $freeze] = $this->fixture(
            freezeStart: '2026-10-02',
            freezeEnd: '2026-10-05',
            validAt: '2026-10-26',
            status: 'active',
        );
        $service = app(MembershipSaleFreezeService::class);

        $service->cancelFreeze($sale->id, $freeze->id);
        $service->storeFreeze($sale->id, [
            'start_date' => '2026-10-02',
            'end_date' => '2026-10-05',
            'notes' => null,
        ]);

        $freezes = $membership->freezes()->orderBy('id')->get();
        $this->assertCount(2, $freezes);
        $this->assertNotNull($freezes->first()->cancelled_at);
        $this->assertNull($freezes->last()->cancelled_at);
        $this->assertSame('2026-10-26', $membership->fresh()->valid_at->toDateString());
        $this->assertSame(0, $membership->freeze_left);
        $this->assertSame(1, $membership->freeze_used);
    }

    public function test_freeze_page_exposes_the_cancel_action_and_the_endpoint_cancels_it(): void
    {
        $this->travelTo('2026-09-30 10:00:00');
        [$sale, $membership, $freeze] = $this->fixture(
            freezeStart: '2026-10-02',
            freezeEnd: '2026-10-05',
            validAt: '2026-10-26',
            status: 'active',
        );

        $this->get("/hy/membership-sale/freezes/{$sale->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MembershipSales/Freezes')
                ->where('freezes.0.id', $freeze->id)
                ->where('freezes.0.can_cancel', true));

        $this->post("/hy/membership-sale/freezes/{$sale->id}/{$freeze->id}/cancel")
            ->assertRedirect("/hy/membership-sale/freezes/{$sale->id}");

        $this->assertNotNull($freeze->fresh()->cancelled_at);
        $this->assertSame('2026-10-22', $membership->fresh()->valid_at->toDateString());
    }

    private function fixture(
        string $freezeStart,
        string $freezeEnd,
        string $validAt,
        string $status,
    ): array {
        $gym = Gym::query()->create(['name' => 'Freeze Gym']);
        $role = Role::query()->create([
            'name' => 'owner',
            'guard_name' => 'web',
            'g_name' => 'owner',
        ]);
        $actor = User::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Freeze',
            'surname' => 'Admin',
            'email' => Str::uuid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $actor->assignRole($role);
        $this->actingAs($actor);
        $person = Person::query()->create([
            'name' => 'Freeze Customer',
            'email' => Str::uuid().'@example.com',
            'phone' => '+374'.random_int(10000000, 99999999),
            'password' => Hash::make('password'),
        ]);
        $category = MembershipCategory::query()->create([
            'gym_id' => $gym->id,
            'slug' => Str::uuid()->toString(),
        ]);
        $plan = MembershipPlan::query()->create([
            'membership_category_id' => $category->id,
            'gym_id' => $gym->id,
            'price' => 10000,
            'duration_type' => 'month',
            'duration_value' => 1,
            'freeze_limit' => 1,
            'active' => true,
        ]);
        $sale = MembershipSale::query()->create([
            'user_id' => $actor->id,
            'person_id' => $person->id,
            'gym_id' => $gym->id,
            'membership_plan_id' => $plan->id,
            'total_price' => 10000,
            'final_price' => 10000,
            'payment_status' => 'paid',
            'sold_at' => '2026-09-01 09:00:00',
        ]);
        $membership = PersonMembership::query()->create([
            'membership_sale_id' => $sale->id,
            'user_id' => $actor->id,
            'person_id' => $person->id,
            'gym_id' => $gym->id,
            'membership_plan_id' => $plan->id,
            'status' => $status,
            'start_date' => '2026-09-01',
            'end_date' => '2026-10-22',
            'valid_at' => $validAt,
            'freeze_left' => 0,
            'freeze_used' => 1,
        ]);
        $nextMembership = PersonMembership::query()->create([
            'membership_sale_id' => $sale->id,
            'user_id' => $actor->id,
            'person_id' => $person->id,
            'gym_id' => $gym->id,
            'membership_plan_id' => $plan->id,
            'status' => 'waiting',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
            'valid_at' => '2026-11-30',
        ]);
        $membership->update(['next_membership_id' => $nextMembership->id]);
        $freeze = PersonMembershipFreeze::query()->create([
            'person_membership_id' => $membership->id,
            'start_date' => $freezeStart,
            'end_date' => $freezeEnd,
        ]);

        return [$sale, $membership, $freeze, $nextMembership, $actor];
    }
}
