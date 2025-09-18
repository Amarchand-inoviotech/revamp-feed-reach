<?php

use App\Http\Controllers\Api\Frontoffice\AuthController;
use App\Http\Middleware\UserGaurdMiddleware;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, "login"]);

Route::group(['middleware' => ['auth:sanctum', UserGaurdMiddleware::class]], function () {
    Route::post('create', [AuthController::class, "create"]);
    Route::get('logout', [AuthController::class, "logout"]);
    Route::get('profile', [AuthController::class, "profile"]);
    Route::delete('revoke-token/{token}', [AuthController::class, "revokeToken"]);
});
