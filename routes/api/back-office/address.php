<?php

use App\Http\Controllers\Api\Backoffice\AddressController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::apiResource('/', AddressController::class, ['parameters' => ['' => 'address']])
        ->middleware('permission:address');
});