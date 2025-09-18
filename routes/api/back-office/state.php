<?php

use App\Http\Controllers\Api\Backoffice\StateController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::apiResource('/', StateController::class, ['parameters' => ['' => 'state']])
        ->middleware('permission:state');
});