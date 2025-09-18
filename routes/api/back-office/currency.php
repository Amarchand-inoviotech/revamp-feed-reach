<?php

use App\Http\Controllers\Api\Backoffice\CurrencyController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::put('/{currency}/restore', [CurrencyController::class, 'restore'])->name('restore')->withTrashed();
    Route::delete('/{currency}/permanently-destroy', [CurrencyController::class, 'permanentlyDestroy'])->name('permanently-destroy')->withTrashed();
    Route::delete('bulk-destroy', [CurrencyController::class, 'bulkDestroy'])->name('bulk-destroy');
    Route::post('export-csv', [CurrencyController::class, 'exportCsv'])->name('export-csv');
    Route::apiResource('/', CurrencyController::class,  ['parameters' => ['' => 'currency']])->only('index');
});
