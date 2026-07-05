<?php

use App\Http\Controllers\Business\Auth\LoginController;
use App\Http\Controllers\Business\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Business Owner Panel
|--------------------------------------------------------------------------
| A scoped "mini admin" panel for business owners (type=business). Every
| screen behind business.panel is scoped to the logged-in owner's own
| business_id, so owners only ever see their own units, prices and bookings.
*/

Route::prefix('business')->name('business.')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->name('login.post');
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    Route::middleware(['business.panel'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    });
});
