<?php

use App\Http\Controllers\Api\Backoffice\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:sanctum']], function () {

    Route::apiResource('/', SubscriptionController::class, ['parameters' => ['' => 'subscription']])
        ->middleware('permission:subscription');

    Route::post('cancel/{subscription}', [SubscriptionController::class, 'cancel'])
        ->name('cancel')
        ->middleware('permission:subscription');

    Route::post('sync/{subscription}', [SubscriptionController::class, 'sync'])
        ->name('sync')
        ->middleware('permission:subscription');

    Route::get('user-subscriptions', [SubscriptionController::class, 'userSubscriptions'])
        ->name('user-subscriptions')
        ->middleware('permission:subscription');

    Route::get('expiring', [SubscriptionController::class, 'expiring'])
        ->name('expiring')
        ->middleware('permission:subscription');
});

// Webhook routes (no authentication required)
Route::post('webhook/{gateway}', [SubscriptionController::class, 'webhook'])
    ->name('webhook');
