<?php

use App\Http\Controllers\Api\Backoffice\StatusController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::delete('bulk-destroy', [StatusController::class, 'bulkDestroy'])->name('bulk-destroy');
    Route::delete('/{status}/permanently-destroy', [StatusController::class, 'permanentlyDestroy'])->name('permanently-destroy')->withTrashed();
    Route::post('export-csv', [StatusController::class, 'exportCsv'])->name('export-csv');
    Route::put('/{status}/restore', [StatusController::class, 'restore'])->withTrashed();
    Route::apiResource('/', StatusController::class,  ['parameters' => ['' => 'status']]);
});
