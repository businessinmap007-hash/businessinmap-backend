<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AdminV2\{
    Auth\LoginController,
    Users\UserController,
    CategoryController,
    PostController,
    DashboardController,
    TransactionsController,
    UploadController,
    JobPostController,
    WalletTransactionController,
    WalletNoteTemplateController,
    SponsorController,
    PaymentController,
    WalletOpsController,
    SubscriptionController,
    AlbumController,
    BookingController,
    DisputeController,
    BusinessServicePriceController,
    ServiceFeeController,
    PlatformServiceController,
    BookableItemController
};

Route::prefix('admin')->name('admin.')->group(function () {

    // =========================
    // Auth (Public)
    // =========================
    Route::get('login',  [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->name('login.post');
    Route::post('logout',[LoginController::class, 'logout'])->name('logout');

    /**
     * ✅ callback route (بدون auth) — الأفضل تحميه بتوقيع/secret
     */
    Route::post('payments/callback/success', [PaymentController::class, 'callbackSuccess'])
        ->name('payments.callback.success');

    // =========================
    // Protected (admin.v2)
    // =========================
    Route::middleware(['admin.v2'])->group(function () {

        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Upload
        Route::post('upload/image', [UploadController::class, 'store'])->name('upload.image');

        // =========================
        // Users
        // =========================
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [UserController::class, 'index'])->name('index');

            Route::delete('/', [UserController::class, 'bulkDestroy'])->name('bulkDestroy');
            Route::post('restore', [UserController::class, 'bulkRestore'])->name('bulkRestore');
            Route::delete('force', [UserController::class, 'bulkForceDelete'])->name('bulkForceDelete');

            Route::get('{user}', [UserController::class, 'show'])->name('show');
            Route::get('{user}/edit', [UserController::class, 'edit'])->name('edit');
            Route::put('{user}', [UserController::class, 'update'])->name('update');

            Route::delete('{user}', [UserController::class, 'destroy'])->name('destroy');
            Route::post('{id}/restore', [UserController::class, 'restore'])->name('restore');
            Route::delete('{id}/force', [UserController::class, 'forceDelete'])->name('forceDelete');

            Route::post('{user}/toggle-suspend', [UserController::class, 'toggleSuspend'])->name('toggleSuspend');
        });

        // =========================
        // Categories (CRUD)
        // =========================
        Route::prefix('categories')->name('categories.')->group(function () {
            Route::get('/', [CategoryController::class, 'index'])->name('index');

            Route::get('create', [CategoryController::class, 'create'])->name('create');
            Route::post('/', [CategoryController::class, 'store'])->name('store');

            Route::get('{category}/edit', [CategoryController::class, 'edit'])->name('edit');
            Route::put('{category}', [CategoryController::class, 'update'])->name('update');

            Route::delete('{category}', [CategoryController::class, 'destroy'])->name('destroy');

            Route::post('{category}/toggle-active', [CategoryController::class, 'toggleActive'])->name('toggleActive');
            Route::post('{category}/reorder', [CategoryController::class, 'updateReorder'])->name('reorder');
        });

        // =========================
        // Posts (CRUD)
        // =========================
        Route::prefix('posts')->name('posts.')->group(function () {
            Route::get('/', [PostController::class, 'index'])->name('index');
            Route::post('/', [PostController::class, 'store'])->name('store');

            Route::get('{post}', [PostController::class, 'show'])->name('show');
            Route::get('{post}/edit', [PostController::class, 'edit'])->name('edit');
            Route::put('{post}', [PostController::class, 'update'])->name('update');

            Route::post('{post}/toggle-active', [PostController::class, 'toggleActive'])->name('toggleActive');
            Route::delete('{post}', [PostController::class, 'destroy'])->name('destroy');

            Route::delete('{post}/images/{image}', [PostController::class, 'destroyImage'])->name('images.destroy');
            Route::delete('{post}/main-image', [PostController::class, 'destroyMainImage'])->name('main_image.destroy');
        });

        // =========================
        // Jobs
        // =========================
        Route::prefix('jobs')->name('jobs.')->group(function () {
            Route::get('/', [JobPostController::class, 'index'])->name('index');
            Route::post('/', [JobPostController::class, 'store'])->name('store');

            Route::get('{post}', [JobPostController::class, 'show'])->name('show');
            Route::get('{post}/edit', [JobPostController::class, 'edit'])->name('edit');
            Route::put('{post}', [JobPostController::class, 'update'])->name('update');

            Route::post('{post}/toggle-active', [JobPostController::class, 'toggleActive'])->name('toggleActive');
            Route::delete('{post}', [JobPostController::class, 'destroy'])->name('destroy');
        });

        // =========================
        // Sponsors
        // =========================
        Route::prefix('sponsors')->name('sponsors.')->group(function () {
            Route::get('/', [SponsorController::class, 'index'])->name('index');

            Route::get('create', [SponsorController::class, 'create'])->name('create');
            Route::post('/', [SponsorController::class, 'store'])->name('store');

            Route::get('{sponsor}/edit', [SponsorController::class, 'edit'])->name('edit');
            Route::put('{sponsor}', [SponsorController::class, 'update'])->name('update');

            Route::post('{sponsor}/toggle-active', [SponsorController::class, 'toggleActive'])->name('toggleActive');
            Route::delete('{sponsor}', [SponsorController::class, 'destroy'])->name('destroy');
        });

        // =========================
        // Transactions
        // =========================
        Route::prefix('transactions')->name('transactions.')->group(function () {
            Route::get('/', [TransactionsController::class, 'index'])->name('index');
            Route::get('{tx}', [TransactionsController::class, 'show'])->name('show');
        });

        // =========================
        // Wallet Transactions
        // =========================
        Route::prefix('wallet-transactions')->name('wallet-transactions.')->group(function () {
            Route::get('/', [WalletTransactionController::class, 'index'])->name('index');
            Route::get('user/{user}', [WalletTransactionController::class, 'user'])->name('user'); // قبل {walletTransaction}
            Route::get('{walletTransaction}', [WalletTransactionController::class, 'show'])->name('show');
        });

        // Wallet Ops (Recharge)
        Route::get('wallet-ops/recharge', [WalletOpsController::class, 'rechargeForm'])->name('wallet-ops.recharge.form');
        Route::post('wallet-ops/recharge', [WalletOpsController::class, 'recharge'])->name('wallet-ops.recharge');

        // Wallet Note Templates
        Route::resource('wallet-notes', WalletNoteTemplateController::class)
            ->except(['show'])
            ->names('wallet-notes');

        // =========================
        // Subscriptions
        // =========================
        Route::get('subscriptions', [SubscriptionController::class,'index'])->name('subscriptions.index');
        Route::get('subscriptions/{subscription}', [SubscriptionController::class,'show'])->name('subscriptions.show');
        Route::get('subscriptions/{subscription}/edit', [SubscriptionController::class,'edit'])->name('subscriptions.edit');
        Route::put('subscriptions/{subscription}', [SubscriptionController::class,'update'])->name('subscriptions.update');
        Route::post('subscriptions/{subscription}/toggle-active', [SubscriptionController::class,'toggleActive'])->name('subscriptions.toggle-active');

        // =========================
        // Payments (Admin)
        // =========================
        Route::prefix('payments')->name('payments.')->group(function () {
            Route::get('/', [PaymentController::class, 'index'])->name('index');
            Route::post('{paymentId}/confirm', [PaymentController::class, 'confirm'])->name('confirm');
        });

        // =========================
        // Albums
        // =========================
        Route::prefix('albums')->name('albums.')->group(function () {
            Route::get('/', [AlbumController::class, 'index'])->name('index');
            Route::get('create', [AlbumController::class, 'create'])->name('create');
            Route::post('/', [AlbumController::class, 'store'])->name('store');

            Route::get('{album}', [AlbumController::class, 'show'])->name('show');
            Route::get('{album}/edit', [AlbumController::class, 'edit'])->name('edit');
            Route::put('{album}', [AlbumController::class, 'update'])->name('update');

            Route::delete('{album}', [AlbumController::class, 'destroy'])->name('destroy');

            Route::post('{album}/images/{imageId}/set-cover', [AlbumController::class, 'setCover'])->name('images.set-cover');
            Route::delete('{album}/images/{imageId}', [AlbumController::class, 'deleteImage'])->name('images.delete');
        });

        // =========================
        // Bookings
        // =========================
        Route::prefix('bookings')->name('bookings.')->group(function () {

            // ✅ لازم قبل {booking}
            Route::get('services/lookup', [BookingController::class, 'serviceLookup'])->name('services.lookup');

            // CRUD
            Route::get('/', [BookingController::class, 'index'])->name('index');
            Route::get('create', [BookingController::class, 'create'])->name('create');
            Route::post('/', [BookingController::class, 'store'])->name('store');
            Route::get('bookable-items/lookup', [BookingController::class, 'bookableItemsLookup'])->name('bookable-items.lookup');

            Route::get('{booking}', [BookingController::class, 'show'])->name('show');
            Route::get('{booking}/edit', [BookingController::class, 'edit'])->name('edit');
            Route::put('{booking}', [BookingController::class, 'update'])->name('update');
            Route::delete('{booking}', [BookingController::class, 'destroy'])->name('destroy');

            // Deposit confirmations
            Route::post('{booking}/start-confirm/client',   [BookingController::class, 'startConfirmClient'])->name('start_confirm.client');
            Route::post('{booking}/start-confirm/business', [BookingController::class, 'startConfirmBusiness'])->name('start_confirm.business');

            // Deposit actions
            Route::post('{booking}/deposit/freeze',  [BookingController::class, 'depositFreeze'])->name('deposit.freeze');
            Route::post('{booking}/deposit/release', [BookingController::class, 'depositRelease'])->name('deposit.release');
            Route::post('{booking}/deposit/refund',  [BookingController::class, 'depositRefund'])->name('deposit.refund');

            Route::post('{booking}/deposit/dispute/open', [BookingController::class, 'depositDisputeOpen'])->name('deposit.dispute.open');
            Route::post('{booking}/deposit/dispute/agree-release', [BookingController::class, 'depositAgreeRelease'])->name('deposit.dispute.agree_release');
            Route::post('{booking}/deposit/dispute/agree-refund',  [BookingController::class, 'depositAgreeRefund'])->name('deposit.dispute.agree_refund');

            Route::post('{booking}/deposit/confirm-client',   [BookingController::class,'depositConfirmClient'])->name('deposit.confirmClient');
            Route::post('{booking}/deposit/confirm-business', [BookingController::class,'depositConfirmBusiness'])->name('deposit.confirmBusiness');
            
        });

        // =========================
        // Disputes
        // =========================
        Route::get('disputes', [DisputeController::class, 'index'])->name('disputes.index');

        // =========================
        // Service Fees / Business Service Prices
        // =========================
        Route::prefix('service-fees')->name('service-fees.')->group(function () {
            Route::get('/', [ServiceFeeController::class, 'index'])->name('index');
            Route::get('create', [ServiceFeeController::class, 'create'])->name('create');
            Route::post('/', [ServiceFeeController::class, 'store'])->name('store');
            Route::get('{serviceFee}/edit', [ServiceFeeController::class, 'edit'])->name('edit');
            Route::put('{serviceFee}', [ServiceFeeController::class, 'update'])->name('update');
            Route::delete('{serviceFee}', [ServiceFeeController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('business-service-prices')->name('business_service_prices.')->group(function () {
            Route::get('/', [BusinessServicePriceController::class, 'index'])->name('index');
            Route::get('/create', [BusinessServicePriceController::class, 'create'])->name('create');
            Route::post('/', [BusinessServicePriceController::class, 'store'])->name('store');
            Route::get('/{row}/edit', [BusinessServicePriceController::class, 'edit'])->name('edit');
            Route::put('/{row}', [BusinessServicePriceController::class, 'update'])->name('update');
            Route::delete('/{row}', [BusinessServicePriceController::class, 'destroy'])->name('destroy');
        });


        /*
        |--------------------------------------------------------------------------
        | Platform Services
        |--------------------------------------------------------------------------
        | تعريف الخدمات الأساسية للنظام مثل:
        | booking / menu / delivery
        | وتحديد قواعدها (deposit / fee / rules)
        */

        Route::prefix('platform-services')->name('platform-services.')->group(function () {
            Route::get('/', [PlatformServiceController::class, 'index'])->name('index');
            Route::get('/create', [PlatformServiceController::class, 'create'])->name('create');
            Route::post('/', [PlatformServiceController::class, 'store'])->name('store');
            Route::get('/{platformService}/edit', [PlatformServiceController::class, 'edit'])->name('edit');
            Route::put('/{platformService}', [PlatformServiceController::class, 'update'])->name('update');
            Route::delete('/{platformService}', [PlatformServiceController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('bookable-items')->name('bookable-items.')->group(function () {
            Route::get('/', [BookableItemController::class, 'index'])->name('index');
            Route::get('/create', [BookableItemController::class, 'create'])->name('create');
            Route::post('/', [BookableItemController::class, 'store'])->name('store');
            Route::get('/{bookableItem}/edit', [BookableItemController::class, 'edit'])->name('edit');
            Route::put('/{bookableItem}', [BookableItemController::class, 'update'])->name('update');
            Route::delete('/{bookableItem}', [BookableItemController::class, 'destroy'])->name('destroy');
        });
    });

});