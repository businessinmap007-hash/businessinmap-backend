<?php

use App\Http\Controllers\Api\V2\GuaranteeController;
use App\Http\Controllers\Api\V2\OfferComparisonController;
use Illuminate\Support\Facades\Route;

Route::prefix('v2')->group(function () {
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
    });
});
