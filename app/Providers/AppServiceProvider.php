<?php

namespace App\Providers;


use App\Interfaces\AttendanceSheets\AttendanceSheetInterface;
use App\Interfaces\CardTypes\CardTypeInterface;
use App\Interfaces\Category\CategoryInterface;
use App\Interfaces\CategoryTranslations\CategoryTranslationInterface;
use App\Interfaces\Documents\DocumentInterface;
use App\Interfaces\Discounts\DiscountInterface;
use App\Interfaces\EntryCodes\EntryCodeInterface;
use App\Interfaces\EntryReports\EntryReportInterface;
use App\Interfaces\Gyms\GymInterface;
use App\Interfaces\Memberships\MembershipCategoryInterface;
use App\Interfaces\Memberships\MembershipPlanInterface;
use App\Interfaces\MembershipPlanPayments\MembershipPlanPaymentInterface;
use App\Interfaces\MembershipSaleDiscounts\MembershipSaleDiscountInterface;
use App\Interfaces\MembershipSales\MembershipSaleInterface;
use App\Interfaces\GymSchedule\GymScheduleInterface;
use App\Interfaces\MeasurementUnit\MeasurementUnitInterface;
use App\Interfaces\MembershipPlanSchedule\MembershipPlanScheduleInterface;
use App\Interfaces\MembershipPlanTrainer\MembershipPlanTrainerInterface;
use App\Interfaces\Notifications\NotificationRepositoryInterface;
use App\Interfaces\Partners\PartnerInterface;
use App\Interfaces\PaymentMethods\PaymentMethodInterface;
use App\Interfaces\PersonMemberships\PersonMembershipInterface;
use App\Interfaces\ProductConsumption\ProductConsumptionInterface;
use App\Interfaces\Products\ProductsInterface;
use App\Interfaces\ProductTranslations\ProductTranslationInterface;
use App\Interfaces\Reports\MembershipSalesReportRepositoryInterface;
use App\Interfaces\Roles\RoleInterface;
use App\Interfaces\Schedule\ScheduleInterface;
use App\Interfaces\ScheduleDetails\ScheduleDetailsInterface;
use App\Interfaces\ScheduleName\ScheduleNameInterface;
use App\Interfaces\SalespersonCommissions\SalespersonCommissionInterface;
use App\Interfaces\SubCategory\SubCategoryInterface;
use App\Interfaces\SubCategoryTranslations\SubCategoryTranslationInterface;
use App\Interfaces\TrainerCommissions\TrainerCommissionInterface;
use App\Interfaces\Users\UserInterface;
use App\Interfaces\Warehouses\WarehouseInterface;
use App\Interfaces\WarehouseStock\WarehouseStockInterface;
use App\Interfaces\People\PersonInterface;
use App\Interfaces\Purchase\PurchaseInterface;
use App\Interfaces\PurchaseItem\PurchaseItemInterface;
use App\Interfaces\Trainer\TrainerInterface;
use App\Interfaces\TrainerSchedule\TrainerScheduleInterface;
use App\Interfaces\TrainerSessionDuration\TrainerSessionDurationInterface as TrainerSessionDurationTrainerSessionDurationInterface;
use App\Interfaces\TrainerSessionDurationSlot\TrainerSessionDurationSlotInterface;
use App\Interfaces\Turnstile\CheckEntryCodeInterface;
use App\Interfaces\Turnstile\ClientIdFromTurnstileInterface;
use App\Repositories\AttendanceSheets\AttendanceSheetsRepository;
use App\Repositories\CardTypes\CardTypeRepository;
use App\Repositories\Category\CategoryRepository;
use App\Repositories\CategoryTranslations\CategoryTranslationsRepository;
use App\Repositories\Documents\DocumentRepository;
use App\Repositories\Discounts\DiscountRepository;
use App\Repositories\EntryCodes\EntryCodeRepository;
use App\Repositories\EntryReports\EntryReportRepository;
use App\Repositories\Gyms\GymRepository;
use App\Repositories\Memberships\MembershipCategoryRepository;
use App\Repositories\Memberships\MembershipPlanRepository;
use App\Repositories\MembershipPlanPayments\MembershipPlanPaymentRepository;
use App\Repositories\MembershipSaleDiscounts\MembershipSaleDiscountRepository;
use App\Repositories\MembershipSales\MembershipSaleRepository;
use App\Repositories\GymSchedule\GymScheduleRepository;
use App\Repositories\MeasurementUnit\MeasurementUnitRepository;
use App\Repositories\MembershipPlanSchedule\MembershipPlanScheduleRepository;
use App\Repositories\MembershipPlanTrainer\MembershipPlanTrainerRepository;
use App\Repositories\Notifications\NotificationRepository;
use App\Repositories\Partners\PartnerRepository;
use App\Repositories\PaymentMethods\PaymentMethodRepository;
use App\Repositories\PersonMemberships\PersonMembershipRepository;
use App\Repositories\ProductConsumption\ProductConsumptionRepository;
use App\Repositories\Products\ProductsRepository;
use App\Repositories\ProductTranslations\ProductTranslationRepository;
use App\Repositories\Reports\MembershipSalesReportRepository;
use App\Repositories\Roles\RoleRepository;
use App\Repositories\Schedule\ScheduleRepository;
use App\Repositories\ScheduleDetails\ScheduleDetailsRepository;
use App\Repositories\ScheduleName\ScheduleNameRepository;
use App\Repositories\SalespersonCommissions\SalespersonCommissionRepository;
use App\Repositories\SubCategory\SubCategoryRepository;
use App\Repositories\SubCategoryTranslations\SubCategoryTranslationsRepository;
use App\Repositories\People\PersonRepository;
use App\Repositories\Purchase\PurchaseRepository;
use App\Repositories\PurchaseItem\PurchaseItemRepository;
use App\Repositories\Trainer\TrainerRepository;
use App\Repositories\TrainerCommissions\TrainerCommissionRepository;
use App\Repositories\TrainerSchedule\TrainerScheduleRepository;
use App\Repositories\TrainerSessionDuration\TrainerSessionDurationRepository;
use App\Repositories\TrainerSessionDurationSlot\TrainerSessionDurationSlotRepository;
use App\Repositories\Turnstile\TurnstileRepository;
use App\Repositories\Users\UserRepository;

use App\Repositories\Warehouses\WarehouseRepository;
use App\Repositories\WarehouseStock\WarehouseStockRepository;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {





        $this->app->bind(GymInterface::class, GymRepository::class);
        $this->app->bind(UserInterface::class, UserRepository::class);
        $this->app->bind(RoleInterface::class, RoleRepository::class);
        $this->app->bind(DocumentInterface::class, DocumentRepository::class);
        $this->app->bind(DiscountInterface::class, DiscountRepository::class);

        $this->app->bind(PaymentMethodInterface::class, PaymentMethodRepository::class);
        $this->app->bind(CardTypeInterface::class, CardTypeRepository::class);
        $this->app->bind(PartnerInterface::class, PartnerRepository::class);
        $this->app->bind(WarehouseInterface::class, WarehouseRepository::class);

        $this->app->bind(CategoryInterface::class, CategoryRepository::class);
        $this->app->bind(SubCategoryInterface::class, SubCategoryRepository::class);
        $this->app->bind(SubCategoryTranslationInterface::class, SubCategoryTranslationsRepository::class);
        $this->app->bind(CategoryTranslationInterface::class, CategoryTranslationsRepository::class);
        $this->app->bind(ProductsInterface::class, ProductsRepository::class);
        $this->app->bind(ProductTranslationInterface::class, ProductTranslationRepository::class);
        $this->app->bind(WarehouseStockInterface::class, WarehouseStockRepository::class);
        $this->app->bind(MeasurementUnitInterface::class, MeasurementUnitRepository::class);
        $this->app->bind(ProductConsumptionInterface::class, ProductConsumptionRepository::class);
        $this->app->bind(ScheduleInterface::class, ScheduleRepository::class);
        $this->app->bind(GymScheduleInterface::class, GymScheduleRepository::class);
        $this->app->bind(ScheduleNameInterface::class, ScheduleNameRepository::class);
        $this->app->bind(ScheduleDetailsInterface::class, ScheduleDetailsRepository::class);
        $this->app->bind(TrainerScheduleInterface::class, TrainerScheduleRepository::class);
        $this->app->bind(EntryCodeInterface::class, EntryCodeRepository::class);
        $this->app->bind(AttendanceSheetInterface::class, AttendanceSheetsRepository::class);
        $this->app->bind(ClientIdFromTurnstileInterface::class, TurnstileRepository::class);
        $this->app->bind(CheckEntryCodeInterface::class, TurnstileRepository::class);
        $this->app->bind(PersonInterface::class, PersonRepository::class);

        $this->app->bind(MembershipPlanInterface::class, MembershipPlanRepository::class);
        $this->app->bind(MembershipCategoryInterface::class, MembershipCategoryRepository::class);
        $this->app->bind(MembershipSaleInterface::class, MembershipSaleRepository::class);
        $this->app->bind(PersonMembershipInterface::class, PersonMembershipRepository::class);
        $this->app->bind(MembershipSaleDiscountInterface::class, MembershipSaleDiscountRepository::class);
        $this->app->bind(MembershipPlanPaymentInterface::class, MembershipPlanPaymentRepository::class);
        $this->app->bind(TrainerCommissionInterface::class, TrainerCommissionRepository::class);
        $this->app->bind(SalespersonCommissionInterface::class, SalespersonCommissionRepository::class);
        $this->app->bind(TrainerInterface::class, TrainerRepository::class);

        $this->app->bind(EntryReportInterface::class, EntryReportRepository::class);

        $this->app->bind(NotificationRepositoryInterface::class, NotificationRepository::class);
        $this->app->bind(MembershipSalesReportRepositoryInterface::class, MembershipSalesReportRepository::class);


        $this->app->bind(TrainerSessionDurationTrainerSessionDurationInterface::class, TrainerSessionDurationRepository::class);

        $this->app->bind(TrainerSessionDurationSlotInterface::class, TrainerSessionDurationSlotRepository::class);

        $this->app->bind(MembershipPlanTrainerInterface::class, MembershipPlanTrainerRepository::class);
        $this->app->bind(MembershipPlanScheduleInterface::class, MembershipPlanScheduleRepository::class);

        $this->app->bind(PurchaseInterface::class, PurchaseRepository::class);
        $this->app->bind(PurchaseItemInterface::class, PurchaseItemRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Inertia::share([
            'locale' => fn() => Session::get('locale', config('app.locale')),
        ]);


        if (is_dir(base_path('lang'))) {
            $this->app->useLangPath(base_path('lang'));
        }
    }
}
