<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\MembershipCategory;
use App\Models\MembershipPlan;
use App\Models\MembershipSale;
use App\Models\PaymentMethod;
use App\Models\Person;
use App\Models\PersonMembership;
use App\Models\SalaryPayableAssignment;
use App\Models\SalespersonCommission;
use App\Models\TrainerCommission;
use App\Models\TrainerMonthlySalary;
use App\Models\User;
use App\Services\SalaryPayouts\SalaryPayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalaryPayoutServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_data_combines_trainer_and_salesperson_payables(): void
    {
        [$actor, $payee] = $this->payableFixture();

        $data = app(SalaryPayoutService::class)->pageData($actor);

        $this->assertSame(2, $data['summary']['payable_count']);
        $this->assertSame(15000.0, $data['summary']['payable_amount']);
        $this->assertSame(
            ['salesperson_commission', 'trainer_monthly_salary'],
            collect($data['payables']->items())->pluck('type')->sort()->values()->all(),
        );
        $this->assertSame(
            [$payee->id],
            collect($data['filterOptions']['payees'])->pluck('value')->all(),
        );
    }

    public function test_it_pays_trainer_and_salesperson_lines_for_the_same_employee_in_one_audited_payout(): void
    {
        [
            $actor,
            $payee,
            $paymentMethod,
            $trainerSalary,
            $salespersonCommission,
            $trainerAssignment,
            $salespersonAssignment,
        ] = $this->payableFixture();

        $payout = app(SalaryPayoutService::class)->pay($actor, [
            'items' => [
                ['id' => $trainerAssignment->id, 'amount' => 10000],
                ['id' => $salespersonAssignment->id, 'amount' => 5000],
            ],
            'payment_method_id' => $paymentMethod->id,
            'paid_at' => '2026-07-27 14:30:00',
            'reference' => 'BANK-42',
            'notes' => 'July settlement',
        ]);

        $this->assertSame($payee->id, $payout->payee_id);
        $this->assertSame($actor->id, $payout->paid_by);
        $this->assertSame('paid', $payout->status);
        $this->assertSame('15000.00', $payout->amount);
        $this->assertCount(2, $payout->items);

        $this->assertDatabaseHas('trainer_monthly_salaries', [
            'id' => $trainerSalary->id,
            'status' => 'paid',
            'salary_payout_id' => null,
        ]);
        $this->assertDatabaseHas('trainer_commissions', [
            'id' => $trainerSalary->trainer_commission_id,
            'salary_amount' => 0,
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('salesperson_commissions', [
            'id' => $salespersonCommission->id,
            'status' => 'paid',
            'salary_payout_id' => null,
        ]);
        $this->assertDatabaseHas('salary_payout_items', [
            'salary_payout_id' => $payout->id,
            'trainer_monthly_salary_id' => $trainerSalary->id,
            'amount' => 10000,
        ]);
        $this->assertDatabaseHas('salary_payout_items', [
            'salary_payout_id' => $payout->id,
            'salesperson_commission_id' => $salespersonCommission->id,
            'amount' => 5000,
        ]);
    }

    public function test_void_keeps_the_payout_history_and_restores_its_payables(): void
    {
        [
            $actor,
            ,
            $paymentMethod,
            $trainerSalary,
            $salespersonCommission,
            $trainerAssignment,
            $salespersonAssignment,
        ] = $this->payableFixture();
        $service = app(SalaryPayoutService::class);

        $payout = $service->pay($actor, [
            'items' => [
                ['id' => $trainerAssignment->id, 'amount' => 10000],
                ['id' => $salespersonAssignment->id, 'amount' => 5000],
            ],
            'payment_method_id' => $paymentMethod->id,
        ]);

        $service->void($actor, $payout, 'Wrong payment method');

        $this->assertDatabaseHas('salary_payouts', [
            'id' => $payout->id,
            'status' => 'voided',
            'voided_by' => $actor->id,
            'void_reason' => 'Wrong payment method',
        ]);
        $this->assertDatabaseHas('trainer_monthly_salaries', [
            'id' => $trainerSalary->id,
            'status' => 'pending',
            'salary_payout_id' => null,
        ]);
        $this->assertDatabaseHas('trainer_commissions', [
            'id' => $trainerSalary->trainer_commission_id,
            'salary_amount' => 10000,
            'status' => 'pending',
            'paid_at' => null,
        ]);
        $this->assertDatabaseHas('salesperson_commissions', [
            'id' => $salespersonCommission->id,
            'status' => 'pending',
            'salary_payout_id' => null,
            'paid_at' => null,
        ]);
        $this->assertDatabaseCount('salary_payout_items', 2);
        $this->assertDatabaseCount('salary_payout_refunds', 1);
    }

    public function test_partial_payment_and_refund_reopen_only_the_refunded_balance(): void
    {
        [
            $actor,
            ,
            $paymentMethod,
            $trainerSalary,
            ,
            $trainerAssignment,
        ] = $this->payableFixture();
        $service = app(SalaryPayoutService::class);

        $payout = $service->pay($actor, [
            'items' => [
                ['id' => $trainerAssignment->id, 'amount' => 4000],
            ],
            'payment_method_id' => $paymentMethod->id,
        ]);
        $payoutItem = $payout->items->first();

        $this->assertDatabaseHas('salary_payable_assignments', [
            'id' => $trainerAssignment->id,
            'available_amount' => 6000,
        ]);
        $this->assertDatabaseHas('trainer_commissions', [
            'id' => $trainerSalary->trainer_commission_id,
            'salary_amount' => 6000,
        ]);

        $service->refund($actor, $payout, [
            'payout_item_id' => $payoutItem->id,
            'amount' => 1500,
            'payment_method_id' => $paymentMethod->id,
            'reason' => 'Partial return',
        ]);

        $this->assertDatabaseHas('salary_payable_assignments', [
            'id' => $trainerAssignment->id,
            'available_amount' => 7500,
        ]);
        $this->assertDatabaseHas('trainer_commissions', [
            'id' => $trainerSalary->trainer_commission_id,
            'salary_amount' => 7500,
        ]);
        $this->assertDatabaseHas('salary_payouts', [
            'id' => $payout->id,
            'status' => 'paid',
        ]);
    }

    private function payableFixture(): array
    {
        $gym = Gym::query()->create([
            'name' => 'Central Gym',
        ]);

        $actor = $this->user('Owner', 'User', 'owner@example.test', null);
        $payee = $this->user('Employee', 'One', 'employee@example.test', $gym->id);
        $role = Role::query()->create([
            'name' => 'owner',
            'guard_name' => 'web',
            'g_name' => 'owner',
        ]);
        $actor->assignRole($role);

        $paymentMethod = PaymentMethod::query()->create([
            'slug' => 'cash',
        ]);
        $category = MembershipCategory::query()->create([
            'gym_id' => $gym->id,
            'slug' => 'standard',
            'active' => true,
        ]);
        $plan = MembershipPlan::query()->create([
            'membership_category_id' => $category->id,
            'gym_id' => $gym->id,
            'price' => 100000,
            'duration_type' => 'month',
            'duration_value' => 1,
            'active' => true,
        ]);
        $person = Person::query()->create([
            'name' => 'Customer',
            'surname' => 'One',
            'email' => 'customer@example.test',
            'password' => Hash::make('password'),
            'phone' => '099000001',
        ]);
        $sale = MembershipSale::query()->create([
            'user_id' => $payee->id,
            'person_id' => $person->id,
            'gym_id' => $gym->id,
            'membership_plan_id' => $plan->id,
            'total_price' => 100000,
            'final_price' => 100000,
            'payment_status' => 'paid',
            'sold_at' => '2026-07-01 10:00:00',
        ]);
        $membership = PersonMembership::query()->create([
            'membership_sale_id' => $sale->id,
            'user_id' => $payee->id,
            'person_id' => $person->id,
            'gym_id' => $gym->id,
            'membership_plan_id' => $plan->id,
            'trainer_id' => $payee->id,
            'status' => 'active',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
        ]);
        $trainerCommission = TrainerCommission::query()->create([
            'trainer_id' => $payee->id,
            'membership_sale_id' => $sale->id,
            'person_membership_id' => $membership->id,
            'salary_type' => 'fixed',
            'salary_value' => 10000,
            'salary_amount' => 10000,
            'status' => 'pending',
        ]);
        $trainerSalary = TrainerMonthlySalary::query()->create([
            'trainer_id' => $payee->id,
            'person_membership_id' => $membership->id,
            'trainer_commission_id' => $trainerCommission->id,
            'salary_month' => '2026-07-01',
            'price' => 10000,
            'status' => 'pending',
        ]);
        $salespersonCommission = SalespersonCommission::query()->create([
            'salesperson_id' => $payee->id,
            'membership_sale_id' => $sale->id,
            'person_membership_id' => $membership->id,
            'membership_plan_id' => $plan->id,
            'salary_type' => 'fixed',
            'salary_value' => 5000,
            'salary_amount' => 5000,
            'sale_amount' => 100000,
            'status' => 'pending',
        ]);
        $trainerAssignment = SalaryPayableAssignment::query()->create([
            'gym_id' => $gym->id,
            'payee_id' => $payee->id,
            'source_type' => 'trainer_monthly_salary',
            'trainer_monthly_salary_id' => $trainerSalary->id,
            'trainer_commission_id' => $trainerCommission->id,
            'root_key' => "trainer:{$trainerSalary->id}",
            'amount' => 10000,
            'available_amount' => 10000,
        ]);
        $salespersonAssignment = SalaryPayableAssignment::query()->create([
            'gym_id' => $gym->id,
            'payee_id' => $payee->id,
            'source_type' => 'salesperson_commission',
            'salesperson_commission_id' => $salespersonCommission->id,
            'root_key' => "salesperson:{$salespersonCommission->id}",
            'amount' => 5000,
            'available_amount' => 5000,
        ]);

        return [
            $actor,
            $payee,
            $paymentMethod,
            $trainerSalary,
            $salespersonCommission,
            $trainerAssignment,
            $salespersonAssignment,
        ];
    }

    private function user(string $name, string $surname, string $email, ?int $gymId): User
    {
        return User::query()->create([
            'name' => $name,
            'surname' => $surname,
            'email' => $email,
            'gym_id' => $gymId,
            'active' => true,
            'password' => Hash::make('password'),
        ]);
    }
}
