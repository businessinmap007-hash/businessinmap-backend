<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AdminV2\{
    Auth\LoginController,
    Users\UserController,
    DashboardController,
    CategoryController,
    PostController,
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
    DisputeController
};

Route::prefix('admin')->name('admin.')->group(function () {

    // =========================
    // Auth (Public)
    // =========================
    Route::get('login',  [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->name('login.post');
    Route::post('logout',[LoginController::class, 'logout'])->name('logout');

    // =========================
    // AdminV2 (Protected)
    // =========================
    Route::middleware(['admin.v2'])->group(function () {

        // Dashboard
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

        // Uploads
        Route::post('upload', [UploadController::class, 'store'])->name('upload.store');

        // Users
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [UserController::class, 'index'])->name('index');
            Route::get('create', [UserController::class, 'create'])->name('create');
            Route::post('/', [UserController::class, 'store'])->name('store');
            Route::get('{user}', [UserController::class, 'show'])->name('show');
            Route::get('{user}/edit', [UserController::class, 'edit'])->name('edit');
            Route::put('{user}', [UserController::class, 'update'])->name('update');
            Route::delete('{user}', [UserController::class, 'destroy'])->name('destroy');
        });

        // Categories
        Route::prefix('categories')->name('categories.')->group(function () {
            Route::get('/', [CategoryController::class, 'index'])->name('index');
            Route::get('create', [CategoryController::class, 'create'])->name('create');
            Route::post('/', [CategoryController::class, 'store'])->name('store');
            Route::get('{category}/edit', [CategoryController::class, 'edit'])->name('edit');
            Route::put('{category}', [CategoryController::class, 'update'])->name('update');
            Route::delete('{category}', [CategoryController::class, 'destroy'])->name('destroy');
        });

        // Posts
        Route::prefix('posts')->name('posts.')->group(function () {
            Route::get('/', [PostController::class, 'index'])->name('index');
            Route::get('create', [PostController::class, 'create'])->name('create');
            Route::post('/', [PostController::class, 'store'])->name('store');
            Route::get('{post}', [PostController::class, 'show'])->name('show');
            Route::get('{post}/edit', [PostController::class, 'edit'])->name('edit');
            Route::put('{post}', [PostController::class, 'update'])->name('update');
            Route::delete('{post}', [PostController::class, 'destroy'])->name('destroy');
        });

        // Jobs
        Route::prefix('jobs')->name('jobs.')->group(function () {
            Route::get('/', [JobPostController::class, 'index'])->name('index');
            Route::get('create', [JobPostController::class, 'create'])->name('create');
            Route::post('/', [JobPostController::class, 'store'])->name('store');
            Route::get('{job}', [JobPostController::class, 'show'])->name('show');
            Route::get('{job}/edit', [JobPostController::class, 'edit'])->name('edit');
            Route::put('{job}', [JobPostController::class, 'update'])->name('update');
            Route::delete('{job}', [JobPostController::class, 'destroy'])->name('destroy');
        });

        // Sponsors
        Route::prefix('sponsors')->name('sponsors.')->group(function () {
            Route::get('/', [SponsorController::class, 'index'])->name('index');
            Route::get('create', [SponsorController::class, 'create'])->name('create');
            Route::post('/', [SponsorController::class, 'store'])->name('store');
            Route::get('{sponsor}', [SponsorController::class, 'show'])->name('show');
            Route::get('{sponsor}/edit', [SponsorController::class, 'edit'])->name('edit');
            Route::put('{sponsor}', [SponsorController::class, 'update'])->name('update');
            Route::delete('{sponsor}', [SponsorController::class, 'destroy'])->name('destroy');
        });

        // Albums
        Route::prefix('albums')->name('albums.')->group(function () {
            Route::get('/', [AlbumController::class, 'index'])->name('index');
            Route::get('create', [AlbumController::class, 'create'])->name('create');
            Route::post('/', [AlbumController::class, 'store'])->name('store');
            Route::get('{album}', [AlbumController::class, 'show'])->name('show');
            Route::get('{album}/edit', [AlbumController::class, 'edit'])->name('edit');
            Route::put('{album}', [AlbumController::class, 'update'])->name('update');
            Route::delete('{album}', [AlbumController::class, 'destroy'])->name('destroy');
        });

        // Transactions (لو عندك صفحة عامة)
        Route::prefix('transactions')->name('transactions.')->group(function () {
            Route::get('/', [TransactionsController::class, 'index'])->name('index');
            Route::get('{txn}', [TransactionsController::class, 'show'])->name('show');
        });

        // Wallet
        Route::prefix('wallet')->name('wallet.')->group(function () {
            Route::get('transactions', [WalletTransactionController::class, 'index'])->name('transactions.index');
            Route::get('transactions/{txn}', [WalletTransactionController::class, 'show'])->name('transactions.show');

            Route::get('note-templates', [WalletNoteTemplateController::class, 'index'])->name('note_templates.index');

            Route::post('ops/hold', [WalletOpsController::class, 'hold'])->name('ops.hold');
            Route::post('ops/release', [WalletOpsController::class, 'release'])->name('ops.release');
        });

        // Payments / Subscriptions (حسب الموجود عندك)
        Route::prefix('payments')->name('payments.')->group(function () {
            Route::get('/', [PaymentController::class, 'index'])->name('index');
        });

        Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
            Route::get('/', [SubscriptionController::class, 'index'])->name('index');
        });

        // =========================
        // Bookings
        // =========================
        Route::prefix('bookings')->name('bookings.')->group(function () {

            // Service lookup (AJAX) - لازم قبل {booking}
            Route::get('services/lookup', [BookingController::class, 'serviceLookup'])->name('services.lookup');

            // CRUD
            Route::get('/', [BookingController::class, 'index'])->name('index');
            Route::get('create', [BookingController::class, 'create'])->name('create');
            Route::post('/', [BookingController::class, 'store'])->name('store');

            Route::get('{booking}', [BookingController::class, 'show'])->name('show');
            Route::get('{booking}/edit', [BookingController::class, 'edit'])->name('edit');
            Route::put('{booking}', [BookingController::class, 'update'])->name('update');
            Route::delete('{booking}', [BookingController::class, 'destroy'])->name('destroy');

            // Start confirm (client/business)
            Route::post('{booking}/start-confirm/client',   [BookingController::class, 'startConfirmClient'])->name('start_confirm.client');
            Route::post('{booking}/start-confirm/business', [BookingController::class, 'startConfirmBusiness'])->name('start_confirm.business');

            // Deposit actions
            Route::post('{booking}/deposit/freeze',  [BookingController::class, 'depositFreeze'])->name('deposit.freeze');
            Route::post('{booking}/deposit/release', [BookingController::class, 'depositRelease'])->name('deposit.release');
            Route::post('{booking}/deposit/refund',  [BookingController::class, 'depositRefund'])->name('deposit.refund');

            Route::post('{booking}/deposit/dispute/open', [BookingController::class, 'depositDisputeOpen'])->name('deposit.dispute.open');
            Route::post('{booking}/deposit/dispute/agree-release', [BookingController::class, 'depositAgreeRelease'])->name('deposit.dispute.agree_release');
            Route::post('{booking}/deposit/dispute/agree-refund',  [BookingController::class, 'depositAgreeRefund'])->name('deposit.dispute.agree_refund');

            Route::post('{booking}/deposit/confirm-client', [BookingController::class, 'depositConfirmClient'])->name('deposit.confirmClient');
        });

        // =========================
        // Disputes
        // =========================
        Route::prefix('disputes')->name('disputes.')->group(function () {
            Route::get('/', [DisputeController::class, 'index'])->name('index');
            Route::get('{booking}', [DisputeController::class, 'show'])->name('show');
        });

    });
});