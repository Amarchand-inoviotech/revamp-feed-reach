<?php

use App\Http\Controllers\Api\Backoffice\BusinessController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::apiResource('/', BusinessController::class, ['parameters' => ['' => 'business']])
        ->middleware('permission:business');
});