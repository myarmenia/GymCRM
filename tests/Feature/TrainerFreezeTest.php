<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\MembershipCategory;
use App\Models\MembershipPlan;
use App\Models\MembershipSale;
use App\Models\Person;
use App\Models\PersonMembership;
use App\Models\PersonMembershipFreeze;
use App\Models\Role;
use App\Models\TrainerCommission;
use App\Models\User;
use App\Services\MembershipSales\MembershipFreezeStatusService;
use App\Services\MembershipSales\MembershipSaleService;
use App\Services\Trainer\TrainerFreezeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrainerFreezeTest extends TestCase
{
    use RefreshDatabase;

    public function test_trainer_freeze_and_customer_freeze_keep_status_and_days_consistent(): void
    {
        $this->travelTo('2026-10-28 10:00:00');
        [$oldTrainer, , $membership] = $this->fixture();
        PersonMembershipFreeze::query()->create([
            'person_membership_id' => $membership->id,
            'start_date' => '2026-10-30',
            'end_date' => '2026-11-02',
        ]);
        $membership->update(['valid_at' => '2026-12-04']); // The four customer freeze days are already counted.

        app(TrainerFreezeService::class)->freeze($oldTrainer->id, [
            'start_date' => '2026-10-28',
            'end_date' => '2026-11-06',
        ]);

        $this->assertSame('frozen', $oldTrainer->fresh()->trainer_status);
        $this->assertSame('trainer_frozen', $membership->fresh()->status);
        $this->assertSame('2026-12-10', $membership->fresh()->valid_at->toDateString());

        $statuses = app(MembershipFreezeStatusService::class);
        $statuses->updateDailyStatuses('2026-10-31');
        $this->assertSame('frozen', $membership->fresh()->status);
        $statuses->updateDailyStatuses('2026-11-03');
        $this->assertSame('trainer_frozen', $membership->fresh()->status);
        $statuses->updateDailyStatuses('2026-11-07');
        $this->assertSame('active', $membership->fresh()->status);
        $this->assertSame('active', $oldTrainer->fresh()->trainer_status);
    }

    public function test_changing_trainer_returns_the_unspent_days_and_reactivates_membership(): void
    {
        $this->travelTo('2026-10-28 10:00:00');
        [$oldTrainer, $newTrainer, $membership, $sale] = $this->fixture();
        app(TrainerFreezeService::class)->freeze($oldTrainer->id, [
            'start_date' => '2026-10-28',
            'end_date' => '2026-11-06',
        ]);

        $this->travelTo('2026-10-31 10:00:00');
        app(MembershipSaleService::class)->changeTrainer($sale->id, ['trainer_id' => $newTrainer->id]);

        $membership->refresh();
        $this->assertSame($newTrainer->id, $membership->trainer_id);
        $this->assertSame('active', $membership->status);
        $this->assertSame('2026-12-03', $membership->valid_at->toDateString());
        $this->assertSame('2026-10-31', $membership->trainerMembershipFreezes()->sole()->cancel_effective_date->toDateString());
    }

    public function test_changing_trainer_does_not_cancel_the_customers_own_freeze(): void
    {
        $this->travelTo('2026-10-28 10:00:00');
        [$oldTrainer, $newTrainer, $membership, $sale] = $this->fixture();
        PersonMembershipFreeze::query()->create([
            'person_membership_id' => $membership->id,
            'start_date' => '2026-10-30',
            'end_date' => '2026-11-02',
        ]);
        $membership->update(['valid_at' => '2026-12-04']);
        app(TrainerFreezeService::class)->freeze($oldTrainer->id, [
            'start_date' => '2026-10-28',
            'end_date' => '2026-11-06',
        ]);

        $this->travelTo('2026-10-31 10:00:00');
        app(MembershipSaleService::class)->changeTrainer($sale->id, ['trainer_id' => $newTrainer->id]);

        $this->assertSame('frozen', $membership->fresh()->status);
        $this->assertSame('2026-12-06', $membership->fresh()->valid_at->toDateString());
        app(MembershipFreezeStatusService::class)->updateDailyStatuses('2026-11-03');
        $this->assertSame('active', $membership->fresh()->status);
    }

    public function test_future_trainer_freeze_changes_status_only_on_its_start_date(): void
    {
        $this->travelTo('2026-10-28 10:00:00');
        [$trainer, , $membership] = $this->fixture();
        app(TrainerFreezeService::class)->freeze($trainer->id, [
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-10',
        ]);

        $this->assertSame('active', $trainer->fresh()->trainer_status);
        $this->assertSame('active', $membership->fresh()->status);
        $this->assertSame('2026-12-10', $membership->fresh()->valid_at->toDateString());
        app(MembershipFreezeStatusService::class)->updateDailyStatuses('2026-11-01');
        $this->assertSame('frozen', $trainer->fresh()->trainer_status);
        $this->assertSame('trainer_frozen', $membership->fresh()->status);
    }

    public function test_renewal_dates_follow_the_trainer_freeze_and_early_switch(): void
    {
        $this->travelTo('2026-10-28 10:00:00');
        [$oldTrainer, $newTrainer, $membership, $sale] = $this->fixture();
        $next = PersonMembership::query()->create([
            'membership_sale_id' => $sale->id, 'user_id' => $membership->user_id,
            'person_id' => $membership->person_id, 'gym_id' => $membership->gym_id,
            'membership_plan_id' => $membership->membership_plan_id,
            'status' => 'waiting', 'start_date' => '2026-12-01',
            'end_date' => '2026-12-31', 'valid_at' => '2026-12-31',
        ]);
        $membership->update(['next_membership_id' => $next->id]);
        app(TrainerFreezeService::class)->freeze($oldTrainer->id, [
            'start_date' => '2026-10-28', 'end_date' => '2026-11-06',
        ]);

        $this->assertSame('2026-12-10', $next->fresh()->start_date->toDateString());
        $this->travelTo('2026-10-31 10:00:00');
        app(MembershipSaleService::class)->changeTrainer($sale->id, ['trainer_id' => $newTrainer->id]);
        $this->assertSame('2026-12-03', $next->fresh()->start_date->toDateString());
    }

    public function test_sale_with_frozen_trainer_counts_only_remaining_days_but_cannot_switch_to_another_frozen_trainer(): void
    {
        $this->travelTo('2026-10-28 10:00:00');
        [$trainer, $otherTrainer, $existingMembership] = $this->fixture();
        app(TrainerFreezeService::class)->freeze($trainer->id, [
            'start_date' => '2026-10-28', 'end_date' => '2026-11-06',
        ]);

        $this->travelTo('2026-10-31 10:00:00');
        $membership = $this->sellToNewPerson($existingMembership, $trainer, '2026-10-31');

        $this->assertSame('trainer_frozen', $membership->status);
        $this->assertSame('2026-12-06', $membership->valid_at->toDateString());
        $this->assertDatabaseHas('trainer_membership_freezes', [
            'person_membership_id' => $membership->id,
            'start_date' => '2026-10-31', 'end_date' => '2026-11-06',
        ]);

        app(TrainerFreezeService::class)->freeze($otherTrainer->id, [
            'start_date' => '2026-10-31', 'end_date' => '2026-11-05',
        ]);
        $this->expectException(ModelNotFoundException::class);
        app(MembershipSaleService::class)->changeTrainer($membership->membership_sale_id, [
            'trainer_id' => $otherTrainer->id,
        ]);
    }

    public function test_sale_after_scheduled_trainer_freeze_is_created_already_accounts_for_its_days(): void
    {
        $this->travelTo('2026-10-28 10:00:00');
        [$trainer, , $existingMembership] = $this->fixture();
        app(TrainerFreezeService::class)->freeze($trainer->id, [
            'start_date' => '2026-11-01', 'end_date' => '2026-11-10',
        ]);

        $membership = $this->sellToNewPerson($existingMembership, $trainer, '2026-11-01');

        $this->assertSame('waiting', $membership->status);
        $this->assertSame('2026-12-10', $membership->valid_at->toDateString());
        app(MembershipFreezeStatusService::class)->updateDailyStatuses('2026-11-01');
        $this->assertSame('trainer_frozen', $membership->fresh()->status);
    }

    public function test_cancelling_current_trainer_freeze_returns_unused_days_and_restores_status(): void
    {
        $this->travelTo('2026-10-28 10:00:00');
        [$trainer, , $membership] = $this->fixture();
        $freeze = app(TrainerFreezeService::class)->freeze($trainer->id, [
            'start_date' => '2026-10-28', 'end_date' => '2026-11-06',
        ]);

        $this->travelTo('2026-10-31 10:00:00');
        app(TrainerFreezeService::class)->cancel($trainer->id, $freeze->id);

        $this->assertSame('active', $trainer->fresh()->trainer_status);
        $this->assertSame('active', $membership->fresh()->status);
        $this->assertSame('2026-12-03', $membership->fresh()->valid_at->toDateString());
        $this->assertSame('2026-10-31', $freeze->fresh()->cancel_effective_date->toDateString());
        $this->assertSame('2026-10-31', $membership->trainerMembershipFreezes()->sole()->cancel_effective_date->toDateString());

        app(MembershipFreezeStatusService::class)->updateDailyStatuses('2026-11-01');
        $this->assertSame('active', $trainer->fresh()->trainer_status);
        $this->assertSame('active', $membership->fresh()->status);
    }

    public function test_cancelling_future_trainer_freeze_returns_all_days_and_never_activates_it(): void
    {
        $this->travelTo('2026-10-28 10:00:00');
        [$trainer, , $membership] = $this->fixture();
        $freeze = app(TrainerFreezeService::class)->freeze($trainer->id, [
            'start_date' => '2026-11-01', 'end_date' => '2026-11-10',
        ]);

        app(TrainerFreezeService::class)->cancel($trainer->id, $freeze->id);

        $this->assertSame('active', $trainer->fresh()->trainer_status);
        $this->assertSame('2026-11-30', $membership->fresh()->valid_at->toDateString());
        $this->assertSame('active', $membership->fresh()->status);
        app(MembershipFreezeStatusService::class)->updateDailyStatuses('2026-11-01');
        $this->assertSame('active', $trainer->fresh()->trainer_status);
        $this->assertSame('active', $membership->fresh()->status);
    }

    public function test_cancelling_trainer_freeze_preserves_the_customers_own_freeze(): void
    {
        $this->travelTo('2026-10-28 10:00:00');
        [$trainer, , $membership] = $this->fixture();
        PersonMembershipFreeze::query()->create([
            'person_membership_id' => $membership->id,
            'start_date' => '2026-10-30', 'end_date' => '2026-11-02',
        ]);
        $membership->update(['valid_at' => '2026-12-04']);
        $freeze = app(TrainerFreezeService::class)->freeze($trainer->id, [
            'start_date' => '2026-10-28', 'end_date' => '2026-11-06',
        ]);

        $this->travelTo('2026-10-31 10:00:00');
        app(TrainerFreezeService::class)->cancel($trainer->id, $freeze->id);

        $this->assertSame('frozen', $membership->fresh()->status);
        $this->assertSame('2026-12-06', $membership->fresh()->valid_at->toDateString());
        $this->assertSame('active', $trainer->fresh()->trainer_status);
    }

    public function test_cancelling_trainer_freeze_restores_renewal_dates_without_refunding_switched_memberships_twice(): void
    {
        $this->travelTo('2026-10-28 10:00:00');
        [$trainer, $newTrainer, $membership, $sale] = $this->fixture();
        $next = PersonMembership::query()->create([
            'membership_sale_id' => $sale->id, 'user_id' => $membership->user_id,
            'person_id' => $membership->person_id, 'gym_id' => $membership->gym_id,
            'membership_plan_id' => $membership->membership_plan_id,
            'status' => 'waiting', 'start_date' => '2026-12-01',
            'end_date' => '2026-12-31', 'valid_at' => '2026-12-31',
        ]);
        $membership->update(['next_membership_id' => $next->id]);
        $freeze = app(TrainerFreezeService::class)->freeze($trainer->id, [
            'start_date' => '2026-10-28', 'end_date' => '2026-11-06',
        ]);
        $switched = $this->sellToNewPerson($membership, $trainer, '2026-10-28');

        $this->travelTo('2026-10-31 10:00:00');
        app(MembershipSaleService::class)->changeTrainer($switched->membership_sale_id, [
            'trainer_id' => $newTrainer->id,
        ]);
        $switchedValidAt = $switched->fresh()->valid_at->toDateString();
        app(TrainerFreezeService::class)->cancel($trainer->id, $freeze->id);
        $this->assertSame('2026-12-03', $next->fresh()->start_date->toDateString());
        $this->assertSame($switchedValidAt, $switched->fresh()->valid_at->toDateString());
    }

    private function sellToNewPerson(PersonMembership $existingMembership, User $trainer, string $startDate): PersonMembership
    {
        $owner = auth()->user();
        $owner->assignRole(Role::query()->firstOrCreate(
            ['name' => 'sales_manager', 'guard_name' => 'web'], ['g_name' => 'sales_manager'],
        ));
        MembershipPlan::query()->findOrFail($existingMembership->membership_plan_id)->update(['price' => 0]);
        $person = Person::query()->create([
            'name' => 'New Customer', 'email' => Str::uuid().'@example.test',
            'phone' => '+374'.random_int(10000000, 99999999), 'password' => Hash::make('password'),
        ]);

        $sale = app(MembershipSaleService::class)->store([
            'person_id' => $person->id,
            'sales_manager_id' => $owner->id,
            'membership_plan_id' => $existingMembership->membership_plan_id,
            'trainer_id' => $trainer->id,
            'start_date' => $startDate,
            'is_hdm' => false,
            'is_full_payment' => true,
        ]);

        return $sale->personMemberships()->sole();
    }

    private function fixture(): array
    {
        $gym = Gym::query()->create(['name' => 'Trainer Freeze Gym']);
        $ownerRole = Role::query()->create(['name' => 'owner', 'guard_name' => 'web', 'g_name' => 'owner']);
        $trainerRole = Role::query()->create(['name' => 'trainer', 'guard_name' => 'web', 'g_name' => 'trainer']);
        $trainerRole->id = 7;
        $trainerRole->save();
        $owner = User::query()->create([
            'gym_id' => $gym->id, 'name' => 'Owner', 'surname' => 'One', 'email' => Str::uuid().'@example.test',
            'password' => Hash::make('password'),
        ]);
        $owner->assignRole($ownerRole);
        $this->actingAs($owner);

        $oldTrainer = User::query()->create([
            'gym_id' => $gym->id, 'name' => 'Old', 'surname' => 'Trainer', 'email' => Str::uuid().'@example.test',
            'password' => Hash::make('password'),
        ]);
        $newTrainer = User::query()->create([
            'gym_id' => $gym->id, 'name' => 'New', 'surname' => 'Trainer', 'email' => Str::uuid().'@example.test',
            'password' => Hash::make('password'),
        ]);
        $oldTrainer->assignRole($trainerRole);
        $newTrainer->assignRole($trainerRole);
        $person = Person::query()->create([
            'name' => 'Customer', 'email' => Str::uuid().'@example.test',
            'phone' => '+374'.random_int(10000000, 99999999), 'password' => Hash::make('password'),
        ]);
        $category = MembershipCategory::query()->create(['gym_id' => $gym->id, 'slug' => Str::uuid()->toString()]);
        $plan = MembershipPlan::query()->create([
            'membership_category_id' => $category->id, 'gym_id' => $gym->id,
            'price' => 10000, 'duration_type' => 'month', 'duration_value' => 1, 'active' => true,
        ]);
        $plan->trainers()->attach([
            $oldTrainer->id => ['price_type' => 'fixed', 'price_value' => 1000, 'total_price' => 1000],
            $newTrainer->id => ['price_type' => 'fixed', 'price_value' => 1000, 'total_price' => 1000],
        ]);
        $sale = MembershipSale::query()->create([
            'user_id' => $owner->id, 'person_id' => $person->id, 'gym_id' => $gym->id,
            'membership_plan_id' => $plan->id, 'total_price' => 10000, 'final_price' => 10000,
            'payment_status' => 'paid', 'sold_at' => '2026-10-28 09:00:00',
        ]);
        $membership = PersonMembership::query()->create([
            'membership_sale_id' => $sale->id, 'user_id' => $owner->id, 'person_id' => $person->id,
            'gym_id' => $gym->id, 'membership_plan_id' => $plan->id, 'trainer_id' => $oldTrainer->id,
            'status' => 'active', 'start_date' => '2026-10-01', 'end_date' => '2026-11-30',
            'valid_at' => '2026-11-30',
        ]);
        TrainerCommission::query()->create([
            'trainer_id' => $oldTrainer->id, 'membership_sale_id' => $sale->id,
            'person_membership_id' => $membership->id, 'salary_type' => 'fixed',
            'salary_value' => 1000, 'salary_amount' => 1000, 'initial_salary_amount' => 1000,
            'salary_start_installment' => 1, 'salary_installment_count' => 1,
            'status' => 'pending',
        ]);

        return [$oldTrainer, $newTrainer, $membership, $sale];
    }
}
