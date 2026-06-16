<?php

use App\Http\Controllers\Api\Turnstile\EntryExitSystemController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



// ======================== turnstile Турникет ======================================
Route::group(['prefix' => 'turnstile'], function ($router) {

    Route::post('ees', EntryExitSystemController::class);  //  Entry/Exit System

});
