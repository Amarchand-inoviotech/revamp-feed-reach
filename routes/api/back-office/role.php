<?php

use App\Http\Controllers\Api\Backoffice\RoleController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::post('export-csv', [RoleController::class, 'exportCsv'])->name('export-csv');
    Route::put('/{role}/restore', [RoleController::class, 'restore'])->withTrashed();
    Route::delete('/{role}/permanently-destroy', [RoleController::class, 'permanentlyDestroy'])->name('permanently-destroy')->withTrashed();
    Route::delete('bulk-destroy', [RoleController::class, 'bulkDestroy'])->name('bulk-destroy');
    Route::apiResource('/', RoleController::class,  ['parameters' => ['' => 'role']]);
});
