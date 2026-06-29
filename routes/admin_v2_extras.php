<?php

use App\Http\Controllers\AdminV2\OfferBoostPackageController;
use App\Http\Controllers\AdminV2\OfferFollowDashboardController;
use App\Http\Controllers\AdminV2\WalletOverviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['admin.v2'])
    ->group(function () {
        Route::get('wallet-overview', [WalletOverviewController::class, 'index'])->name('wallet-overview.index');
        Route::get('offer-follows', [OfferFollowDashboardController::class, 'index'])->name('offer-follows.index');

        Route::prefix('offer-boost-packages')->name('offer-boost-packages.')->group(function () {
            Route::get('/', [OfferBoostPackageController::class, 'index'])->name('index');
            Route::get('create', [OfferBoostPackageController::class, 'create'])->name('create');
            Route::post('/', [OfferBoostPackageController::class, 'store'])->name('store');
            Route::get('boost', [OfferBoostPackageController::class, 'boostForm'])->name('boost-form');
            Route::post('boost', [OfferBoostPackageController::class, 'activateBoost'])->name('activate');
            Route::get('{offerBoostPackage}/edit', [OfferBoostPackageController::class, 'edit'])->whereNumber('offerBoostPackage')->name('edit');
            Route::put('{offerBoostPackage}', [OfferBoostPackageController::class, 'update'])->whereNumber('offerBoostPackage')->name('update');
            Route::post('{offerBoostPackage}/toggle', [OfferBoostPackageController::class, 'toggle'])->whereNumber('offerBoostPackage')->name('toggle');
        });
    });
