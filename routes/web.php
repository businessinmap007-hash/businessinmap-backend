<?php

use Illuminate\Http\Request;
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminV2\CategoryServiceBulkController;
use App\Http\Controllers\AdminV2\OfferBoostPackageController;
use App\Http\Controllers\AdminV2\WalletOverviewController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Active web UI routes only
|--------------------------------------------------------------------------
*/

Route::get('lang/{language}', [
    'as' => 'lang.switch',
    'uses' => 'App\Http\Controllers\LanguageController@switchLang'
]);

Route::get('/', fn() => view('welcome_bim'))->name('landing');
Route::get('/home', fn() => redirect()->route('landing'));

Route::get('/user/login', 'App\Http\Controllers\LoginController@showLogin')->name('get.user.login');
Route::get('/user/register', 'App\Http\Controllers\RegistrationController@showRegister')->name('get.user.register');
Route::post('/user/auth/login', 'App\Http\Controllers\LoginController@login')->name('user.login');
Route::get('user/auth/logout', 'App\Http\Controllers\LoginController@logout')->name('user.auth.logout');
Route::post('user/signup', 'App\Http\Controllers\RegistrationController@signup')->name('user.signup');
Route::post('user/col/check', 'App\Http\Controllers\RegistrationController@checkIsColExist')->name('user.col.check');
Route::post('forgot/password', 'App\Http\Controllers\ForgotPasswordController@sendCode')->name('user.forgot.password');
Route::post('check/reset/code', 'App\Http\Controllers\ResetPasswordController@checkCode')->name('check.reset.code');
Route::post('reset/password', 'App\Http\Controllers\ResetPasswordController@reset')->name('reset.password');
Route::post('resend/activation/password', 'App\Http\Controllers\ResetPasswordController@resendActivationCode')->name('resend.activation.code');

Route::get("aboutus", "App\Http\Controllers\PageController@aboutUs")->name('aboutus');
Route::get("terms-and-conditions", "App\Http\Controllers\PageController@termsAndConditions")->name('terms');
Route::get("privacy-and-policy", "App\Http\Controllers\PageController@privacy")->name('privacy');

Route::get('/user/profile', 'App\Http\Controllers\ProfileController@profile')->name('profile');
Route::get('/user/addresses', 'App\Http\Controllers\ProfileController@userAddresses')->name('addresses');
Route::resource('addresses', 'App\Http\Controllers\AddressController');
Route::post('addresses/update/primary', 'App\Http\Controllers\AddressController@updatePrimaryAddress')->name('update.primary.address');
Route::post('/profile/update', 'App\Http\Controllers\ProfileController@profileUpdateUser')->name('profile.update');

Auth::routes();

Route::prefix('administrator')->middleware(['auth:admin'])->group(function () {
    Route::get('/', [App\Http\Controllers\Admin\HomeController::class, 'index'])->name('admin.dashboard');
    Route::resource('businesses', App\Http\Controllers\Admin\BusinessController::class);
});

Route::prefix('admin')->middleware(['web'])->group(function () {
    Route::post('category-services-bulk/apply', [CategoryServiceBulkController::class, 'apply'])->name('admin.category-services-bulk.apply');
});

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['web', 'admin.v2'])
    ->group(function () {
        Route::get('wallet-overview', [WalletOverviewController::class, 'index'])->name('wallet-overview.index');

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

Route::get('/_disabled/category-products', function () {
    abort(404);
})->name('category.products');

Route::get('/_disabled/cart', function () {
    abort(404);
})->name('cart');
