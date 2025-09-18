<?php

use App\Http\Controllers\Api\Backoffice\PermissionController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::put('/{permission}/restore', [PermissionController::class, 'restore'])->name('restore')->withTrashed();
    Route::delete('/{permission}/permanently-destroy', [PermissionController::class, 'permanentlyDestroy'])->name('permanently-destroy')->withTrashed();
    Route::delete('bulk-destroy', [PermissionController::class, 'bulkDestroy'])->name('bulk-destroy');
    Route::post('export-csv', [PermissionController::class, 'exportCsv'])->name('export-csv');
    Route::get('/', [PermissionController::class, 'index'])->name('index');
});
