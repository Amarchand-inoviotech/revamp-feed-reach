<?php

use App\Http\Controllers\Api\Backoffice\LeadController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::apiResource('/', LeadController::class, ['parameters' => ['' => 'lead']])
        ->middleware('permission:lead');
});