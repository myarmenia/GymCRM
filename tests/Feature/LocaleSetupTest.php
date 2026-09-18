<?php

namespace Tests\Feature;

use App\Models\Gym;
use App\Models\Lang;
use App\Models\FinancialCategory;
use App\Models\MeasurementUnit;
use App\Models\MembershipCategory;
use App\Models\ReminderCategory;
use App\Models\User;
use Database\Seeders\GymSeeder;
use Database\Seeders\LangSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocaleSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_uses_the_url_locale_for_laravel_and_inertia(): void
    {
        $this->withSession(['locale' => 'en'])->get('/ru/login')->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('locale', 'ru')
            ->where('lang', 'ru')
            ->where('langs', ['hy', 'en', 'ru'])
            ->where('translations.app.sidebar.profile', 'Профиль')
            ->where('translations.app.sidebar.users', 'Пользователи')
            ->where('translations.app.auth.login', 'Вход')
            ->where('translations.app.confirm.title', 'Подтвердите действие')
            ->where('translations.app.people.clients', 'Клиенты')
            ->where('translations.app.ui.gyms_list', 'Список спортзалов')
            ->etc());
        $this->assertSame('ru', app()->getLocale());
        $this->assertSame('/ru/forgot-password', route('password.request', absolute: false));

        $this->get('/en/login')->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('locale', 'en')
            ->where('lang', 'en')
            ->where('translations.app.sidebar.profile', 'Profile')
            ->where('translations.app.sidebar.users', 'Users')
            ->where('translations.app.auth.login', 'Log in')
            ->where('translations.app.confirm.title', 'Confirm action')
            ->where('translations.app.people.clients', 'Clients')
            ->where('translations.app.ui.gyms_list', 'Gyms list')
            ->etc());

        $this->get('/hy/login')->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('translations.app.people.clients', 'Անձինք')
            ->etc());
    }

    public function test_root_redirect_uses_the_saved_locale(): void
    {
        $this->withSession(['locale' => 'ru'])->get('/')->assertRedirect('/ru/login');
    }

    public function test_login_error_uses_the_selected_language(): void
    {
        $this->post('/ru/login', [
            'email' => 'missing@example.com',
            'password' => 'incorrect-password',
        ])->assertSessionHasErrors([
            'email' => 'Неверный адрес электронной почты или пароль.',
        ]);
    }

    public function test_backend_messages_and_placeholders_use_the_selected_language(): void
    {
        app()->setLocale('en');
        $this->assertSame('The product was not found.', __('backend_messages.product_not_found'));
        $this->assertSame(
            'Payment #17 was recorded successfully.',
            __('backend_messages.payment_recorded_successfully', ['id' => 17]),
        );
        $this->assertSame(
            'Only an active membership can be frozen.',
            __('backend.membership_sales.freeze_active_only'),
        );

        app()->setLocale('ru');
        $this->assertSame('Товар не найден.', __('backend_messages.product_not_found'));
        $this->assertSame(
            'Платёж №17 успешно зарегистрирован.',
            __('backend_messages.payment_recorded_successfully', ['id' => 17]),
        );
        $this->assertSame('Поле email обязательно.', __('validation.required', ['attribute' => 'email']));
        $this->assertSame(
            'Заморозить можно только активный абонемент.',
            __('backend.membership_sales.freeze_active_only'),
        );

        app()->setLocale('hy');
        $this->assertSame('Ապրանքը գտնված չէ։', __('backend_messages.product_not_found'));
    }

    public function test_system_database_labels_are_localized_without_data_migrations(): void
    {
        $category = new FinancialCategory(['code' => 'product_sale', 'name' => 'Ապրանքի վաճառք']);
        $reminder = new ReminderCategory(['slug' => 'general', 'name' => 'Ընդհանուր հիշեցում']);
        $unit = new MeasurementUnit(['code' => 'pcs', 'name' => 'Հատով']);

        app()->setLocale('en');
        $this->assertSame('Product sale', $category->name);
        $this->assertSame('General reminder', $reminder->name);
        $this->assertSame('Pieces', $unit->name);

        app()->setLocale('ru');
        $this->assertSame('Продажа товара', $category->name);
        $this->assertSame('Общее напоминание', $reminder->name);
        $this->assertSame('Штуки', $unit->name);
    }

    public function test_authenticated_page_uses_the_url_locale(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/en/dashboard')->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('locale', 'en')
            ->where('lang', 'en')
            ->where('translations.app.sidebar.profile', 'Profile')
            ->etc());
    }

    public function test_membership_category_edit_keeps_ui_and_record_translations_separate(): void
    {
        config()->set('sync.enabled', false);
        $this->seed(LangSeeder::class);

        $gym = Gym::query()->create(['name' => 'Main gym']);
        $user = User::factory()->create(['gym_id' => $gym->id]);
        $category = MembershipCategory::query()->create([
            'gym_id' => $gym->id,
            'slug' => 'group-training',
            'active' => true,
        ]);
        $category->translations()->createMany([
            ['locale' => 'hy', 'name' => 'Խմբային', 'description' => 'Հայերեն'],
            ['locale' => 'en', 'name' => 'Group training', 'description' => 'English'],
            ['locale' => 'ru', 'name' => 'Групповые', 'description' => 'Русский'],
        ]);

        $this->actingAs($user)
            ->get(route('membership-category.edit', [
                'locale' => 'en',
                'id' => $category->id,
            ], absolute: false))
            ->assertInertia(fn (Assert $page) => $page
                ->component('MembershipCategory/Edit')
                ->where('translations.app.membership.category_edit', 'Edit category')
                ->where('categoryTranslations.hy.name', 'Խմբային')
                ->where('categoryTranslations.en.name', 'Group training')
                ->where('categoryTranslations.ru.name', 'Групповые')
                ->where('langs', ['hy', 'ru', 'en'])
                ->etc());
    }

    public function test_authenticated_page_shares_only_active_gym_languages(): void
    {
        $this->seed(LangSeeder::class);

        $gym = Gym::query()->create(['name' => 'Main gym']);
        $englishId = Lang::query()->where('code', 'en')->value('id');
        $gym->languages()->updateExistingPivot($englishId, ['active' => false]);
        $user = User::factory()->create(['gym_id' => $gym->id]);

        $this->actingAs($user)->get('/ru/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'ru')
            ->where('lang', 'ru')
            ->where('langs', ['hy', 'ru'])
            ->etc());
    }

    public function test_logout_keeps_the_selected_language(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/ru/logout')->assertRedirect('/ru/login');
        $this->assertGuest();
    }

    public function test_new_gyms_have_all_three_active_languages(): void
    {
        $this->seed(LangSeeder::class);

        $gym = Gym::query()->create(['name' => 'Main gym']);
        $this->assertEqualsCanonicalizing(['hy', 'en', 'ru'], $gym->languages()
            ->wherePivot('active', true)->pluck('code')->all());
        $this->assertSame(3, $gym->languages()->count());
    }

    public function test_seeded_gym_has_all_three_active_languages_without_model_events(): void
    {
        $this->seed(LangSeeder::class);

        Model::withoutEvents(fn () => $this->seed(GymSeeder::class));

        $gym = Gym::query()->where('name', 'Default Gym')->firstOrFail();
        $this->assertSame(3, $gym->languages()->wherePivot('active', true)->count());
    }
}
