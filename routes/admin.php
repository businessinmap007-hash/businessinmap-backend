<?php


use Illuminate\Http\Request;
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

//Route::get('lang/{language}', ['as' => 'lang.switch', 'uses' => 'LanguageController@switchLang']);


Route::get('fawry/success', function (Request $request) {
    return $request->all();
})->name('fawry.success');


Route::group(['prefix' => 'administrator', 'middleware' => 'localeControlPanel'], function () {


    Route::get('pay', 'App\Http\Controllers\Admin\HomeController@pay');


    Route::get('fawry/payment/online', function (Request $request) {

        $main = new  \App\Libraries\Main;


        $addtions = array(
            'price' => $request->price,
            'couponId' => $request->couponId,
            'duration' => $request->duration,
            'actionType' => $request->actionType
        );
//        return $main->paymentAction(1.0, '52', '', '', 'USD', 'balance', 'en,', 'Test', '', '');
        $paymentResponse = $main->fawryPayment($addtions['price'], '899', '', '', 'USD', 'balance', 'en,', 'Test', '', '', $addtions);

        $url = $paymentResponse['url'];
        $chargeRequest = $paymentResponse['chargeRequest'];
        $paymentUrl = $url . "?chargeRequest=" . $chargeRequest . "&failerPageUrl=https://www.google.com/&successPageUrl=https://businessinmap.com/testing/api/v1/fawry/success/payment?price=120&duration=3&couponId=4488333&actionType=subscription";
        return response()->json(['status' => 200, 'message' => 'success handle data.', 'paymentUrl' => $paymentUrl]);
    });


    //Route::get('/', 'App\Http\Controllers\Admin\LoginController@login')->name('admin');
    Route::get('/login', 'App\Http\Controllers\Admin\LoginController@login')->name('admin.login');
    Route::post('/login', 'App\Http\Controllers\Admin\LoginController@postLogin')->name('admin.postLogin');

    // Password Reset Routes...

    Route::get('password/reset', 'App\Http\Controllers\Admin\Auth\ForgotPasswordController@showLinkRequestForm')->name('administrator.password.request');
    Route::post('password/email', 'App\Http\Controllers\Admin\Auth\ForgotPasswordController@sendResetLinkEmail')->name('administrator.password.email');
    Route::get('password/reset/{token}', 'App\Http\Controllers\Admin\Auth\ResetPasswordController@showResetForm')->name('administrator.password.reset.token');
    Route::post('password/reset', 'App\Http\Controllers\Admin\Auth\ResetPasswordController@reset');

    Route::post('user/update/token', function (Request $request) {

        $user = \App\Models\User::whereId($request->id)->first();


        if ($request->token) {
            $data = \App\Models\Device::where('device', $request->token)->first();
            if ($data) {
                $data->user_id = $user->id;
                $data->save();
            } else {


                $data = new \App\Models\Device;
                $data->device = $request->token;
                $data->user_id = $user->id;
                $data->device_type = 'web';
                $data->save();
            }
        }


    })->name('user.update.token');


});


Route::group(['prefix' => 'administrator', 'middleware' => ['admin']], function () {

    // Pilgrims Mashaeer system Routes

    Route::resource('products', 'App\Http\Controllers\Admin\ProductController');
    Route::post('delete/product/image', 'App\Http\Controllers\Admin\ProductController@deleteImage')->name('delete.product.image');

    Route::get('/', 'App\Http\Controllers\Admin\HomeController@index')->name('home');
    Route::get('/home', 'App\Http\Controllers\Admin\HomeController@index')->name('admin.home');
    Route::resource('abilities', 'App\Http\Controllers\Admin\AbilitiesController');
    Route::post('abilities_mass_destroy', ['uses' => 'App\Http\Controllers\Admin\AbilitiesController@massDestroy', 'as' => 'abilities.mass_destroy']);

    /**
     * Custom Roles for agencies Companies
     */

    Route::post('roles/company-roles/store', 'App\Http\Controllers\Admin\RolesController@companyRolesStore')->name('roles.companies.store');
    Route::get('roles/company-roles', 'App\Http\Controllers\Admin\RolesController@companyRoles')->name('company.custom.roles');
    Route::get('roles/company-roles/create', 'App\Http\Controllers\Admin\RolesController@companyRolesCreate')->name('company.roles.create');
    Route::post('roles/company-roles/{id}/update', 'App\Http\Controllers\Admin\RolesController@companyRolesUpdate')->name('roles.companies.update');
    Route::get('roles/company-roles/{id}/edit', 'App\Http\Controllers\Admin\RolesController@companyRolesEdit')->name('company-roles.edit');
    Route::resource('roles', 'App\Http\Controllers\Admin\RolesController');
    Route::post('roles_mass_destroy', ['uses' => 'App\Http\Controllers\Admin\RolesController@massDestroy', 'as' => 'roles.mass_destroy']);
    Route::resource('users', 'App\Http\Controllers\Admin\UsersController');
    Route::resource('vendors', 'App\Http\Controllers\Admin\VendorController');
    Route::resource('clients', 'App\Http\Controllers\Admin\ClientController');
    Route::resource('business', 'App\Http\Controllers\Admin\BusinessController');
    Route::resource('sliders', 'App\Http\Controllers\Admin\SliderController');
    Route::resource('banners', 'App\Http\Controllers\Admin\BannerController');
    Route::resource('locations', 'App\Http\Controllers\Admin\LocationController');
    Route::resource('offers', 'App\Http\Controllers\Admin\OfferController');
    Route::resource('coupons', 'App\Http\Controllers\Admin\CouponController');
    Route::resource('options', 'App\Http\Controllers\Admin\OptionController');
    Route::get('transactions', 'App\Http\Controllers\Admin\TransactionController')->name('transactions.index');
    Route::post('transactions/{user}/store', 'App\Http\Controllers\Admin\TransactionController@store')->name('transactions.store');
    Route::post('transactions/{user}/store', 'App\Http\Controllers\Admin\TransactionController@store')->name('transactions.charge');
    Route::post('gifts/{user}/store', 'App\Http\Controllers\Admin\GiftController@store')->name('gifts.store');

    Route::post('users_mass_destroy', ['uses' => 'App\Http\Controllers\Admin\UsersController@massDestroy', 'as' => 'users.mass_destroy']);
    Route::post('role/delete/group', 'App\Http\Controllers\Admin\RolesController@groupDelete')->name('roles.group.delete');
    Route::get('managers', 'App\Http\Controllers\Admin\UsersController@usersManagers')->name('users.managers');
    Route::get('user/profile', 'App\Http\Controllers\Admin\UsersController@profile')->name('user.profile');
    Route::post('user/delete', 'App\Http\Controllers\Admin\UsersController@delete')->name('user.delete');
    Route::post('user/delete/group', 'App\Http\Controllers\Admin\UsersController@groupDelete')->name('users.group.delete');
    Route::post('user/suspend/group', 'App\Http\Controllers\Admin\UsersController@groupSuspend')->name('users.group.suspend');
    Route::post('user/suspend', 'App\Http\Controllers\Admin\UsersController@suspendUser')->name('user.suspend');
   // Route::post('companies/delete/group', 'App\Http\Controllers\Admin\CompaniesController@groupDelete')->name('companies.group.delete');
    Route::post('role/delete', 'App\Http\Controllers\Admin\RolesController@delete')->name('role.delete');

    Route::get('/home/settings', 'App\Http\Controllers\Admin\SettingsController@homeSettings')->name('home.settings');
    Route::get('/discount/gifts', 'App\Http\Controllers\Admin\SettingsController@discountAndGifts')->name('discounts.and.gifts');
    // Hotels
    Route::resource('jobs', 'App\Http\Controllers\Admin\JobController');
    Route::resource('categories', 'App\Http\Controllers\Admin\CategoriesController');
    Route::post('bank/suspend', 'App\Http\Controllers\Admin\BanksController@suspend')->name('bank.suspend');

    Route::resource('banks', 'App\Http\Controllers\Admin\BanksController');


    Route::get('settings/aboutus', 'App\Http\Controllers\Admin\SettingsController@aboutus')->name('settings.aboutus');
    Route::get('settings/terms', 'App\Http\Controllers\Admin\SettingsController@terms')->name('settings.terms');
    Route::get('settings/privacy', 'App\Http\Controllers\Admin\SettingsController@privacy')->name('settings.privacy');
    Route::get('/settings/support', 'App\Http\Controllers\Admin\SettingsController@support')->name('settings.support');
    Route::get('/settings/contactus', 'App\Http\Controllers\Admin\SettingsController@contactus')->name('settings.contactus');
    Route::get('/settings/app-general-settings', 'App\Http\Controllers\Admin\SettingsController@appGeneralSettings')->name('settings.app.general');
   // Route::POST('get/selected/buses', 'App\Http\Controllers\Admin\TripController@getSelectBuses')->name('get.selected.buses');


    Route::get('/settings/socials/links', 'App\Http\Controllers\Admin\SettingsController@socialLinks')->name('settings.socials');
    Route::post('/settings', 'App\Http\Controllers\Admin\SettingsController@store')->name('administrator.settings.store');

    Route::post('/logout', 'App\Http\Controllers\Admin\LoginController@logout')->name('administrator.logout');


    Route::post('get/all/selected/categories', 'App\Http\Controllers\Admin\CategoriesController@getSelectedCategories')->name('get.all.selected.categories');


    Route::get('/comments/{post}/list', 'App\Http\Controllers\Admin\CommentController@index')->name('comments.index');
    Route::get('/sponsors/list', 'App\Http\Controllers\Admin\SponsorController@index')->name('sponsors.index');
    Route::get('/albums/list', 'App\Http\Controllers\Admin\AlbumController@index')->name('albums.index');
    Route::get('/posts', 'App\Http\Controllers\Admin\PostController@index')->name('posts.index');
    Route::get('/posts/{post}/show', 'App\Http\Controllers\Admin\PostController@show')->name('posts.show');
    Route::get('/jobs', 'App\Http\Controllers\Admin\PostController@jobs')->name('jobs.index');


});

Auth::routes();

Route::get('roles', function () {

    $user = \App\Models\User::find(1);
//    $user->retract('admin');
    $user->assign('owner');
//    Bouncer::allow('owner')->everything();
    Bouncer::allow($user)->everything();
//    $user->allow('users_manage');
});


Route::post('/import_parse', function (Request $request) {
    $path = $request->file('csv_file')->getRealPath();
    $data = array_map('str_getcsv', file($path));
    $csv_data = array_slice($data, 0, 200);
    return response()->json([
        "status" => true,
        "html" => view('import_fields', compact('csv_data'))->render(),
        "message" => "Done"
    ]);
})->name('import_parse');

Route::post('/import_process', function (Request $request) {
    return $request->all();
})->name('import_process');


Route::get('activationcode/{phone}', function ($phone) {

    $user = User::wherePhone($phone)->first();

    if ($user) {
        return "Activation Code Is: " . $user->action_code;
    } else {
        return "User Not Found";
    }

});
