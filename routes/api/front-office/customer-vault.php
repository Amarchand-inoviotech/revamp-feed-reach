<?php

use App\Http\Controllers\Api\Frontoffice\CustomerVaultController;
use App\Http\Middleware\UserGaurdMiddleware;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:sanctum', UserGaurdMiddleware::class]], function () {

    // Get user's cards
    Route::get('cards', [CustomerVaultController::class, 'getCards'])
        ->name('cards');

    // Get user's customers
    Route::get('customers', [CustomerVaultController::class, 'getCustomers'])
        ->name('customers');

    // Create a new card
    Route::post('cards', [CustomerVaultController::class, 'createCard'])
        ->name('create-card');

    // Delete a card
    Route::delete('cards', [CustomerVaultController::class, 'deleteCard'])
        ->name('delete-card');

});
