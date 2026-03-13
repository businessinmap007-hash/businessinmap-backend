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
    BookingController,
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
    Route::get('governorates',    [LocationDropdownController::class, 'governorates']);    // ?country_id=1
    Route::get('cities',          [LocationDropdownController::class, 'cities']);          // ?governorate_id=5
    Route::get('cities/search',   [LocationDropdownController::class, 'searchCities']);    // ?q=زق&governorate_id=5

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

    /*
    |--------------------------------------------------------------------------
    | MENU V1 (Public menu browsing)  ✅ NEW
    |--------------------------------------------------------------------------
    | - Browsing menu does NOT require token
    | - Cart requires token
    */

    Route::prefix('menu')->group(function () {
        Route::get('items',       [MenuController::class, 'items']);  // GET  /v1/menu/items
        Route::get('items/{id}',  [MenuController::class, 'show']);   // GET  /v1/menu/items/{id}
    });

    /*
    |--------------------------------------------------------------------------
    | Authenticated Routes (Require Sanctum Token)
    |--------------------------------------------------------------------------
    */

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

        /*
        |--------------------------------------------------------------------------
        | CART V1 ✅ NEW (matches our CartController methods)
        |--------------------------------------------------------------------------
        */

        Route::prefix('cart')->group(function () {
            Route::get('my',                    [CartController::class, 'myCart']);        // GET    /v1/cart/my
            Route::post('items',                [CartController::class, 'addItem']);       // POST   /v1/cart/items
            Route::patch('items/{cartItemId}',  [CartController::class, 'updateQty']);     // PATCH  /v1/cart/items/{id}
            Route::delete('items/{cartItemId}', [CartController::class, 'removeItem']);    // DELETE /v1/cart/items/{id}
            Route::delete('clear',              [CartController::class, 'clear']);         // DELETE /v1/cart/clear
        });
        

        /*
        |--------------------------------------------------------------------------
        | Menu Orders (keep as-is)
        |--------------------------------------------------------------------------
        */

        // Route::prefix('menu/orders')
        //     ->controller(MenuOrderController::class)
        //     ->group(function () {
        //         Route::post('from-cart', 'createFromCart');
        //         Route::get('my', 'myOrders');
        //         Route::get('{id}', 'show');

        //         Route::middleware('business')->group(function () {
        //             Route::get('business', 'businessOrders');
        //             Route::post('{id}/status', 'updateStatus');
        //         });
        //     });

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

                // Store / Restaurant business
                Route::get('orders/business',     [DeliveryController::class, 'businessOrders']);

                // Courier
                Route::get('orders/driver',       [DeliveryController::class, 'driverOrders']);
                Route::get('orders/available',    [DeliveryController::class, 'availableOrders']);

                // Details
                Route::get('orders/{id}',         [DeliveryController::class, 'show']);

                // Actions
                Route::post('orders/{id}/accept', [DeliveryController::class, 'accept']);
                Route::post('orders/{id}/status', [DeliveryController::class, 'updateStatus']);

                // Cancel (✅ بدل DELETE)
                Route::post('orders/{id}/cancel', [DeliveryController::class, 'cancel']);

                // Reject (hide from courier list)
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

        // Booking
        Route::prefix('booking')->group(function () {
            Route::post('create',        [BookingController::class, 'store']);
            Route::post('update-status', [BookingController::class, 'updateStatus']);
            Route::get('my',             [BookingController::class, 'myBookings']);

            Route::middleware('business')->group(function () {
                Route::get('business', [BookingController::class, 'businessBookings']);
            });
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

        /*
        |--------------------------------------------------------------------------
        | WALLET SYSTEM — FINAL CLEAN VERSION
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | DEPOSIT SYSTEM
        |--------------------------------------------------------------------------
        */

        Route::prefix('deposits')->group(function () {
         // ===============================
        // CRUD / Listing
        // ===============================

        // إنشاء Deposit (Freeze)
        Route::post('/', [DepositController::class, 'create']);

        // قائمة Deposits (فلترة)
        Route::get('/', [DepositController::class, 'index']);

        // عرض Deposit واحد
        Route::get('{id}', [DepositController::class, 'show']);

        // ===============================
        // Workflow Actions
        // ===============================

        // 🚀 Start Execution (خصم رسوم الخدمة من مقدم الخدمة)
        Route::post('{id}/start-execution', [DepositController::class, 'startExecution']);

        // Release (فك التجميد للطرفين)
        Route::post('{id}/release', [DepositController::class, 'release']);

        // Refund (إرجاع لطرف واحد أو للطرفين)
        Route::post('{id}/refund', [DepositController::class, 'refund']);
        });

    });

  
    
    
      
});
