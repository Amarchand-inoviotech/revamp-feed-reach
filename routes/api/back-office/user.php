<?php

use App\Http\Controllers\Api\Backoffice\UserController;
use Illuminate\Support\Facades\Route;;


Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::put('/{user}/restore', [UserController::class, 'restore'])->name('restore')->withTrashed();
    Route::post('export-csv', [UserController::class, 'exportCsv'])->name('export-csv');
    Route::put('{user}/direct-permissions', [UserController::class, 'syncDirectPermissions'])->name('sync-direct-permissions');
    Route::delete('{user}/permanently-destroy', [UserController::class, 'permanentlyDestroy'])->name('permanently-destroy')->withTrashed();
    Route::delete('bulk-destroy', [UserController::class, 'bulkDestroy'])->name('bulk-destroy');
    Route::apiResource('/', UserController::class,  ['parameters' => ['' => 'user']]);
});
