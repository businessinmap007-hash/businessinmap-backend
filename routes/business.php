<?php

use App\Http\Controllers\Business\Auth\LoginController;
use App\Http\Controllers\Business\BookableItemController;
use App\Http\Controllers\Business\BookingController;
use App\Http\Controllers\Business\BusinessServicePriceController;
use App\Http\Controllers\Business\DashboardController;
use App\Http\Controllers\Business\MenuItemController;
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

        Route::get('bookable-items', [BookableItemController::class, 'index'])->name('bookable-items.index');
        Route::get('bookable-items/create', [BookableItemController::class, 'create'])->name('bookable-items.create');
        Route::post('bookable-items', [BookableItemController::class, 'store'])->name('bookable-items.store');
        Route::get('bookable-items/{id}/edit', [BookableItemController::class, 'edit'])->whereNumber('id')->name('bookable-items.edit');
        Route::put('bookable-items/{id}', [BookableItemController::class, 'update'])->whereNumber('id')->name('bookable-items.update');
        Route::delete('bookable-items/{id}', [BookableItemController::class, 'destroy'])->whereNumber('id')->name('bookable-items.destroy');

        Route::get('prices', [BusinessServicePriceController::class, 'index'])->name('prices.index');
        Route::get('prices/create', [BusinessServicePriceController::class, 'create'])->name('prices.create');
        Route::post('prices', [BusinessServicePriceController::class, 'store'])->name('prices.store');
        Route::get('prices/{id}/edit', [BusinessServicePriceController::class, 'edit'])->whereNumber('id')->name('prices.edit');
        Route::put('prices/{id}', [BusinessServicePriceController::class, 'update'])->whereNumber('id')->name('prices.update');
        Route::delete('prices/{id}', [BusinessServicePriceController::class, 'destroy'])->whereNumber('id')->name('prices.destroy');

        Route::get('menu', [MenuItemController::class, 'index'])->name('menu.index');
        Route::get('menu/create', [MenuItemController::class, 'create'])->name('menu.create');
        Route::post('menu', [MenuItemController::class, 'store'])->name('menu.store');
        Route::get('menu/{id}/edit', [MenuItemController::class, 'edit'])->whereNumber('id')->name('menu.edit');
        Route::put('menu/{id}', [MenuItemController::class, 'update'])->whereNumber('id')->name('menu.update');
        Route::delete('menu/{id}', [MenuItemController::class, 'destroy'])->whereNumber('id')->name('menu.destroy');

        Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
        Route::get('bookings/{id}', [BookingController::class, 'show'])->whereNumber('id')->name('bookings.show');
        Route::post('bookings/{id}/food', [BookingController::class, 'addFood'])->whereNumber('id')->name('bookings.food.add');
        Route::delete('bookings/{id}/food', [BookingController::class, 'removeFood'])->whereNumber('id')->name('bookings.food.remove');
    });
});
