<?php

use App\Http\Controllers\Api\Backoffice\PackageController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:sanctum']], function () {

    Route::post('restore/{package}', [PackageController::class, 'restore'])
        ->name('restore');

    Route::apiResource('/', PackageController::class, ['parameters' => ['' => 'package']]);
});
