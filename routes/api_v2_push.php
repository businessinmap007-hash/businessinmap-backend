<?php

use App\Http\Controllers\Api\V2\DeviceTokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('v2')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::post('push-token', [DeviceTokenController::class, 'register']);
    });
