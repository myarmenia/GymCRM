<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Category\CategoryController;
use App\Http\Controllers\Discount\DiscountController;
use App\Http\Controllers\Documents\DocumentController;
use App\Http\Controllers\EntryCode\EntryCodeController;
use App\Http\Controllers\EntryReportController;
use App\Http\Controllers\Membership\MembershipPlanController;
use App\Http\Controllers\Membership\MembershipSaleController;
use App\Http\Controllers\Users\UserController;
use App\Http\Controllers\Gyms\GymController;
use App\Http\Controllers\MeasurementUnit\MeasurementUnitController;
use App\Http\Controllers\Membership\MembershipCategoryController;
use App\Http\Controllers\ProductConsumption\ProductConsumptionController;
use App\Http\Controllers\Partners\PartnerController;
use App\Http\Controllers\Products\ProductsController;
use App\Http\Controllers\Schedule\ScheduleController;
use App\Http\Controllers\People\PersonController;
use App\Http\Controllers\Purchase\PurchaseController;
use App\Http\Controllers\TableDeleteController;
use App\Http\Controllers\TableToggleController;
use App\Http\Controllers\Trainer\TrainerController;
use App\Http\Controllers\Warehouses\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::prefix('{locale}')
    ->where(['locale' => 'en|ru|hy']) // Здесь указываются допустимые значения для локали
    ->group(function () {

        Route::middleware('my_guest')->group(function () {
            Route::get('register', [RegisteredUserController::class, 'create'])
                ->name('register');

            Route::post('register', [RegisteredUserController::class, 'store']);

            Route::get('login', [AuthenticatedSessionController::class, 'create'])
                ->name('login');

            Route::post('login', [AuthenticatedSessionController::class, 'store']);

            Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
                ->name('password.request');

            Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
                ->name('password.email');

            Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
                ->name('password.reset');

            Route::post('reset-password', [NewPasswordController::class, 'store'])
                ->name('password.store');
        });
    });

Route::prefix('{locale}')
    ->where(['locale' => 'en|ru|hy']) // Здесь указываются допустимые значения для локали
    ->group(function () {

        Route::middleware('auth')->group(function () {
            Route::get('verify-email', EmailVerificationPromptController::class)
                ->name('verification.notice');

            Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
                ->middleware(['signed', 'throttle:6,1'])
                ->name('verification.verify');

            Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
                ->middleware('throttle:6,1')
                ->name('verification.send');

            Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
                ->name('password.confirm');

            Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

            Route::put('password', [PasswordController::class, 'update'])->name('password.update');

            Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
                ->name('logout');


            // ====== users ================
            Route::prefix('user')->name('user.')->group(function () {
                Route::get('/list', [UserController::class, 'list'])->name('list');
                Route::get('/create', [UserController::class, 'create'])->name('create');
                Route::post('/store', [UserController::class, 'store'])->name('store');

                Route::middleware('check.gym:User,id')->group(function () {
                    Route::get('/edit/{id}', [UserController::class, 'edit'])->name('edit');
                    Route::patch('/update/{id}', [UserController::class, 'update'])->name('update');
                    Route::get('/show/{id}', [UserController::class, 'show'])->name('show');
                });
            });

            // ====== people ================
            Route::prefix('person')->name('person.')->group(function () {
                Route::get('/list', [PersonController::class, 'list'])->name('list');
                Route::get('/create', [PersonController::class, 'create'])->name('create');
                Route::post('/store', [PersonController::class, 'store'])->name('store');

                // Route::middleware('check.gym:Person,id')->group(function () {
                Route::get('/edit/{id}', [PersonController::class, 'edit'])->name('edit');
                Route::patch('/update/{id}', [PersonController::class, 'update'])->name('update');
                // });
            });


            // ====== gym ================
            Route::prefix('gym')->name('gym.')->group(function () {
                Route::get('/list', [GymController::class, 'list'])->name('list');
                Route::get('/create', [GymController::class, 'create'])->name('create');
                Route::post('/store', [GymController::class, 'store'])->name('store');

                Route::middleware('check.gym:User,id')->group(function () {
                    Route::get('/edit/{id}', [GymController::class, 'edit'])->name('edit');
                    Route::patch('/update/{id}', [GymController::class, 'update'])->name('update');
                    Route::delete('/{model}/{id}', [TableDeleteController::class, 'destroyLocale']);
                });
            });

            // ====== Entry Code ================
            Route::prefix('entry-code')->name('entry-code.')->group(function () {
                Route::get('/list', [EntryCodeController::class, 'list'])->name('list');
                Route::get('/create', [EntryCodeController::class, 'create'])->name('create');
                Route::post('/store', [EntryCodeController::class, 'store'])->name('store');
                Route::get('/by-gym/{gymId}', [EntryCodeController::class, 'getByGym'])->name('by-gym');

                Route::middleware('check.gym:EntryCode,id')->group(function () {
                    Route::get('/edit/{id}', [EntryCodeController::class, 'edit'])->name('edit');
                    Route::patch('/update/{id}', [EntryCodeController::class, 'update'])->name('update');
                    Route::delete('/{model}/{id}', [TableDeleteController::class, 'destroyLocale']);

                    // Եթե ունեք active/inactive toggle (ըստ անհրաժեշտության)
                    Route::patch('{model}/{id}/toggle-active', [TableToggleController::class, 'toggleChangeLocale']);
                });
            });

            Route::prefix('entry-reports')->name('entry-reports.')->group(function () {
                Route::get('/', [EntryReportController::class, 'index'])->name('index');
                Route::get('/export', [EntryReportController::class, 'export'])->name('export');
                Route::get('/{entryReport}', [EntryReportController::class, 'show'])->name('show');
            });

            Route::prefix('partner')->name('partner.')->group(function () {
                Route::get('/list', [PartnerController::class, 'list'])->name('list');
                Route::get('/create', [PartnerController::class, 'create'])->name('create');
                Route::post('/store', [PartnerController::class, 'store'])->name('store');

                Route::middleware('check.gym:Partner,id')->group(function () {
                    Route::get('/edit/{id}', [PartnerController::class, 'edit'])->name('edit');
                    Route::patch('/update/{id}', [PartnerController::class, 'update'])->name('update');
                    Route::delete('/{model}/{id}', [TableDeleteController::class, 'destroyLocale']);

                    Route::patch('{model}/{id}/toggle-active', [TableToggleController::class, 'toggleChangeLocale']);
                });
            });

            // ====== Warehouse ================
            Route::prefix('warehouse')->name('warehouse.')->group(function () {
                Route::get('/list', [WarehouseController::class, 'list'])->name('list');
                Route::get('/create', [WarehouseController::class, 'create'])->name('create');
                Route::post('/store', [WarehouseController::class, 'store'])->name('store');

                Route::middleware('check.gym:Warehouse,id')->group(function () {
                    Route::get('/edit/{id}', [WarehouseController::class, 'edit'])->name('edit');
                    Route::patch('/update/{id}', [WarehouseController::class, 'update'])->name('update');
                    Route::delete('/{model}/{id}', [TableDeleteController::class, 'destroyLocale']);
                    Route::patch('{model}/{id}/toggle-active', [TableToggleController::class, 'toggleChangeLocale']);
                });
            });


            // ====== Membership Category ================
            Route::prefix('membership-category')->name('membership-category.')->group(function () {
                Route::get('/', [MembershipCategoryController::class, 'list'])->name('list');
                Route::get('/create', [MembershipCategoryController::class, 'create'])->name('create');
                Route::post('/', [MembershipCategoryController::class, 'store'])->name('store');

                Route::middleware('check.gym:MembershipCategory,id')->group(function () {
                    Route::get('/{id}/edit', [MembershipCategoryController::class, 'edit'])->name('edit');
                    Route::patch('/{id}', [MembershipCategoryController::class, 'update'])->name('update');
                    Route::delete('/{id}', [MembershipCategoryController::class, 'destroy'])->name('destroy');
                });
            });

            // ====== Discounts ================
            Route::prefix('discount')->name('discount.')->group(function () {
                Route::get('/', [DiscountController::class, 'list'])->name('list');
                Route::get('/create', [DiscountController::class, 'create'])->name('create');
                Route::post('/', [DiscountController::class, 'store'])->name('store');

                // Route::middleware('check.gym:Discount,id')->group(function () {
                Route::get('/{id}/edit', [DiscountController::class, 'edit'])->name('edit');
                Route::patch('/{id}', [DiscountController::class, 'update'])->name('update');
                Route::delete('/{model}/{id}', [TableDeleteController::class, 'destroyLocale']);
                Route::patch('/{model}/{id}/toggle-active', [TableToggleController::class, 'toggleChangeLocale']);
                // });
            });

            Route::prefix('categories')->name('categories.')->group(function () {
                Route::get('/', [CategoryController::class, 'index'])->name('index');
                Route::get('/create', [CategoryController::class, 'create'])->name('create');
                Route::post('/', [CategoryController::class, 'store'])->name('store');

                Route::middleware('check.gym:InventoryCategory,id')->group(function () {
                    Route::get('/edit/{id}', [CategoryController::class, 'edit'])->name('edit');
                    Route::put('/{id}', [CategoryController::class, 'update'])->name('update');
                    Route::delete('/{id}', [CategoryController::class, 'destroy'])->name('destroy');
                });
            });


            // ====== users ================
            Route::prefix('membership-plan')->name('membership_plan.')->group(function () {
                Route::get('/list', [MembershipPlanController::class, 'list'])->name('list');
                Route::get('/create', [MembershipPlanController::class, 'create'])->name('create');
                Route::post('/store', [MembershipPlanController::class, 'store'])->name('store');

                Route::middleware('check.gym:MembershipPlan,id')->group(function () {
                    Route::get('/edit/{id}', [MembershipPlanController::class, 'edit'])->name('edit');
                    Route::put('/update/{id}', [MembershipPlanController::class, 'update'])->name('update');
                });
            });

            Route::prefix('membership-sale')->name('membership_sale.')->group(function () {
                Route::get('/list', [MembershipSaleController::class, 'list'])->name('list');
                Route::get('/create/{person}', [MembershipSaleController::class, 'create'])->name('create');
                Route::post('/store/{person}', [MembershipSaleController::class, 'store'])->name('store');
                Route::middleware('check.gym:MembershipSale,id')->group(function () {
                    Route::get('/edit/{id}', [MembershipSaleController::class, 'edit'])->name('edit');
                    Route::get('/payments/{id}', [MembershipSaleController::class, 'payments'])->name('payments');
                    Route::post('/payments/{id}', [MembershipSaleController::class, 'storePayment'])->name('payments.store');
                    Route::post('/refunds/{id}', [MembershipSaleController::class, 'storeRefund'])->name('refunds.store');
                    Route::post('/cancel/{id}', [MembershipSaleController::class, 'cancel'])->name('cancel');
                    Route::patch('/update/{id}', [MembershipSaleController::class, 'update'])->name('update');
                    Route::delete('/{id}', [MembershipSaleController::class, 'destroy'])->name('destroy');
                });
            });


            Route::prefix('products')->name('products.')->group(function () {
                Route::get('/', [ProductsController::class, 'index'])->name('index');
                Route::get('/create', [ProductsController::class, 'create'])->name('create');
                Route::post('/', [ProductsController::class, 'store'])->name('store');

                Route::middleware('check.gym:InventoryProduct,id')->group(function () {
                    Route::get('/edit/{id}', [ProductsController::class, 'edit'])->name('edit');
                    Route::put('/{id}', [ProductsController::class, 'update'])->name('update');
                    Route::delete('/{id}', [ProductsController::class, 'destroy'])->name('destroy');
                });
            });

            Route::prefix('schedule')->name('schedule.')->group(function () {
                Route::get('/', [ScheduleController::class, 'index'])->name('index');
                Route::get('/create', [ScheduleController::class, 'create'])->name('create');
                Route::post('/', [ScheduleController::class, 'store'])->name('store');

                //Route::middleware('check.gym:ScheduleName,id')->group(function () {
                Route::get('/edit/{id}', [ScheduleController::class, 'edit'])->name('edit');
                Route::put('/{id}', [ScheduleController::class, 'update'])->name('update');
                Route::delete('/{id}', [ScheduleController::class, 'destroy'])->name('destroy');
                //});
            });

            Route::prefix('product-consumptions')->name('product-consumptions.')->group(function () {
                Route::get('/', [ProductConsumptionController::class, 'index'])->name('index');
                Route::get('/create', [ProductConsumptionController::class, 'create'])
                    ->name('create');
                Route::post('/', [ProductConsumptionController::class, 'store'])
                    ->name('store');
                Route::middleware('check.gym:InventoryProduct,id')->group(function () {
                    Route::get('/edit/{id}', [ProductConsumptionController::class, 'edit'])->name('edit');
                    Route::put('/{id}', [ProductConsumptionController::class, 'update'])->name('update');
                    Route::delete('/{id}', [ProductConsumptionController::class, 'destroy'])->name('destroy');
                });
            });

            Route::prefix('products')->name('products.')->group(function () {
                Route::get('/', [ProductsController::class, 'index'])->name('index');
                Route::get('/create', [ProductsController::class, 'create'])->name('create');
                Route::post('/', [ProductsController::class, 'store'])->name('store');

                Route::middleware('check.gym:InventoryProduct,id')->group(function () {
                    Route::get('/edit/{id}', [ProductsController::class, 'edit'])->name('edit');
                    Route::put('/{id}', [ProductsController::class, 'update'])->name('update');
                    Route::delete('/{id}', [ProductsController::class, 'destroy'])->name('destroy');
                });
            });

            Route::prefix('trainer')->name('trainer.')->group(function () {
                Route::get('/', [TrainerController::class, 'index'])->name('index');
                Route::get('/{id}/edit', [TrainerController::class, 'edit'])->name('edit');
                Route::post('/{id}', [TrainerController::class, 'store'])->name('store');
                Route::put('/{id}', [TrainerController::class, 'update'])->name('update');
            });


            Route::prefix('purchase')->name('purchase.')->group(function () {
                Route::get('/', [PurchaseController::class, 'index'])->name('index');
                Route::get('/history', [PurchaseController::class, 'history'])->name('history');
                Route::post('/sell', [PurchaseController::class, 'sell'])->name('sell');
            });
        });
    });

Route::middleware('auth')->group(function () {
    Route::prefix('tables')->group(function () {
        Route::patch('{model}/{id}/toggle-active', [TableToggleController::class, 'toggleChange']);
        Route::delete('{model}/{id}', [TableDeleteController::class, 'destroy']);
    });

    Route::prefix('documents')->group(function () {
        Route::get('{ownerType}/{ownerId}', [DocumentController::class, 'ownerDocuments']);
        Route::post('{ownerType}/{ownerId}', [DocumentController::class, 'store']);
        Route::delete('{document}', [DocumentController::class, 'destroy']);
    });
});
