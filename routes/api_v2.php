<?php

use App\Http\Controllers\Api\V2\BusinessOfferController;
use App\Http\Controllers\Api\V2\GuaranteeController;
use App\Http\Controllers\Api\V2\OfferComparisonController;
use App\Http\Controllers\Api\V2\OfferDiscoveryController;
use App\Http\Controllers\Api\V2\OfferFollowController;
use App\Http\Controllers\Api\V2\OfferTrackingController;
use App\Http\Controllers\Api\V2\SearchOffersController;
use Illuminate\Support\Facades\Route;

Route::prefix('v2')->group(function () {
    Route::prefix('offers')->group(function () {
        Route::get('/', [OfferDiscoveryController::class, 'index']);
        Route::get('lowest', [OfferDiscoveryController::class, 'lowestForOfferable']);
        Route::get('business/{business}', [OfferDiscoveryController::class, 'byBusiness'])->whereNumber('business');
        Route::post('{offer}/track', [OfferTrackingController::class, 'track'])->whereNumber('offer');
        Route::get('{offer}', [OfferDiscoveryController::class, 'show'])->whereNumber('offer');
    });

    Route::prefix('search')->group(function () {
        Route::get('offers', [SearchOffersController::class, 'index']);
        Route::get('business/{business}/offers', [SearchOffersController::class, 'business'])->whereNumber('business');
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

        Route::prefix('offer-follows')->group(function () {
            Route::get('/', [OfferFollowController::class, 'index']);
            Route::post('/', [OfferFollowController::class, 'store']);
            Route::delete('{follow}', [OfferFollowController::class, 'destroy'])->whereNumber('follow');
            Route::get('notifications', [OfferFollowController::class, 'notifications']);
            Route::post('notifications/{notification}/read', [OfferFollowController::class, 'markRead'])->whereNumber('notification');
        });

        Route::prefix('business/offers')->group(function () {
            Route::get('/', [BusinessOfferController::class, 'index']);
            Route::post('/', [BusinessOfferController::class, 'store']);
            Route::put('{offer}', [BusinessOfferController::class, 'update'])->whereNumber('offer');
            Route::patch('{offer}', [BusinessOfferController::class, 'update'])->whereNumber('offer');
            Route::post('{offer}/toggle', [BusinessOfferController::class, 'toggle'])->whereNumber('offer');
            Route::delete('{offer}', [BusinessOfferController::class, 'destroy'])->whereNumber('offer');
            Route::get('performance/me', [OfferTrackingController::class, 'myPerformance']);
        });
    });
});
