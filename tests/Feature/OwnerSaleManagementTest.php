<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\OwnerSale;
use App\Models\Role;
use App\Models\User;
use App\Services\OwnerSales\OwnerSaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OwnerSaleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config()->set('sync.enabled', false);
    }

    public function test_owner_can_create_update_cancel_and_delete_a_sale(): void
    {
        $owner = $this->userWithRole('owner');
        $gym = $this->gym();

        $this->actingAs($owner)->post(route('owner-sales.store', ['locale' => 'hy']), [
            'gym_id' => $gym->id,
            'amount' => 25000,
            'payment_type' => 'transfer',
            'payment_status' => 'paid',
            'status' => 'active',
            'starts_at' => today()->toDateString(),
            'ends_at' => today()->addMonth()->toDateString(),
            'notes' => 'First payment',
        ])->assertRedirect(route('owner-sales.index', ['locale' => 'hy']));

        $sale = OwnerSale::query()->firstOrFail();
        $this->assertNotNull($sale->uuid);
        $this->assertSame(1, $sale->version);
        $this->actingAs($owner)->get(route('owner-sales.edit', [
            'locale' => 'hy', 'ownerSale' => $sale->id,
        ]))->assertInertia(fn (Assert $page) => $page
            ->where('sale.starts_at', today()->toDateString())
            ->where('sale.ends_at', today()->addMonth()->toDateString()));

        $this->actingAs($owner)->put(route('owner-sales.update', [
            'locale' => 'hy', 'ownerSale' => $sale->id,
        ]), [
            'gym_id' => $gym->id,
            'amount' => 30000,
            'payment_type' => 'cash',
            'payment_status' => 'paid',
            'status' => 'active',
            'starts_at' => today()->toDateString(),
            'ends_at' => today()->addMonths(2)->toDateString(),
        ])->assertRedirect();

        $sale->refresh();
        $this->assertSame('30000.00', $sale->amount);
        $this->assertSame(2, $sale->version);

        $this->actingAs($owner)->patch(route('owner-sales.cancel', [
            'locale' => 'hy', 'ownerSale' => $sale->id,
        ]))->assertRedirect();

        $this->assertSame('cancelled', $sale->refresh()->status);
        $this->assertNotNull($sale->cancelled_at);

        $this->actingAs($owner)->delete(route('owner-sales.destroy', [
            'locale' => 'hy', 'ownerSale' => $sale->id,
        ]))->assertRedirect();

        $this->assertSoftDeleted('owner_sales', ['id' => $sale->id]);
    }

    public function test_management_is_owner_only(): void
    {
        $gym = $this->gym();
        $manager = $this->userWithRole('manager', $gym);

        $this->actingAs($manager)
            ->get(route('owner-sales.index', ['locale' => 'en']))
            ->assertForbidden();
    }

    public function test_existing_gym_without_sales_remains_accessible(): void
    {
        $gym = $this->gym();
        $manager = $this->userWithRole('manager', $gym);

        $this->actingAs($manager)->get('/hy/dashboard')->assertOk();
        $this->actingAs($manager)
            ->get(route('owner-sales.access-expired', ['locale' => 'hy']))
            ->assertRedirect(route('dashboard', ['locale' => 'hy']));
    }

    public function test_gym_user_is_blocked_when_current_period_is_inactive(): void
    {
        $gym = $this->gym();
        $manager = $this->userWithRole('manager', $gym);
        $this->sale($gym, ['payment_status' => 'unpaid', 'status' => 'inactive']);

        $this->actingAs($manager)->get('/hy/dashboard')
            ->assertRedirect(route('owner-sales.access-expired', ['locale' => 'hy']));

        $this->actingAs($manager)->get(route('owner-sales.access-expired', ['locale' => 'hy']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('OwnerSales/AccessExpired'));
    }

    public function test_current_paid_period_allows_access_and_owner_is_never_blocked(): void
    {
        $gym = $this->gym();
        $manager = $this->userWithRole('manager', $gym);
        $this->sale($gym);

        $this->actingAs($manager)->get('/hy/dashboard')->assertOk();

        $expiredGym = $this->gym('Expired gym');
        $this->sale($expiredGym, [
            'starts_at' => today()->subMonth(),
            'ends_at' => today()->subDay(),
        ]);
        $this->actingAs($this->userWithRole('owner'))
            ->get(route('owner-sales.index', ['locale' => 'hy']))
            ->assertOk();
    }

    public function test_summary_uses_amounts_for_payment_statuses_and_counts_for_other_states(): void
    {
        $gym = $this->gym();
        $this->sale($gym, ['amount' => 12000, 'payment_status' => 'paid']);
        $this->sale($gym, ['amount' => 3000, 'payment_status' => 'unpaid']);
        $cancelled = $this->sale($gym, ['amount' => 7000]);
        $cancelled->update(['status' => 'cancelled']);

        $summary = app(OwnerSaleService::class)->summary(['gym_id' => $gym->id]);

        $this->assertSame(3, $summary['total_count']);
        $this->assertSame(12000.0, $summary['paid_amount']);
        $this->assertSame(3000.0, $summary['unpaid_amount']);
        $this->assertSame(1, $summary['cancelled_count']);
    }

    public function test_archived_sale_is_hidden_from_general_list_and_visible_in_archive(): void
    {
        $owner = $this->userWithRole('owner');
        $sale = $this->sale($this->gym());

        $this->actingAs($owner)->patch(route('owner-sales.archive', [
            'locale' => 'hy', 'ownerSale' => $sale->id,
        ]))->assertRedirect();

        $this->assertSame('archived', $sale->refresh()->status);
        $service = app(OwnerSaleService::class);
        $this->assertSame(0, $service->paginate(['tab' => 'all'])->total());
        $this->assertSame(1, $service->paginate(['tab' => 'archived'])->total());
        $this->assertSame(1, $service->summary()['archived_count']);
    }

    public function test_period_filter_returns_overlapping_sales(): void
    {
        $gym = $this->gym();
        $matching = $this->sale($gym, [
            'starts_at' => '2026-10-01',
            'ends_at' => '2026-10-31',
        ]);
        $this->sale($gym, [
            'starts_at' => '2026-11-01',
            'ends_at' => '2026-11-30',
        ]);

        $page = app(OwnerSaleService::class)->paginate([
            'tab' => 'all',
            'date_from' => '2026-10-10',
            'date_to' => '2026-10-20',
        ]);

        $this->assertSame([$matching->id], collect($page->items())->pluck('id')->all());
    }

    public function test_active_unpaid_period_allows_temporary_access(): void
    {
        $owner = $this->userWithRole('owner');
        $gym = $this->gym();

        $this->actingAs($owner)->post(route('owner-sales.store', ['locale' => 'ru']), [
            'gym_id' => $gym->id,
            'amount' => 5000,
            'payment_type' => 'cash',
            'payment_status' => 'unpaid',
            'status' => 'active',
            'starts_at' => today()->toDateString(),
            'ends_at' => today()->addMonth()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('owner_sales', [
            'gym_id' => $gym->id,
            'payment_type' => 'cash',
            'payment_status' => 'unpaid',
            'status' => 'active',
        ]);

        $manager = $this->userWithRole('manager', $gym);
        $this->actingAs($manager)->get('/ru/dashboard')->assertOk();
    }

    public function test_expiry_notification_is_sent_once_three_days_before_end(): void
    {
        $owner = $this->userWithRole('owner');
        $gym = $this->gym();
        $sale = $this->sale($gym, ['ends_at' => today()->addDays(3)]);

        $service = app(OwnerSaleService::class);
        $originalVersion = (int) $sale->version;
        $this->assertSame(1, $service->sendExpiryNotifications());
        $this->assertNotNull($sale->refresh()->expiry_notified_at);
        $this->assertSame($originalVersion, (int) $sale->version);
        $this->assertDatabaseHas('notifications', [
            'recipient_id' => $owner->id,
        ]);
        $this->assertSame(0, $service->sendExpiryNotifications());
    }

    private function gym(string $name = 'Main gym'): Gym
    {
        return Gym::query()->create(['name' => $name]);
    }

    private function userWithRole(string $roleName, ?Gym $gym = null): User
    {
        $role = Role::query()->firstOrCreate([
            'name' => $roleName,
            'guard_name' => 'web',
            'g_name' => $roleName,
        ]);
        $user = User::factory()->create(['gym_id' => $gym?->id, 'active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function sale(Gym $gym, array $attributes = []): OwnerSale
    {
        return OwnerSale::query()->create([
            'gym_id' => $gym->id,
            'amount' => 10000,
            'payment_type' => 'cash',
            'payment_status' => 'paid',
            'starts_at' => today()->subDay(),
            'ends_at' => today()->addMonth(),
            'status' => 'active',
            ...$attributes,
        ]);
    }
}
