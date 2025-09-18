<?php

use App\Http\Controllers\Api\Backoffice\ServiceController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::apiResource('/', ServiceController::class, ['parameters' => ['' => 'service']])
        ->middleware('permission:service');
});