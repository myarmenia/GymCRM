<?php

namespace Tests\Feature;

use App\DTO\People\PersonDTO;
use App\Models\ActivityLog;
use App\Models\Discount;
use App\Models\EntryCode;
use App\Models\Gym;
use App\Models\MembershipCategory;
use App\Models\MembershipPlan;
use App\Models\MembershipSale;
use App\Models\PaymentMethod;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use App\Services\MembershipSales\MembershipSaleFreezeService;
use App\Services\MembershipSales\MembershipSaleService;
use App\Services\People\PersonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_person_registration_creates_a_safe_activity_log(): void
    {
        $gym = Gym::query()->create(['name' => 'Main gym']);
        $actor = $this->userWithRole($gym, 'super_admin');
        $entryCode = EntryCode::query()->create([
            'gym_id' => $gym->id,
            'token' => 'private-entry-token',
            'status' => true,
            'activation' => false,
            'type' => 'person',
        ]);

        $this->actingAs($actor);

        $person = app(PersonService::class)->store(new PersonDTO(
            name: 'John',
            surname: 'Doe',
            image: null,
            email: 'john@example.com',
            password: 'secret-password',
            phone: '+37499123456',
            type: 'visitor',
            birth_date: '1990-01-01',
            entry_code_id: $entryCode->id,
            gender: 'male',
            fcm_token: 'private-fcm-token',
        ));

        $log = ActivityLog::query()->sole();
        $snapshot = $log->meta['snapshot'];

        $this->assertSame('person.created', $log->action);
        $this->assertSame($gym->id, $log->gym_id);
        $this->assertSame($actor->id, $log->user_id);
        $this->assertSame(Person::class, $log->loggable_type);
        $this->assertSame($person->id, $log->loggable_id);
        $this->assertSame('John', $snapshot['name']);
        $this->assertSame($entryCode->id, $snapshot['entry_code_id']);
        $this->assertArrayNotHasKey('password', $snapshot);
        $this->assertArrayNotHasKey('fcm_token', $snapshot);
        $this->assertNotContains('private-entry-token', $snapshot, true);

        $this->get("/hy/logs/{$log->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Logs/Show')
                ->where('log.action', 'person.created')
                ->where('log.new_values.name', 'John')
                ->where('log.new_values.entry_code_id', $entryCode->id));
    }

    public function test_non_owner_only_sees_activity_logs_from_their_gym(): void
    {
        $gym = Gym::query()->create(['name' => 'Main gym']);
        $otherGym = Gym::query()->create(['name' => 'Other gym']);
        $actor = $this->userWithRole($gym, 'admin');
        $person = $this->person('Visible Person');
        $otherPerson = $this->person('Hidden Person');

        ActivityLog::query()->create([
            'gym_id' => $gym->id,
            'user_id' => $actor->id,
            'action' => 'person.created',
            'loggable_type' => Person::class,
            'loggable_id' => $person->id,
            'message' => 'Visible log',
            'meta' => ['snapshot' => ['name' => $person->name]],
        ]);

        ActivityLog::query()->create([
            'gym_id' => $otherGym->id,
            'user_id' => $actor->id,
            'action' => 'person.created',
            'loggable_type' => Person::class,
            'loggable_id' => $otherPerson->id,
            'message' => 'Hidden log',
            'meta' => ['snapshot' => ['name' => $otherPerson->name]],
        ]);

        $this->actingAs($actor)
            ->get('/hy/logs')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Logs/Index')
                ->has('logs.data', 1)
                ->where('logs.data.0.description', 'Visible log'));
    }

    public function test_membership_sale_creation_creates_a_complete_activity_log(): void
    {
        $gym = Gym::query()->create(['name' => 'Main gym']);
        $actor = $this->userWithRole($gym, 'super_admin');
        $person = $this->person('Membership Customer');
        $person->gyms()->attach($gym->id);
        $category = MembershipCategory::query()->create([
            'gym_id' => $gym->id,
            'slug' => 'monthly',
        ]);
        $plan = MembershipPlan::query()->create([
            'membership_category_id' => $category->id,
            'gym_id' => $gym->id,
            'price' => 10000,
            'duration_type' => 'month',
            'duration_value' => 1,
            'active' => true,
        ]);
        $paymentMethod = PaymentMethod::query()->create(['slug' => 'cash']);

        $this->actingAs($actor);

        $sale = app(MembershipSaleService::class)->store([
            'person_id' => $person->id,
            'membership_plan_id' => $plan->id,
            'start_date' => '2026-08-03',
            'is_full_payment' => true,
            'payment_method_id' => $paymentMethod->id,
            'is_hdm' => true,
            'payment_notes' => 'Paid at reception',
        ]);

        $log = ActivityLog::query()->sole();
        $snapshot = $log->meta['snapshot'];

        $this->assertSame('membership_sale.created', $log->action);
        $this->assertSame($gym->id, $log->gym_id);
        $this->assertSame($actor->id, $log->user_id);
        $this->assertSame(MembershipSale::class, $log->loggable_type);
        $this->assertSame($sale->id, $log->loggable_id);
        $this->assertSame($person->id, $snapshot['person']['id']);
        $this->assertSame($plan->id, $snapshot['membership_plan']['id']);
        $this->assertSame('waiting', $snapshot['membership']['status']);
        $this->assertSame('2026-08-03', $snapshot['membership']['start_date']);
        $this->assertSame('paid', $snapshot['payment_status']);
        $this->assertSame(10000.0, (float) $snapshot['payments'][0]['amount']);
        $this->assertSame("#{$paymentMethod->id}", $snapshot['payments'][0]['payment_method']);
        $this->assertTrue($snapshot['payments'][0]['is_hdm']);

        $this->get("/hy/logs/{$log->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Logs/Show')
                ->where('log.action', 'membership_sale.created')
                ->where('log.new_values.person.id', $person->id)
                ->where('log.new_values.membership_plan.id', $plan->id));
    }

    public function test_membership_sale_menu_mutations_create_before_and_after_logs(): void
    {
        $gym = Gym::query()->create(['name' => 'Main gym']);
        $actor = $this->userWithRole($gym, 'super_admin');
        $person = $this->person('Membership Customer');
        $person->gyms()->attach($gym->id);
        $category = MembershipCategory::query()->create([
            'gym_id' => $gym->id,
            'slug' => 'monthly',
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
        $discount = Discount::query()->create([
            'type' => 'fixed',
            'value' => 1000,
            'status' => true,
        ]);
        $plan->discounts()->attach($discount->id);
        $paymentMethod = PaymentMethod::query()->create(['slug' => 'cash']);

        $this->actingAs($actor);
        $service = app(MembershipSaleService::class);
        $sale = $service->store([
            'person_id' => $person->id,
            'membership_plan_id' => $plan->id,
            'start_date' => '2026-08-03',
            'is_partial_payment' => true,
            'amount' => 4000,
            'payment_method_id' => $paymentMethod->id,
            'reminder_scheduled_at' => '2026-08-10 10:00:00',
            'reminder_recipient_ids' => [$actor->id],
        ]);
        ActivityLog::query()->delete();

        app(MembershipSaleFreezeService::class)->storeFreeze($sale->id, [
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-05',
            'notes' => 'Holiday',
        ]);
        $service->storePayment($sale->id, [
            'is_full_payment' => true,
            'amount' => 6000,
            'payment_method_id' => $paymentMethod->id,
        ]);
        $service->update($sale->id, [
            'membership_discount_ids' => [$discount->id],
        ]);
        $service->cancelMembership($sale->id);
        $service->storeRefund($sale->id, [
            'is_partial_refund' => true,
            'amount' => 2500,
            'payment_method_id' => $paymentMethod->id,
        ]);

        $logs = ActivityLog::query()->orderBy('id')->get();

        $this->assertSame([
            'membership_sale.frozen',
            'membership_sale.payment_added',
            'membership_sale.updated',
            'membership_sale.cancelled',
            'membership_sale.refund_added',
        ], $logs->pluck('action')->all());

        foreach ($logs as $log) {
            $this->assertSame($sale->id, $log->loggable_id);
            $this->assertNotEmpty($log->changes);
            $this->assertArrayHasKey('old_snapshot', $log->meta);
            $this->assertArrayHasKey('snapshot', $log->meta);
        }

        $freezeLog = $logs->firstWhere('action', 'membership_sale.frozen');
        $this->assertSame(1, $freezeLog->meta['old_snapshot']['membership']['freeze_left']);
        $this->assertSame(0, $freezeLog->meta['snapshot']['membership']['freeze_left']);
        $this->assertSame('Holiday', $freezeLog->meta['snapshot']['membership']['freezes'][0]['notes']);

        $cancelLog = $logs->firstWhere('action', 'membership_sale.cancelled');
        $this->assertSame('frozen', $cancelLog->meta['old_snapshot']['membership']['status']);
        $this->assertSame('cancelled', $cancelLog->meta['snapshot']['membership']['status']);
    }

    public function test_changing_membership_trainer_creates_an_activity_log(): void
    {
        $gym = Gym::query()->create(['name' => 'Main gym']);
        $actor = $this->userWithRole($gym, 'super_admin');
        $trainerRole = Role::query()->create([
            'name' => 'trainer',
            'guard_name' => 'web',
            'g_name' => 'trainer',
        ]);
        $trainerRole->id = 7;
        $trainerRole->save();
        $oldTrainer = User::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Old',
            'surname' => 'Trainer',
            'email' => Str::uuid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $newTrainer = User::query()->create([
            'gym_id' => $gym->id,
            'name' => 'New',
            'surname' => 'Trainer',
            'email' => Str::uuid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $oldTrainer->assignRole($trainerRole);
        $newTrainer->assignRole($trainerRole);

        $person = $this->person('Membership Customer');
        $person->gyms()->attach($gym->id);
        $category = MembershipCategory::query()->create([
            'gym_id' => $gym->id,
            'slug' => 'personal',
        ]);
        $plan = MembershipPlan::query()->create([
            'membership_category_id' => $category->id,
            'gym_id' => $gym->id,
            'price' => 10000,
            'duration_type' => 'month',
            'duration_value' => 1,
            'active' => true,
        ]);
        $plan->trainers()->attach([
            $oldTrainer->id => ['price_type' => 'fixed', 'price_value' => 1000, 'total_price' => 1000],
            $newTrainer->id => ['price_type' => 'fixed', 'price_value' => 1000, 'total_price' => 1000],
        ]);
        $paymentMethod = PaymentMethod::query()->create(['slug' => 'cash']);

        $this->actingAs($actor);
        $service = app(MembershipSaleService::class);
        $sale = $service->store([
            'person_id' => $person->id,
            'membership_plan_id' => $plan->id,
            'trainer_id' => $oldTrainer->id,
            'start_date' => '2026-08-03',
            'is_full_payment' => true,
            'payment_method_id' => $paymentMethod->id,
        ]);
        ActivityLog::query()->delete();

        $service->changeTrainer($sale->id, ['trainer_id' => $newTrainer->id]);

        $log = ActivityLog::query()->sole();
        $this->assertSame('membership_sale.trainer_changed', $log->action);
        $this->assertSame($oldTrainer->id, $log->meta['old_snapshot']['membership']['trainer']['id']);
        $this->assertSame($newTrainer->id, $log->meta['snapshot']['membership']['trainer']['id']);
    }

    private function userWithRole(Gym $gym, string $roleName): User
    {
        $role = Role::query()->create([
            'name' => $roleName,
            'guard_name' => 'web',
            'g_name' => $roleName,
        ]);

        $user = User::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Audit',
            'surname' => 'Admin',
            'email' => Str::uuid().'@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function person(string $name): Person
    {
        return Person::query()->create([
            'name' => $name,
            'email' => Str::uuid().'@example.com',
            'password' => Hash::make('password'),
            'phone' => '+374'.random_int(10000000, 99999999),
        ]);
    }
}
