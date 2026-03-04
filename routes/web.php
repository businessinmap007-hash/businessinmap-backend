<?php

use Illuminate\Http\Request;
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Active web UI routes only
|--------------------------------------------------------------------------
*/

// =====================
// Language
// =====================
Route::get('lang/{language}', [
    'as' => 'lang.switch',
    'uses' => 'App\Http\Controllers\LanguageController@switchLang'
]);

// =====================
// Home
// =====================
//Route::get('/', 'App\Http\Controllers\HomeController@index')->name('user.home');
//Route::get('/home', 'App\Http\Controllers\HomeController@index')->name('user.home');
Route::get('/', fn() => view('welcome_bim'))->name('landing');
Route::get('/home', fn() => redirect()->route('landing'));


// =====================
// Auth (Web)
// =====================
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

// =====================
// Static Pages
// =====================
Route::get("aboutus", "App\Http\Controllers\PageController@aboutUs")->name('aboutus');
Route::get("terms-and-conditions", "App\Http\Controllers\PageController@termsAndConditions")->name('terms');
Route::get("privacy-and-policy", "App\Http\Controllers\PageController@privacy")->name('privacy');

/*
// =====================
// Contact
// =====================
Route::get('contactus', "App\Http\Controllers\SupportController@index")->name('contactus');
Route::post('contactus', "App\Http\Controllers\SupportController@contactMessage")->name('contactus.post');
*/
// =====================
// Profile (Web)
// =====================
Route::get('/user/profile', 'App\Http\Controllers\ProfileController@profile')->name('profile');
Route::get('/user/addresses', 'App\Http\Controllers\ProfileController@userAddresses')->name('addresses');
Route::resource('addresses', 'App\Http\Controllers\AddressController');
Route::post(
    'addresses/update/primary',
    'App\Http\Controllers\AddressController@updatePrimaryAddress'
)->name('update.primary.address');
Route::post('/profile/update', 'App\Http\Controllers\ProfileController@profileUpdateUser')->name('profile.update');

// =====================
// Admin
// =====================
Auth::routes();

Route::prefix('administrator')->middleware(['auth:admin'])->group(function () {

    Route::get('/', [App\Http\Controllers\Admin\HomeController::class, 'index'])
        ->name('admin.dashboard');

    
    
    Route::resource('businesses', App\Http\Controllers\Admin\BusinessController::class);
});
/*
|--------------------------------------------------------------------------
| TEMP PLACEHOLDER ROUTES
|--------------------------------------------------------------------------
| Prevent RouteNotFoundException for legacy views
*/

        Route::get('/_disabled/category-products', function () {
            abort(404);
        })->name('category.products');

        Route::get('/_disabled/cart', function () {
            abort(404);
        })->name('cart');

/*
|--------------------------------------------------------------------------
| LEGACY / DISABLED ROUTES (Temporarily Disabled)
|--------------------------------------------------------------------------
| Reason:
| - Controllers moved to API
| - Controllers missing
| - Old Web logic
|--------------------------------------------------------------------------
*/

// =====================
// Cart (Legacy Web)
// =====================
/*
Route::get('shopping/cart', 'App\Http\Controllers\CartController@index')->name('cart');
Route::post('add/product/to/cart', "App\Http\Controllers\CartController@addToCart")->name('add.to.cart');
Route::post('delete/item/cart', 'App\Http\Controllers\CartController@deleteCartItem')->name('delete.item.cart');
Route::post('update/item/cart', 'App\Http\Controllers\CartController@updateCartQty')->name('update.item.cart');
*/

// =====================
// Search (Legacy Web)
// =====================
/*
Route::get('get/search', "App\Http\Controllers\SearchController@index")->name('get.search');
Route::post('search', "App\Http\Controllers\SearchController@searchPost")->name('search.post');
*/

// =====================
// Location (Legacy)
// =====================
/*
Route::post(
    '/get/all/cities/by/country',
    'App\Http\Controllers\LocationController@getCities'
)->name('get.all.selected.cities');
*/

// =====================
// Wishlist / Rate / Files (Legacy)
// =====================
/*
Route::post('add/to/wishlist', "App\Http\Controllers\WishlistController@addToWishList")->name('add.to.wishlist');
Route::post('rate/comments', "App\Http\Controllers\RateController@postRate")->name('rate.comments');
Route::post('upload/file', "App\Http\Controllers\FilesController@uploadFile")->name('upload.file');
*/

// =====================
// Misc Legacy
// =====================
/*
Route::get("order/payment", "App\Http\Controllers\OrdersController@orderPayment")->name('order.payment');
Route::get("ask/orders", "App\Http\Controllers\OrdsController@askOrder")->name('ask.order');
Route::get("faqs", "App\Http\Controllers\ListsController@faqs")->name('faqs');
Route::get("offers", "App\Http\Controllers\OfferController@index")->name('offers.index');
Route::get("offer/{id}/details", "App\Http\Controllers\OrdsController@offerDetails")->name('present.offer.details');
Route::get("ask/order/{id}/details", "App\Http\Controllers\OrdsController@orderDetails")->name('ask.order.details');
Route::resource('connections', 'App\Http\Controllers\ConnectionsController');
*/

// =====================
// Debug / Test helpers
// =====================
/*
Route::get('activationcode/{phone}', function ($phone) {
    $user = User::wherePhone($phone)->first();
    return $user
        ? "Activation Code Is: " . $user->action_code
        : "User Not Found";
});
*/
