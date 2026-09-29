<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\HdmCashier;
use App\Models\HdmConfig;
use App\Models\HdmOperation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HdmConfigurationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('sync.enabled', false);
    }

    public function test_owner_can_manage_a_configuration_and_its_cashier_without_exposing_secrets(): void
    {
        $gym = $this->gym();
        $owner = $this->owner();
        $cashierUser = User::factory()->create(['gym_id' => $gym->id]);

        $this->actingAs($owner)
            ->post(route('hdm-configurations.store', ['locale' => 'hy']), [
                'gym_id' => $gym->id,
                'name' => 'Reception',
                'ip' => '192.168.1.10',
                'port' => 6000,
                'password' => 'device-secret',
                'status' => true,
            ])
            ->assertRedirect();

        $config = HdmConfig::query()->firstOrFail();
        $this->assertNotNull($config->uuid);

        $this->actingAs($owner)
            ->post(route('hdm-configurations.cashiers.store', [
                'locale' => 'hy',
                'hdmConfig' => $config->id,
            ]), [
                'user_id' => $cashierUser->id,
                'name' => 'Main cashier',
                'login' => 'cashier-1',
                'pin' => '1234',
                'status' => true,
            ])
            ->assertRedirect();

        $cashier = HdmCashier::query()->firstOrFail();
        $versionAfterCashierCreation = (int) $config->refresh()->version;
        $this->assertGreaterThan(1, $versionAfterCashierCreation);
        $this->actingAs($owner)
            ->get(route('hdm-configurations.edit', [
                'locale' => 'hy',
                'hdmConfig' => $config->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('HdmConfigurations/Edit')
                ->where('config.id', $config->id)
                ->missing('config.password')
                ->missing('config.cashiers.0.pin')
                ->missing('config.cashiers.0.session_key'));

        $this->actingAs($owner)
            ->put(route('hdm-configurations.update', [
                'locale' => 'hy',
                'hdmConfig' => $config->id,
            ]), [
                'name' => 'Updated reception',
                'ip' => '192.168.1.11',
                'port' => 6001,
                'password' => '',
                'status' => true,
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->put(route('hdm-configurations.cashiers.update', [
                'locale' => 'hy',
                'hdmConfig' => $config->id,
                'cashier' => $cashier->id,
            ]), [
                'user_id' => $cashierUser->id,
                'name' => 'Updated cashier',
                'login' => 'cashier-1',
                'pin' => '',
                'status' => true,
            ])
            ->assertRedirect();

        $this->assertSame('device-secret', $config->refresh()->password);
        $this->assertSame('1234', $cashier->refresh()->pin);
        $this->assertGreaterThan($versionAfterCashierCreation, (int) $config->refresh()->version);
    }

    public function test_configuration_management_is_owner_only_and_delete_is_soft(): void
    {
        $gym = $this->gym();
        $config = HdmConfig::query()->create([
            'gym_id' => $gym->id,
            'name' => 'Bar',
            'ip' => '10.0.0.10',
            'port' => 6000,
            'password' => 'secret',
            'status' => true,
        ]);
        $regularUser = User::factory()->create(['gym_id' => $gym->id]);

        $this->actingAs($regularUser)
            ->get(route('hdm-configurations.index', ['locale' => 'en']))
            ->assertForbidden();

        $this->actingAs($this->owner())
            ->delete(route('hdm-configurations.destroy', [
                'locale' => 'en',
                'hdmConfig' => $config->id,
            ]))
            ->assertRedirect(route('hdm-configurations.index', ['locale' => 'en']));

        $this->assertSoftDeleted('hdm_configs', ['id' => $config->id]);
    }

    public function test_configuration_with_fiscal_operations_cannot_be_deleted(): void
    {
        $config = HdmConfig::query()->create([
            'gym_id' => $this->gym()->id,
            'name' => 'Protected config',
            'ip' => '10.0.0.20',
            'port' => 6000,
            'password' => 'secret',
            'status' => true,
        ]);
        HdmOperation::query()->create([
            'hdm_config_id' => $config->id,
            'transaction_type' => 'sale',
        ]);

        $this->actingAs($this->owner())
            ->delete(route('hdm-configurations.destroy', [
                'locale' => 'hy',
                'hdmConfig' => $config->id,
            ]))
            ->assertRedirect()
            ->assertSessionHas('error', __('backend_messages.hdm_config_has_operations'));

        $this->assertNotSoftDeleted('hdm_configs', ['id' => $config->id]);
    }

    private function owner(): User
    {
        $role = Role::query()->firstOrCreate([
            'name' => 'owner',
            'guard_name' => 'web',
            'g_name' => 'owner',
        ]);
        $owner = User::factory()->create();
        $owner->assignRole($role);

        return $owner;
    }

    private function gym(): Gym
    {
        return Gym::query()->create([
            'name' => 'Main gym',
            'address' => 'Main street 1',
            'entry_code_type' => 'rfId',
            'trainer_salary_mode' => 'prepaid',
        ]);
    }
}
