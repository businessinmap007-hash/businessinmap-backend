<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Import Controllers (Api/V1)
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\Api\V1\{
    RegistrationController,
    LoginController,
    ForgotPasswordController,
    ResetPasswordController,
    UsersController,
    SettingsController,
    JobController,
    CategoryController,
    ProductsController,
    CommentController,
    PostController,
    LocationController,
    SponsorController,
    BusinessController,
    ProfileController,
    PaymentController,
    ApplyController,
    FollowController,
    TargetController,
    AlbumController,
    ImageController,
    RatesController,
    CategoriesController,
    CouponController,
    TransactionController,
    NotificationController,
    DeliveryController,
    RideController,
    ChatController,
    CarController,
    OrderController,
    DriverLocationController,
    DepositController,
    WalletController,
    WalletPinController,
    SearchController,
    MenuController,
    CartController,
    CourierController,
    LocationDropdownController
};

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Public Routes (No token required)
    |--------------------------------------------------------------------------
    */

    // Auth
    Route::post('register', [RegistrationController::class, 'store']);
    Route::post('login',    [LoginController::class, 'login']);

    Route::post('password/forgot',        [ForgotPasswordController::class, 'getResetTokens']);
    Route::post('password/check',         [ResetPasswordController::class,  'check']);
    Route::post('password/reset',         [ResetPasswordController::class,  'reset']);
    Route::post('password/forgot/resend', [ForgotPasswordController::class, 'resendResetPasswordCode']);

    // Settings
    Route::prefix('general')->group(function () {
        Route::get('info',         [SettingsController::class, 'generalInfo']);
        Route::get('support',      [SettingsController::class, 'support']);
        Route::get('contacts',     [SettingsController::class, 'contacts']);
        Route::get('about-app',    [SettingsController::class, 'aboutApp']);
        Route::get('social/links', [SettingsController::class, 'socialLinks']);
    });

    // Jobs / Categories / Products
    Route::get('jobs',                         [JobController::class,      'index']);
    Route::get('categories',                   [CategoryController::class, 'index']);
    Route::get('category/{category}/products', [ProductsController::class, 'productsByCategoryId']);

    // Comments
    Route::get('comments/{post}/post',       [CommentController::class, 'index']);
    Route::get('comments/{comment}/replies', [CommentController::class, 'commentRepliesList']);

    // Posts
    Route::get('get/posts',           [PostController::class, 'getPosts']);
    Route::get('get/posts/{id}/list', [PostController::class, 'index']);

    // Locations (Dropdown - current)
    Route::get('countries',       [LocationDropdownController::class, 'countries']);
    Route::get('governorates',    [LocationDropdownController::class, 'governorates']);
    Route::get('cities',          [LocationDropdownController::class, 'cities']);
    Route::get('cities/search',   [LocationDropdownController::class, 'searchCities']);

    // Search
    Route::get('search/locations', [SearchController::class, 'locations']);

    // Sponsors & Business
    Route::get('get/paid/sponsors',       [SponsorController::class, 'paidSponsorList']);
    Route::get('get/free/advertisements', [SponsorController::class, 'getFreeAds']);
    Route::post('share/{post}/social',    [PostController::class, 'sharePost']);

    Route::get('category/{category}/business', [BusinessController::class, 'index']);
    Route::get('get/business/list',            [BusinessController::class, 'getBusinessList']);

    // Payment callbacks
    Route::post('fawry-success-payment', [PaymentController::class, 'fawrySuccessPayment']);
    Route::any('payment/success/cashu',  [PaymentController::class, 'cashuSuccess']);

    Route::any('payment/error/cashu', function (Request $request) {
        return $request->all();
    });

    Route::prefix('menu')->group(function () {
        Route::get('items',       [MenuController::class, 'items']);
        Route::get('items/{id}',  [MenuController::class, 'show']);
    });

    Route::middleware('auth:sanctum')->group(function () {

        // Profile
        Route::get('profile',            [ProfileController::class, 'index']);
        Route::post('profile/update',    [ProfileController::class, 'updateProfile']);
        Route::post('user/update/phone', [ProfileController::class, 'updatePhone']);

        // Notifications
        Route::prefix('notifications')->group(function () {
            Route::get('/',            [NotificationController::class, 'index']);
            Route::get('unread',       [NotificationController::class, 'unread']);
            Route::get('{id}',         [NotificationController::class, 'show']);
            Route::post('{id}/read',   [NotificationController::class, 'markAsRead']);
            Route::delete('{id}',      [NotificationController::class, 'destroy']);
            Route::post('/',           [NotificationController::class, 'store']);
        });

        Route::prefix('cart')->group(function () {
            Route::get('my',                    [CartController::class, 'myCart']);
            Route::post('items',                [CartController::class, 'addItem']);
            Route::patch('items/{cartItemId}',  [CartController::class, 'updateQty']);
            Route::delete('items/{cartItemId}', [CartController::class, 'removeItem']);
            Route::delete('clear',              [CartController::class, 'clear']);
        });

        // General Orders
        Route::prefix('orders')->group(function () {
            Route::post('/',   [OrderController::class, 'store']);
            Route::get('my',   [OrderController::class, 'myOrders']);
            Route::get('{id}', [OrderController::class, 'show']);

            Route::middleware('business')->group(function () {
                Route::get('business',     [OrderController::class, 'businessOrders']);
                Route::post('{id}/status', [OrderController::class, 'updateStatus']);
            });
        });

        // Delivery Orders
        Route::prefix('delivery')->group(function () {
            Route::post('orders',             [DeliveryController::class, 'store']);
            Route::get('orders',              [DeliveryController::class, 'myOrders']);
            Route::get('orders/business',     [DeliveryController::class, 'businessOrders']);
            Route::get('orders/driver',       [DeliveryController::class, 'driverOrders']);
            Route::get('orders/available',    [DeliveryController::class, 'availableOrders']);
            Route::get('orders/{id}',         [DeliveryController::class, 'show']);
            Route::post('orders/{id}/accept', [DeliveryController::class, 'accept']);
            Route::post('orders/{id}/status', [DeliveryController::class, 'updateStatus']);
            Route::post('orders/{id}/cancel', [DeliveryController::class, 'cancel']);
            Route::post('orders/{id}/reject', [DeliveryController::class, 'reject']);
        });

        // Courier service (on/off + location + profile)
        Route::prefix('courier')->group(function () {
            Route::get('me',        [CourierController::class, 'myProfile']);
            Route::post('status',   [CourierController::class, 'updateStatus']);
            Route::post('location', [CourierController::class, 'updateLocation']);
        });

        // Driver Location
        Route::prefix('driver/location')->group(function () {
            Route::post('update',     [DriverLocationController::class, 'update']);
            Route::get('{driver_id}', [DriverLocationController::class, 'show']);
        });

        // Rides
        Route::prefix('rides')->group(function () {
            Route::post('/',           [RideController::class, 'store']);
            Route::get('/',            [RideController::class, 'myRides']);
            Route::get('{id}',         [RideController::class, 'show']);
            Route::delete('{id}',      [RideController::class, 'cancel']);
            Route::post('{id}/status', [RideController::class, 'updateStatus']);
            Route::post('{id}/accept', [RideController::class, 'acceptRide']);
        });

        Route::get('driver/rides', [RideController::class, 'driverRides']);

        // Chat
        Route::prefix('chat')->group(function () {
            Route::get('conversations',                [ChatController::class, 'conversations']);
            Route::post('conversations',               [ChatController::class, 'startConversation']);
            Route::get('conversations/{id}/messages',  [ChatController::class, 'messages']);
            Route::post('conversations/{id}/messages', [ChatController::class, 'sendMessage']);
            Route::post('conversations/{id}/read',     [ChatController::class, 'markAsRead']);
            Route::delete('conversations/{id}',        [ChatController::class, 'deleteConversation']);
        });

        // Payments
        Route::post('payment/charge/account', [PaymentController::class, 'chargeAccount']);
        Route::post('payment/subscription',   [PaymentController::class, 'store']);
        Route::post('payment/transfer',       [PaymentController::class, 'transferToAnother']);

        // Transactions
        Route::prefix('transactions')
            ->controller(TransactionController::class)
            ->group(function () {
                Route::get('/',       'index');
                Route::get('balance', 'balance');
                Route::get('summary', 'summary');
                Route::get('{id}',    'show');
            });

        // Wallet
        Route::prefix('wallet')->group(function () {
            Route::post('pin/set',    [WalletPinController::class, 'setPin']);
            Route::post('pin/verify', [WalletPinController::class, 'verifyPin']);
            Route::post('pin/update', [WalletPinController::class, 'updatePin']);

            Route::get('balance',      [WalletController::class, 'balance']);
            Route::get('transactions', [WalletController::class, 'transactions']);

            Route::post('deposit',  [WalletController::class, 'deposit']);
            Route::post('withdraw', [WalletController::class, 'withdraw']);
            Route::post('transfer', [WalletController::class, 'transfer']);
        });

        // Deposit System
        Route::prefix('deposits')->group(function () {
            Route::post('/', [DepositController::class, 'create']);
            Route::get('/', [DepositController::class, 'index']);
            Route::get('{id}', [DepositController::class, 'show']);
            Route::post('{id}/start-execution', [DepositController::class, 'startExecution']);
            Route::post('{id}/release', [DepositController::class, 'release']);
            Route::post('{id}/refund', [DepositController::class, 'refund']);
        });

    });
});

require __DIR__ . '/api_v2.php';
