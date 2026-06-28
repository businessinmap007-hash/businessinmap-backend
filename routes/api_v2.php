<?php

use App\Http\Controllers\Api\V2\BusinessOfferController;
use App\Http\Controllers\Api\V2\GuaranteeController;
use App\Http\Controllers\Api\V2\OfferComparisonController;
use App\Http\Controllers\Api\V2\OfferDiscoveryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v2')->group(function () {
    Route::prefix('offers')->group(function () {
        Route::get('/', [OfferDiscoveryController::class, 'index']);
        Route::get('lowest', [OfferDiscoveryController::class, 'lowestForOfferable']);
        Route::get('business/{business}', [OfferDiscoveryController::class, 'byBusiness'])->whereNumber('business');
        Route::get('{offer}', [OfferDiscoveryController::class, 'show'])->whereNumber('offer');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('guarantees')->group(function () {
            Route::get('levels', [GuaranteeController::class, 'levels']);
            Route::get('me', [GuaranteeController::class, 'me']);
            Route::get('transactions', [GuaranteeController::class, 'transactions']);
            Route::post('activate', [GuaranteeController::class, 'activate']);
            Route::post('check-operation', [GuaranteeController::class, 'checkOperationCoverage']);
        });

        Route::prefix('offers')->group(function () {
            Route::get('compare', [OfferComparisonController::class, 'compare']);
            Route::post('compare', [OfferComparisonController::class, 'compare']);
        });

        Route::prefix('business/offers')->group(function () {
            Route::get('/', [BusinessOfferController::class, 'index']);
            Route::post('/', [BusinessOfferController::class, 'store']);
            Route::put('{offer}', [BusinessOfferController::class, 'update'])->whereNumber('offer');
            Route::patch('{offer}', [BusinessOfferController::class, 'update'])->whereNumber('offer');
            Route::post('{offer}/toggle', [BusinessOfferController::class, 'toggle'])->whereNumber('offer');
            Route::delete('{offer}', [BusinessOfferController::class, 'destroy'])->whereNumber('offer');
        });
    });
});
