<?php

use App\Http\Controllers\Api\Backoffice\CardController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::apiResource('/', CardController::class, ['parameters' => ['' => 'card']])
        ->middleware('permission:card');
});