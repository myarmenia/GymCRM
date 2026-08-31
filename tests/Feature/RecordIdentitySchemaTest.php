<?php

namespace Tests\Feature;

use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

class RecordIdentitySchemaTest extends TestCase
{
    use RefreshDatabase;

    private const RECORD_TABLES = [
        'gyms', 'users', 'langs', 'countries', 'country_translations', 'companies',
        'company_translations', 'payment_methods', 'payment_method_translations',
        'card_types', 'measurement_units', 'person_positions', 'people', 'documents',
        'partners', 'person_biometrics', 'entry_codes', 'entry_permissions', 'entry_reports',
        'attendance_sheets', 'turnstiles', 'gym_working_day_times', 'gym_languages',
        'notifications', 'inventory_categories', 'inventory_category_translations',
        'inventory_products', 'inventory_product_translations', 'warehouses',
        'warehouse_stocks', 'product_consumptions', 'purchases', 'purchase_items',
        'membership_categories', 'membership_category_translations', 'membership_plans',
        'membership_plan_translations', 'membership_plan_trainers', 'discounts',
        'discount_translations', 'membership_sales', 'membership_sale_discounts',
        'membership_plan_payments', 'person_memberships', 'person_membership_freezes',
        'guests', 'trainer_commissions', 'trainer_monthly_salaries',
        'salesperson_commissions', 'schedule_names', 'schedule_details',
        'trainer_schedules', 'trainer_session_durations',
        'trainer_session_duration_slots', 'hdm_configs', 'hdm_cashiers',
        'hdm_operations', 'hdm_operation_payments', 'salary_payouts',
        'salary_payout_items', 'salary_payout_refunds', 'salary_payout_refund_items',
        'salary_payable_assignments', 'salary_payable_transfers', 'financial_categories',
        'financial_transactions', 'reminder_categories', 'reminders',
        'reminder_recipients',
    ];

    private const IDENTITY_FREE_TABLES = [
        'card_type_payment_method', 'discount_membership_plan', 'gym_person',
        'gym_schedule', 'membership_plan_schedules', 'model_has_permissions',
        'model_has_roles', 'role_has_permissions', 'permissions', 'roles',
        'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
        'migrations', 'password_reset_tokens', 'sessions', 'mobile_notifications',
        'activity_logs',
    ];

    public function test_record_schema_and_models_implement_identity_contract(): void
    {
        $models = $this->modelsByTable();

        foreach (self::RECORD_TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing record table: {$table}");
            $this->assertTrue(
                Schema::hasColumns($table, ['uuid', 'version']),
                "Record table {$table} must have uuid and version columns",
            );
            $this->assertArrayHasKey($table, $models, "Record table {$table} must have a model");
            $this->assertContains(
                HasUuidAndVersion::class,
                class_uses_recursive($models[$table]),
                "{$models[$table]} must use HasUuidAndVersion",
            );
        }
    }

    public function test_pivot_and_local_tables_do_not_expose_record_identity(): void
    {
        foreach (self::IDENTITY_FREE_TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing identity-free table: {$table}");
            $this->assertFalse(Schema::hasColumn($table, 'version'), "{$table} must not have version");

            if ($table !== 'failed_jobs') {
                $this->assertFalse(Schema::hasColumn($table, 'uuid'), "{$table} must not have uuid");
            }
        }
    }

    /**
     * @return array<string, class-string<Model>>
     */
    private function modelsByTable(): array
    {
        $models = [];

        foreach (glob(app_path('Models/*.php')) ?: [] as $path) {
            $class = 'App\\Models\\'.pathinfo($path, PATHINFO_FILENAME);

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
                continue;
            }

            /** @var Model $model */
            $model = new $class;
            $models[$model->getTable()] = $class;
        }

        return $models;
    }
}
