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
use App\Http\Controllers\Documents\DocumentController;
use App\Http\Controllers\Users\UserController;
use App\Http\Controllers\Gyms\GymController;
use App\Http\Controllers\Partners\PartnerController;
use App\Http\Controllers\TableDeleteController;
use App\Http\Controllers\TableToggleController;
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
                });
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
