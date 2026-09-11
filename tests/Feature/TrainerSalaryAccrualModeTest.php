<?php

namespace Tests\Feature;

use App\Models\AttendanceSheet;
use App\Models\Gym;
use App\Models\MembershipCategory;
use App\Models\MembershipPlan;
use App\Models\MembershipSale;
use App\Models\Person;
use App\Models\PersonMembership;
use App\Models\PersonMembershipFreeze;
use App\Models\TrainerCommission;
use App\Models\User;
use App\Services\MembershipSales\MembershipSaleService;
use App\Services\Reports\TrainerCommissionsReportService;
use App\Services\TrainerMonthlySalaries\TrainerMonthlySalaryService;
use Database\Seeders\GymSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TrainerSalaryAccrualModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_gym_seed_uses_prepaid_salary_mode(): void
    {
        $this->seed(GymSeeder::class);

        $this->assertDatabaseHas('gyms', [
            'name' => 'Default Gym',
            'trainer_salary_mode' => Gym::TRAINER_SALARY_MODE_PREPAID,
        ]);
    }

    public function test_prepaid_salary_waits_for_first_attendance_and_one_entry_activates_the_full_cycle_amount(): void
    {
        [$commission, $membership] = $this->salaryFixture(
            'prepaid',
            '2026-01-01',
            '2026-01-31',
            10000,
            1,
        );
        $service = app(TrainerMonthlySalaryService::class);

        $this->assertNull($service->generateForCommission($commission, '2026-01-01'));
        $this->assertDatabaseCount('trainer_monthly_salaries', 0);
        $this->assertDatabaseCount('salary_payable_assignments', 0);

        $this->attendance($membership, '2026-01-20 10:00:00');
        $salary = $service->generateForCommission($commission->fresh(), '2026-01-20');

        $this->assertSame('pending', $salary?->status);
        $this->assertSame(10000.0, (float) $salary?->price);
        $this->assertDatabaseHas('salary_payable_assignments', [
            'trainer_monthly_salary_id' => $salary?->id,
            'available_amount' => 10000,
        ]);
    }

    public function test_prepaid_cycle_without_attendance_is_recorded_as_non_payable_after_it_ends(): void
    {
        [$commission] = $this->salaryFixture('prepaid', '2026-01-01', '2026-01-31', 10000, 1);
        $service = app(TrainerMonthlySalaryService::class);

        $service->generateForCommission($commission->fresh(), '2026-02-01');

        $this->assertDatabaseHas('trainer_monthly_salaries', [
            'status' => 'cancel',
            'cancellation_reason' => 'no_attendance',
        ]);
        $this->assertDatabaseCount('salary_payable_assignments', 0);
        $this->assertDatabaseHas('trainer_commissions', [
            'id' => $commission->id,
            'salary_amount' => 0,
        ]);
    }

    public function test_postpaid_salary_waits_for_the_cycle_end_and_requires_attendance(): void
    {
        [$commission, $membership] = $this->salaryFixture(
            'postpaid',
            '2026-01-01',
            '2026-01-31',
            10000,
            1,
        );
        $service = app(TrainerMonthlySalaryService::class);

        $this->assertNull($service->generateForCommission($commission, '2026-01-15'));
        $this->assertDatabaseCount('trainer_monthly_salaries', 0);

        $this->attendance($membership, '2026-01-20 10:00:00');
        $salary = $service->generateForCommission($commission->fresh(), '2026-02-01');

        $this->assertSame('pending', $salary?->status);
        $this->assertSame('2026-01-01', $salary?->period_start?->toDateString());
        $this->assertSame('2026-01-31', $salary?->period_end?->toDateString());
        $this->assertDatabaseHas('salary_payable_assignments', [
            'trainer_monthly_salary_id' => $salary?->id,
            'available_amount' => 10000,
        ]);
    }

    public function test_postpaid_cycle_without_attendance_is_recorded_as_non_payable(): void
    {
        [$commission] = $this->salaryFixture('postpaid', '2026-01-01', '2026-01-31', 10000, 1);

        $salary = app(TrainerMonthlySalaryService::class)
            ->generateForCommission($commission, '2026-02-01');

        $this->assertSame('cancel', $salary?->status);
        $this->assertSame('no_attendance', $salary?->cancellation_reason);
        $this->assertDatabaseCount('salary_payable_assignments', 0);
        $this->assertDatabaseHas('trainer_commissions', [
            'id' => $commission->id,
            'salary_amount' => 0,
        ]);
    }

    public function test_freeze_pauses_salary_cycles_and_allows_the_last_salary_in_the_extended_month(): void
    {
        [$commission, $membership] = $this->salaryFixture(
            'postpaid',
            '2026-01-01',
            '2026-03-31',
            30000,
            3,
        );
        PersonMembershipFreeze::query()->create([
            'person_membership_id' => $membership->id,
            'start_date' => '2026-02-01',
            'end_date' => '2026-02-28',
        ]);
        $membership->update(['valid_at' => '2026-04-28']);
        $this->attendance($membership, '2026-01-15 10:00:00');
        $this->attendance($membership, '2026-03-15 10:00:00');
        $this->attendance($membership, '2026-04-15 10:00:00');
        $service = app(TrainerMonthlySalaryService::class);

        $service->generateForCommission($commission->fresh(), '2026-02-01');
        $service->generateForCommission($commission->fresh(), '2026-03-29');
        $service->generateForCommission($commission->fresh(), '2026-04-29');

        $salaries = $commission->monthlySalaries()->orderBy('installment_number')->get();

        $this->assertCount(3, $salaries);
        $this->assertSame([1, 2, 3], $salaries->pluck('installment_number')->all());
        $this->assertSame(
            ['2026-01-31', '2026-03-28', '2026-04-28'],
            $salaries->map(fn ($salary) => $salary->period_end->toDateString())->all(),
        );
        $this->assertSame(['pending'], $salaries->pluck('status')->unique()->values()->all());
        $this->assertSame(30000.0, (float) $salaries->sum('price'));
    }

    public function test_shifted_cycles_can_begin_in_the_same_calendar_month(): void
    {
        [$commission, $membership] = $this->salaryFixture(
            'postpaid',
            '2026-01-01',
            '2026-03-31',
            30000,
            3,
        );
        PersonMembershipFreeze::query()->create([
            'person_membership_id' => $membership->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-28',
        ]);
        $this->attendance($membership, '2026-01-29 10:00:00');
        $this->attendance($membership, '2026-03-15 10:00:00');
        $this->attendance($membership, '2026-04-15 10:00:00');
        $service = app(TrainerMonthlySalaryService::class);

        $service->generateForCommission($commission->fresh(), '2026-03-01');
        $service->generateForCommission($commission->fresh(), '2026-03-29');
        $service->generateForCommission($commission->fresh(), '2026-04-29');

        $salaries = $commission->monthlySalaries()->orderBy('installment_number')->get();

        $this->assertCount(3, $salaries);
        $this->assertSame(
            ['2026-01-01', '2026-03-01', '2026-03-29'],
            $salaries->map(fn ($salary) => $salary->period_start->toDateString())->all(),
        );
        $this->assertSame(
            ['2026-01-01', '2026-03-01', '2026-03-01'],
            $salaries->map(fn ($salary) => $salary->salary_month->toDateString())->all(),
        );
    }

    public function test_plain_cancellation_keeps_generated_salary_and_cancels_only_unearned_installments(): void
    {
        [$commission, $membership] = $this->salaryFixture(
            'prepaid',
            '2026-01-01',
            '2026-03-31',
            30000,
            3,
        );
        $trainer = $commission->trainer;
        $trainer->assignRole(Role::firstOrCreate([
            'name' => 'owner',
            'guard_name' => 'web',
        ], ['g_name' => 'owner']));
        $this->actingAs($trainer);
        $this->attendance($membership, '2026-01-20 10:00:00');
        $salaryService = app(TrainerMonthlySalaryService::class);
        $generatedSalary = $salaryService->generateForCommission($commission->fresh(), '2026-01-20');

        app(MembershipSaleService::class)->cancelMembership($commission->membership_sale_id);

        $commission->refresh();
        $this->assertSame(10000.0, (float) $commission->salary_amount);
        $this->assertSame(30000.0, (float) $commission->initial_salary_amount);
        $this->assertSame(20000.0, (float) $commission->cancelled_unearned_amount);
        $this->assertSame('membership_cancelled', $commission->generation_stopped_reason);
        $this->assertNotNull($commission->generation_stopped_at);
        $this->assertDatabaseHas('trainer_monthly_salaries', [
            'id' => $generatedSalary?->id,
            'status' => 'pending',
            'price' => 10000,
        ]);
        $this->assertDatabaseHas('salary_payable_assignments', [
            'trainer_monthly_salary_id' => $generatedSalary?->id,
            'available_amount' => 10000,
        ]);

        $this->assertNull($salaryService->generateForCommission($commission->fresh(), '2026-04-01'));
        $this->assertDatabaseCount('trainer_monthly_salaries', 1);

        $report = app(TrainerCommissionsReportService::class)->report($trainer, [
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        $row = $report['commissions']->getCollection()->first();
        $this->assertSame(10000.0, (float) $report['summary']['total_commission_amount']);
        $this->assertSame(10000.0, (float) $report['summary']['pending_commission_amount']);
        $this->assertSame(20000.0, (float) $report['summary']['cancelled_unearned_commission_amount']);
        $this->assertSame('cancelled', $row['status']);
        $this->assertSame(30000.0, (float) $row['initial_commission_amount']);
        $this->assertSame(20000.0, (float) $row['cancelled_unearned_amount']);
    }

    public function test_cancelled_membership_status_alone_does_not_change_refund_or_termination_salary_behavior(): void
    {
        [$commission, $membership] = $this->salaryFixture(
            'postpaid',
            '2026-01-01',
            '2026-01-31',
            10000,
            1,
        );
        $this->attendance($membership, '2026-01-20 10:00:00');
        $membership->update(['status' => 'cancelled']);

        $salary = app(TrainerMonthlySalaryService::class)
            ->generateForCommission($commission->fresh(), '2026-02-01');

        $this->assertSame('pending', $salary?->status);
        $this->assertNull($commission->fresh()->generation_stopped_at);
        $this->assertSame(0.0, (float) $commission->fresh()->cancelled_unearned_amount);
    }

    public function test_generation_stop_preserves_an_old_trainers_generated_balance_after_future_amount_was_reassigned(): void
    {
        [$commission, $membership] = $this->salaryFixture(
            'prepaid',
            '2026-01-01',
            '2026-03-31',
            30000,
            3,
        );
        $this->attendance($membership, '2026-01-20 10:00:00');
        app(TrainerMonthlySalaryService::class)
            ->generateForCommission($commission->fresh(), '2026-01-20');

        // Changing the trainer already removes this commission's future share.
        $commission->update(['salary_amount' => 10000]);

        app(TrainerMonthlySalaryService::class)
            ->stopFutureGenerationForMembership($membership);

        $commission->refresh();
        $this->assertSame(10000.0, (float) $commission->salary_amount);
        $this->assertSame(0.0, (float) $commission->cancelled_unearned_amount);
        $this->assertNotNull($commission->generation_stopped_at);
    }

    private function salaryFixture(
        string $mode,
        string $startDate,
        string $endDate,
        float $amount,
        int $installmentCount,
    ): array {
        $gym = Gym::query()->create([
            'name' => 'Salary Gym',
            'trainer_salary_mode' => $mode,
        ]);
        $trainer = User::query()->create([
            'name' => 'Trainer',
            'surname' => 'One',
            'email' => uniqid('trainer-', true).'@example.test',
            'gym_id' => $gym->id,
            'active' => true,
            'password' => Hash::make('password'),
        ]);
        $category = MembershipCategory::query()->create([
            'gym_id' => $gym->id,
            'slug' => uniqid('category-', true),
            'active' => true,
        ]);
        $plan = MembershipPlan::query()->create([
            'membership_category_id' => $category->id,
            'gym_id' => $gym->id,
            'price' => 100000,
            'duration_type' => 'month',
            'duration_value' => $installmentCount,
            'active' => true,
        ]);
        $person = Person::query()->create([
            'name' => 'Customer',
            'surname' => 'One',
            'email' => uniqid('customer-', true).'@example.test',
            'password' => Hash::make('password'),
            'phone' => uniqid('09', false),
        ]);
        $sale = MembershipSale::query()->create([
            'is_hdm' => true,
            'user_id' => $trainer->id,
            'person_id' => $person->id,
            'gym_id' => $gym->id,
            'membership_plan_id' => $plan->id,
            'total_price' => 100000,
            'final_price' => 100000,
            'payment_status' => 'paid',
            'sold_at' => $startDate.' 09:00:00',
        ]);
        $membership = PersonMembership::query()->create([
            'membership_sale_id' => $sale->id,
            'user_id' => $trainer->id,
            'person_id' => $person->id,
            'gym_id' => $gym->id,
            'membership_plan_id' => $plan->id,
            'trainer_id' => $trainer->id,
            'status' => 'active',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'valid_at' => $endDate,
        ]);
        $commission = TrainerCommission::query()->create([
            'trainer_id' => $trainer->id,
            'membership_sale_id' => $sale->id,
            'person_membership_id' => $membership->id,
            'salary_type' => 'fixed',
            'salary_value' => $amount,
            'salary_amount' => $amount,
            'initial_salary_amount' => $amount,
            'calculation_mode' => $mode,
            'salary_start_installment' => 1,
            'salary_installment_count' => $installmentCount,
            'status' => 'pending',
            'created_at' => $startDate.' 09:00:00',
        ]);

        return [$commission, $membership];
    }

    private function attendance(PersonMembership $membership, string $date): AttendanceSheet
    {
        $attendance = AttendanceSheet::query()->create([
            'relation_type' => Person::class,
            'relation_id' => $membership->person_id,
            'gym_id' => $membership->gym_id,
            'date' => $date,
            'direction' => 'entry',
            'type' => 'manual',
            'online' => true,
        ]);

        $attendance->personMemberships()->attach($membership->id);

        return $attendance;
    }
}
