<?php

use App\Http\Controllers\Api\Frontoffice\Guest\BusinessController;
use App\Http\Controllers\Api\Frontoffice\Guest\LeadController;
use App\Http\Controllers\Api\Frontoffice\Guest\PackageController;
use App\Http\Controllers\Api\Frontoffice\Guest\StateController;
use App\Http\Controllers\Api\Frontoffice\Guest\ServiceController;
use Illuminate\Support\Facades\Route;

Route::prefix('services')->group(function () {
    Route::get('/',[ServiceController::class, 'index'])->name('index');
});

Route::prefix('states')->group(function () {
    Route::get('/',[StateController::class, 'index'])->name('index');
});

Route::prefix('packages')->group(function () {
    Route::get('/',[PackageController::class, 'index'])->name('index');
});

Route::prefix('businesses')->group(callback: function () {
    Route::post('/send-email',[BusinessController::class, 'sendEmail'])->name('send-email');
    Route::post('/',[BusinessController::class, 'store'])->name('store');
});

Route::prefix('leads')->group(callback: function () {
    Route::post('/',[LeadController::class, 'store'])->name('store');
});



