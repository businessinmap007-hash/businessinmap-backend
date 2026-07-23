<?php

use Illuminate\Http\Request;
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminV2\CategoryServiceBulkController;

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
Route::post('user/signup', 'App\Http\Controllers\RegistrationController@signup')->middleware('throttle:auth-attempts')->name('user.signup');
Route::post('user/col/check', 'App\Http\Controllers\RegistrationController@checkIsColExist')->name('user.col.check');
Route::post('forgot/password', 'App\Http\Controllers\ForgotPasswordController@sendCode')->name('user.forgot.password');
Route::post('check/reset/code', 'App\Http\Controllers\ResetPasswordController@checkCode')->name('check.reset.code');
Route::post('reset/password', 'App\Http\Controllers\ResetPasswordController@reset')->name('reset.password');
Route::post('resend/activation/password', 'App\Http\Controllers\ResetPasswordController@resendActivationCode')->name('resend.activation.code');

Route::get("aboutus", "App\Http\Controllers\PageController@aboutUs")->name('aboutus');
Route::get("terms-and-conditions", "App\Http\Controllers\PageController@termsAndConditions")->name('terms');
Route::get("privacy-and-policy", "App\Http\Controllers\PageController@privacy")->name('privacy');

Route::get('/user/profile', 'App\Http\Controllers\ProfileController@profile')->name('profile');
// Legacy web address form retired: the routes only 500'd — the resource's
// create/show/edit/update/destroy methods and ProfileController@userAddresses
// never existed, and addresses.index rendered a view that was never built. The
// live address book is Api\V2\AddressController. AddressController +
// StoreAddressRequest are kept (unrouted) under the keep-v1 rule for porting.
Route::post('/profile/update', 'App\Http\Controllers\ProfileController@profileUpdateUser')->name('profile.update');

Auth::routes();

// The legacy `administrator` group (Admin\HomeController + Admin\BusinessController)
// was removed with the v1 panel: both controllers are deleted, its `admin.dashboard`
// name is now served by AdminV2's DashboardController (URI `admin`), and its
// `businesses` resource had no callers.

Route::prefix('admin')->middleware(['web'])->group(function () {
    Route::post('category-services-bulk/apply', [CategoryServiceBulkController::class, 'apply'])->name('admin.category-services-bulk.apply');
});

// Shared (group) cart web entry: a share link/QR opens this page, which joins
// + renders the cart via the v2 API. Self-contained; no server auth required.
Route::get('/cart/join/{token}/qr', [App\Http\Controllers\SharedCartWebController::class, 'qr'])
    ->name('cart.shared.qr');
Route::get('/cart/join/{token}', [App\Http\Controllers\SharedCartWebController::class, 'join'])
    ->name('cart.shared.join');
Route::get('/cart/share/{business}', [App\Http\Controllers\SharedCartWebController::class, 'share'])
    ->whereNumber('business')->name('cart.shared.host');

// Restaurant-table QR (BIM-13.3): a permanent sticker points at /t/{token}.
Route::get('/t/{token}/qr', [App\Http\Controllers\SharedCartWebController::class, 'tableQr'])
    ->name('table.qr');
Route::get('/t/{token}', [App\Http\Controllers\SharedCartWebController::class, 'table'])
    ->name('table.scan.web');

// Public storefront QR (BIM-13.4): a permanent business-profile sticker/card.
Route::get('/b/{business}/qr', [App\Http\Controllers\BusinessProfileWebController::class, 'qr'])
    ->whereNumber('business')->name('storefront.qr');
Route::get('/b/{business}', [App\Http\Controllers\BusinessProfileWebController::class, 'show'])
    ->whereNumber('business')->name('storefront.show');

// Order-handover QR (BIM-13.5): the ready order's sticker points at /h/{token}.
Route::get('/h/{token}/qr', [App\Http\Controllers\HandoverWebController::class, 'qr'])
    ->name('handover.qr');
Route::get('/h/{token}', [App\Http\Controllers\HandoverWebController::class, 'scan'])
    ->name('handover.scan.web');

// Connected delivery loop: /dp = driver confirms pickup, /dd = customer confirms receipt.
Route::get('/dp/{token}/qr', [App\Http\Controllers\DeliveryWebController::class, 'pickupQr'])->name('delivery.pickup.qr');
Route::get('/dp/{token}', [App\Http\Controllers\DeliveryWebController::class, 'pickup'])->name('delivery.pickup.web');
Route::get('/dd/{token}/qr', [App\Http\Controllers\DeliveryWebController::class, 'deliverQr'])->name('delivery.deliver.qr');
Route::get('/dd/{token}', [App\Http\Controllers\DeliveryWebController::class, 'deliver'])->name('delivery.deliver.web');

Route::get('/_disabled/category-products', function () {
    abort(404);
})->name('category.products');

Route::get('/_disabled/cart', function () {
    abort(404);
})->name('cart');
