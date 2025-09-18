<?php

use App\Http\Controllers\Api\Frontoffice\SubscriptionController;
use App\Http\Middleware\UserGaurdMiddleware;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:sanctum', UserGaurdMiddleware::class]], function () {

    // Subscribe to a package
    Route::post('/', [SubscriptionController::class, 'store'])
        ->name('store');

    // Update subscription plan (change package)
    Route::put('{subscription}', [SubscriptionController::class, 'update'])
        ->name('update');

    // Update subscription card/payment method
    Route::patch('{subscription}/card', [SubscriptionController::class, 'updateCard'])
        ->name('update-card');

    // Cancel subscription
    Route::delete('{subscription}', [SubscriptionController::class, 'destroy'])
        ->name('cancel');

    // Additional subscription routes
    Route::get('my-subscriptions', [SubscriptionController::class, 'mySubscriptions'])
        ->name('my-subscriptions');

    Route::get('{subscription}', [SubscriptionController::class, 'show'])
        ->name('show');

    Route::post('sync/{subscription}', [SubscriptionController::class, 'sync'])
        ->name('sync');

    // Custom subscription management routes
    Route::post('custom', [SubscriptionController::class, 'createCustomSubscription'])
        ->name('custom.create');

    Route::put('custom/{subscription}/card', [SubscriptionController::class, 'updateSubscriptionCard'])
        ->name('custom.update.card');

    Route::put('custom/{subscription}', [SubscriptionController::class, 'updateCustomSubscription'])
        ->name('update.custom');

    Route::put('manual-charge/{subscription}', [SubscriptionController::class, 'manualCharge'])
        ->name('manual.charge');

});
