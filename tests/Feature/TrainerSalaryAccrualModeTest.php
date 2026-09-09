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
use App\Services\TrainerMonthlySalaries\TrainerMonthlySalaryService;
use Database\Seeders\GymSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_prepaid_salary_is_created_in_advance_and_cancelled_after_a_cycle_without_attendance(): void
    {
        [$commission] = $this->salaryFixture('prepaid', '2026-01-01', '2026-01-31', 10000, 1);
        $service = app(TrainerMonthlySalaryService::class);

        $salary = $service->generateForCommission($commission, '2026-01-01');

        $this->assertSame('pending', $salary?->status);
        $this->assertDatabaseHas('salary_payable_assignments', [
            'trainer_monthly_salary_id' => $salary?->id,
            'available_amount' => 10000,
        ]);

        $service->generateForCommission($commission->fresh(), '2026-02-01');

        $this->assertDatabaseHas('trainer_monthly_salaries', [
            'id' => $salary?->id,
            'status' => 'cancel',
            'cancellation_reason' => 'no_attendance',
        ]);
        $this->assertDatabaseHas('salary_payable_assignments', [
            'trainer_monthly_salary_id' => $salary?->id,
            'available_amount' => 0,
        ]);
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
        return AttendanceSheet::query()->create([
            'relation_type' => Person::class,
            'relation_id' => $membership->person_id,
            'membership_plan_id' => $membership->membership_plan_id,
            'person_membership_id' => $membership->id,
            'date' => $date,
            'direction' => 'entry',
            'type' => 'manual',
            'online' => true,
        ]);
    }
}
