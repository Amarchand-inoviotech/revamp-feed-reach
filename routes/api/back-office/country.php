<?php

use App\Http\Controllers\Api\Backoffice\CountryController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::apiResource('/', CountryController::class, ['parameters' => ['' => 'country']])
        ->middleware('permission:country');
});