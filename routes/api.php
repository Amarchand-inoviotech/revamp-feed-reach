<?php

use App\Http\Controllers\Api\Backoffice\NMIController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GenericController;
use App\Http\Middleware\BrandAccessMiddleware;

Route::get('/', [GenericController::class, 'apiPrefixRoute']);

Route::group(['prefix' => 'v1', 'middleware' => [BrandAccessMiddleware::class]], function () {
    Route::group(['prefix' => 'brands'], function () {
    });
});
Route::group(['prefix' => 'v1/back-offices'], function () {
     Route::any('nmi/webhook', [NMIController::class, 'webhook'])->name('nmi.webhook');

});

