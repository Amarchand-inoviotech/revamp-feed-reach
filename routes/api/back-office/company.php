<?php

use App\Http\Controllers\Api\Backoffice\CompanyController;
use Illuminate\Support\Facades\Route;;



Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::put('/{company}/restore', [CompanyController::class, 'restore'])->name('restore')->withTrashed();
    Route::delete('/{company}/permanently-destroy', [CompanyController::class, 'permanentlyDestroy'])->name('permanently-destroy')->withTrashed();
    Route::delete('bulk-destroy', [CompanyController::class, 'bulkDestroy'])->name('bulk-destroy');
    Route::post('export-csv', [CompanyController::class, 'exportCsv'])->name('export-csv');
    Route::apiResource('/', CompanyController::class, ['parameters' => ['' => 'company']]);
});
