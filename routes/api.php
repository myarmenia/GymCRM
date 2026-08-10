<?php

use App\Http\Controllers\Api\Turnstile\EntryExitSystemController;
use App\Http\Controllers\Api\Mobile\MobileAuthController;
use App\Http\Controllers\Api\Mobile\MobileProfileController;
use App\Http\Controllers\Api\Mobile\MobileGymController;
use App\Http\Controllers\Api\Mobile\MobileScheduleController;
use App\Http\Controllers\Api\Mobile\MobileMembershipController;
use App\Http\Controllers\Api\Mobile\MobileNotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



// ======================== turnstile Турникет ======================================
Route::group(['prefix' => 'turnstile'], function ($router) {

    Route::post('ees', EntryExitSystemController::class);  //  Entry/Exit System

});

Route::prefix('mobile/v1')->group(function () {
    Route::post('auth/login', [MobileAuthController::class, 'login'])
        ->middleware('throttle:6,1');

    Route::middleware(['auth:sanctum', 'mobile.person'])->group(function () {
        Route::post('auth/logout', [MobileAuthController::class, 'logout']);
        Route::get('me', [MobileAuthController::class, 'me']);
        Route::put('me', [MobileProfileController::class, 'update']);
        Route::delete('me', [MobileProfileController::class, 'deactivate']);
        Route::put('devices/fcm-token', [MobileAuthController::class, 'updateFcmToken']);
        Route::get('biometrics', [MobileProfileController::class, 'biometric']);
        Route::put('biometrics', [MobileProfileController::class, 'updateBiometric']);
        Route::get('gyms', [MobileGymController::class, 'index']);
        Route::get('schedule', [MobileScheduleController::class, 'index']);
        Route::get('memberships', [MobileMembershipController::class, 'index']);
        Route::get('memberships/{membership}', [MobileMembershipController::class, 'show'])
            ->whereNumber('membership');
        Route::get('notifications', [MobileNotificationController::class, 'index']);
        Route::patch('notifications/{notification}/read', [MobileNotificationController::class, 'markAsRead']);
    });
});
