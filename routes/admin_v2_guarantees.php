<?php

use App\Http\Controllers\AdminV2\GuaranteeAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['admin.v2'])
    ->group(function () {
        Route::prefix('guarantees')->name('guarantees.')->group(function () {
            Route::get('/', [GuaranteeAdminController::class, 'index'])
                ->name('index');

            Route::get('{guarantee}', [GuaranteeAdminController::class, 'show'])
                ->whereNumber('guarantee')
                ->name('show');
        });
    });
