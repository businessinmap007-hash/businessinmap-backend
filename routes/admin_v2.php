<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AdminV2\{
    AlbumController,
    Auth\LoginController,
    BookableAllocationController,
    BookableItemBlockedSlotController,
    BookableItemBulkController,
    BookableItemCalendarController,
    BookableItemController,
    BookableItemPriceRuleController,
    BookingController,
    BusinessOffersSubscriptionController,
    BusinessPartnershipController,
    BusinessServicePriceController,
    CategoryChildOptionController,
    CategoryChildServiceFeeBulkController,
    CategoryChildServiceFeeController,
    CategoryController,
    CategoryServiceBulkController,
    CommercialOfferController,
    DashboardController,
    DisputeController,
    GuaranteeAdminController,
    GuaranteeLevelAdminController,
    JobPostController,
    OfferPerformanceController,
    OptionController,
    OptionGroupController,
    PaymentController,
    PlatformServiceController,
    PlatformServiceItemTypeController,
    PlatformServiceFeePromotionController,
    PostController,
    SponsorController,
    SubscriptionController,
    UploadController,
    Users\UserController,
    WalletNoteTemplateController,
    WalletOpsController,
    view\BookingTestController,
    WalletTransactionController
};

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->name('login.post');
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    Route::post('payments/callback/success', [PaymentController::class, 'callbackSuccess'])->name('payments.callback.success');

    Route::middleware(['admin.v2'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('upload/image', [UploadController::class, 'store'])->name('upload.image');

        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [UserController::class, 'index'])->name('index');
            Route::delete('/', [UserController::class, 'bulkDestroy'])->name('bulkDestroy');
            Route::post('restore', [UserController::class, 'bulkRestore'])->name('bulkRestore');
            Route::delete('force', [UserController::class, 'bulkForceDelete'])->name('bulkForceDelete');
            Route::get('{user}', [UserController::class, 'show'])->whereNumber('user')->name('show');
            Route::get('{user}/edit', [UserController::class, 'edit'])->whereNumber('user')->name('edit');
            Route::put('{user}', [UserController::class, 'update'])->whereNumber('user')->name('update');
            Route::delete('{user}', [UserController::class, 'destroy'])->whereNumber('user')->name('destroy');
            Route::post('{id}/restore', [UserController::class, 'restore'])->whereNumber('id')->name('restore');
            Route::delete('{id}/force', [UserController::class, 'forceDelete'])->whereNumber('id')->name('forceDelete');
            Route::post('{user}/toggle-suspend', [UserController::class, 'toggleSuspend'])->whereNumber('user')->name('toggleSuspend');
        });

        Route::prefix('categories')->name('categories.')->group(function () {
            Route::get('/', [CategoryController::class, 'index'])->name('index');
            Route::get('create', [CategoryController::class, 'create'])->name('create');
            Route::post('/', [CategoryController::class, 'store'])->name('store');
            Route::get('{category}/edit', [CategoryController::class, 'edit'])->whereNumber('category')->name('edit');
            Route::put('{category}', [CategoryController::class, 'update'])->whereNumber('category')->name('update');
            Route::delete('{category}', [CategoryController::class, 'destroy'])->whereNumber('category')->name('destroy');
            Route::post('{category}/toggle-active', [CategoryController::class, 'toggleActive'])->whereNumber('category')->name('toggleActive');
            Route::post('{category}/reorder', [CategoryController::class, 'updateReorder'])->whereNumber('category')->name('reorder');
        });

        Route::prefix('category-children')->name('category-children.')->group(function () {
            Route::get('/', [CategoryController::class, 'categoryChildrenIndex'])->name('index');
            Route::get('create', [CategoryController::class, 'categoryChildrenCreate'])->name('create');
            Route::post('/', [CategoryController::class, 'categoryChildrenStore'])->name('store');
            Route::post('{parent}/sync', [CategoryController::class, 'syncChildren'])->whereNumber('parent')->name('sync');
            Route::get('{categoryChild}/edit', [CategoryController::class, 'categoryChildrenEdit'])->whereNumber('categoryChild')->name('edit');
            Route::put('{categoryChild}', [CategoryController::class, 'categoryChildrenUpdate'])->whereNumber('categoryChild')->name('update');
            Route::delete('{categoryChild}', [CategoryController::class, 'categoryChildrenDestroy'])->whereNumber('categoryChild')->name('destroy');
            Route::delete('{categoryChild}/parents/{parent}', [CategoryController::class, 'detachChildParent'])->whereNumber('categoryChild')->whereNumber('parent')->name('detach-parent');
        });

        Route::prefix('categories/services-bulk')->name('categories.services-bulk.')->group(function () {
            Route::get('/', [CategoryServiceBulkController::class, 'index'])->name('index');
            Route::get('apply', fn () => redirect()->route('admin.categories.index'))->name('apply.get');
            Route::post('apply', [CategoryServiceBulkController::class, 'apply'])->name('apply');
        });

        Route::prefix('category-child-options')->name('category-child-options.')->group(function () {
            Route::get('bulk/edit', fn () => redirect()->route('admin.categories.services-bulk.index'))->name('bulk.edit');
            Route::post('bulk/update', [CategoryChildOptionController::class, 'bulkUpdate'])->name('bulk.update');
            Route::get('{categoryChild}', [CategoryChildOptionController::class, 'edit'])->whereNumber('categoryChild')->name('edit');
            Route::put('{categoryChild}', [CategoryChildOptionController::class, 'update'])->whereNumber('categoryChild')->name('update');
        });

        Route::post('options/bulk-assign-group', [OptionController::class, 'bulkAssignGroup'])->name('options.bulk-assign-group');
        Route::delete('options/bulk-delete', [OptionController::class, 'bulkDelete'])->name('options.bulk-delete');
        Route::resource('options', OptionController::class)->except(['show']);
        Route::resource('option-groups', OptionGroupController::class)->except(['show'])->names('option-groups');

        Route::prefix('posts')->name('posts.')->group(function () {
            Route::get('/', [PostController::class, 'index'])->name('index');
            Route::post('/', [PostController::class, 'store'])->name('store');
            Route::get('{post}', [PostController::class, 'show'])->whereNumber('post')->name('show');
            Route::get('{post}/edit', [PostController::class, 'edit'])->whereNumber('post')->name('edit');
            Route::put('{post}', [PostController::class, 'update'])->whereNumber('post')->name('update');
            Route::post('{post}/toggle-active', [PostController::class, 'toggleActive'])->whereNumber('post')->name('toggleActive');
            Route::delete('{post}', [PostController::class, 'destroy'])->name('destroy');
            Route::delete('{post}/images/{image}', [PostController::class, 'destroyImage'])->whereNumber('post')->whereNumber('image')->name('images.destroy');
            Route::delete('{post}/main-image', [PostController::class, 'destroyMainImage'])->whereNumber('post')->name('main_image.destroy');
        });

        Route::prefix('jobs')->name('jobs.')->group(function () {
            Route::get('/', [JobPostController::class, 'index'])->name('index');
            Route::post('/', [JobPostController::class, 'store'])->name('store');
            Route::get('{post}', [JobPostController::class, 'show'])->whereNumber('post')->name('show');
            Route::get('{post}/edit', [JobPostController::class, 'edit'])->whereNumber('post')->name('edit');
            Route::put('{post}', [JobPostController::class, 'update'])->whereNumber('post')->name('update');
            Route::post('{post}/toggle-active', [JobPostController::class, 'toggleActive'])->whereNumber('post')->name('toggleActive');
            Route::delete('{post}', [JobPostController::class, 'destroy'])->whereNumber('post')->name('destroy');
        });

        Route::prefix('sponsors')->name('sponsors.')->group(function () {
            Route::get('/', [SponsorController::class, 'index'])->name('index');
            Route::get('create', [SponsorController::class, 'create'])->name('create');
            Route::post('/', [SponsorController::class, 'store'])->name('store');
            Route::get('{sponsor}/edit', [SponsorController::class, 'edit'])->whereNumber('sponsor')->name('edit');
            Route::put('{sponsor}', [SponsorController::class, 'update'])->whereNumber('sponsor')->name('update');
            Route::post('{sponsor}/toggle-active', [SponsorController::class, 'toggleActive'])->whereNumber('sponsor')->name('toggleActive');
            Route::delete('{sponsor}', [SponsorController::class, 'destroy'])->whereNumber('sponsor')->name('destroy');
        });

        Route::prefix('albums')->name('albums.')->group(function () {
            Route::get('/', [AlbumController::class, 'index'])->name('index');
            Route::get('create', [AlbumController::class, 'create'])->name('create');
            Route::post('/', [AlbumController::class, 'store'])->name('store');
            Route::get('{album}', [AlbumController::class, 'show'])->whereNumber('album')->name('show');
            Route::get('{album}/edit', [AlbumController::class, 'edit'])->whereNumber('album')->name('edit');
            Route::put('{album}', [AlbumController::class, 'update'])->whereNumber('album')->name('update');
            Route::delete('{album}', [AlbumController::class, 'destroy'])->whereNumber('album')->name('destroy');
            Route::post('{album}/images/{imageId}/set-cover', [AlbumController::class, 'setCover'])->whereNumber('album')->whereNumber('imageId')->name('images.set-cover');
            Route::delete('{album}/images/{imageId}', [AlbumController::class, 'deleteImage'])->whereNumber('album')->whereNumber('imageId')->name('images.delete');
        });

        Route::prefix('business-partnerships')->name('business-partnerships.')->group(function () {
            Route::get('/', [BusinessPartnershipController::class, 'index'])->name('index');
            Route::get('create', [BusinessPartnershipController::class, 'create'])->name('create');
            Route::post('/', [BusinessPartnershipController::class, 'store'])->name('store');
            Route::get('{businessPartnership}/edit', [BusinessPartnershipController::class, 'edit'])->whereNumber('businessPartnership')->name('edit');
            Route::put('{businessPartnership}', [BusinessPartnershipController::class, 'update'])->whereNumber('businessPartnership')->name('update');
            Route::delete('{businessPartnership}', [BusinessPartnershipController::class, 'destroy'])->whereNumber('businessPartnership')->name('destroy');
            Route::post('{businessPartnership}/activate', [BusinessPartnershipController::class, 'activate'])->whereNumber('businessPartnership')->name('activate');
            Route::post('{businessPartnership}/pause', [BusinessPartnershipController::class, 'pause'])->whereNumber('businessPartnership')->name('pause');
        });

        Route::prefix('bookable-allocations')->name('bookable-allocations.')->group(function () {
            Route::get('/', [BookableAllocationController::class, 'index'])->name('index');
            Route::get('create', [BookableAllocationController::class, 'create'])->name('create');
            Route::post('/', [BookableAllocationController::class, 'store'])->name('store');
            Route::get('{bookableAllocation}/edit', [BookableAllocationController::class, 'edit'])->whereNumber('bookableAllocation')->name('edit');
            Route::put('{bookableAllocation}', [BookableAllocationController::class, 'update'])->whereNumber('bookableAllocation')->name('update');
            Route::delete('{bookableAllocation}', [BookableAllocationController::class, 'destroy'])->whereNumber('bookableAllocation')->name('destroy');
            Route::post('{bookableAllocation}/activate', [BookableAllocationController::class, 'activate'])->whereNumber('bookableAllocation')->name('activate');
            Route::post('{bookableAllocation}/stop', [BookableAllocationController::class, 'stop'])->whereNumber('bookableAllocation')->name('stop');
        });

        Route::prefix('commercial-offers')->name('commercial-offers.')->group(function () {
            Route::get('/', [CommercialOfferController::class, 'index'])->name('index');
            Route::get('create', [CommercialOfferController::class, 'create'])->name('create');
            Route::post('/', [CommercialOfferController::class, 'store'])->name('store');
            Route::get('{commercialOffer}/edit', [CommercialOfferController::class, 'edit'])->whereNumber('commercialOffer')->name('edit');
            Route::put('{commercialOffer}', [CommercialOfferController::class, 'update'])->whereNumber('commercialOffer')->name('update');
            Route::delete('{commercialOffer}', [CommercialOfferController::class, 'destroy'])->whereNumber('commercialOffer')->name('destroy');
            Route::post('{commercialOffer}/toggle', [CommercialOfferController::class, 'toggle'])->whereNumber('commercialOffer')->name('toggle');
        });

        Route::get('offer-performance', [OfferPerformanceController::class, 'index'])->name('offer-performance.index');

        Route::prefix('business-offers-subscriptions')->name('business-offers-subscriptions.')->group(function () {
            Route::get('/', [BusinessOffersSubscriptionController::class, 'form'])->name('form');
            Route::post('activate', [BusinessOffersSubscriptionController::class, 'activate'])->name('activate');
            Route::post('deactivate', [BusinessOffersSubscriptionController::class, 'deactivate'])->name('deactivate');
        });

        Route::prefix('wallet-transactions')->name('wallet-transactions.')->group(function () {
            Route::get('/', [WalletTransactionController::class, 'index'])->name('index');
            Route::get('user/{user}', [WalletTransactionController::class, 'user'])->whereNumber('user')->name('user');
            Route::get('{walletTransaction}', [WalletTransactionController::class, 'show'])->whereNumber('walletTransaction')->name('show');
        });

        Route::get('wallet-ops/recharge', [WalletOpsController::class, 'rechargeForm'])->name('wallet-ops.recharge.form');
        Route::post('wallet-ops/recharge', [WalletOpsController::class, 'recharge'])->name('wallet-ops.recharge');

        Route::prefix('guarantee-levels')->name('guarantee-levels.')->group(function () {
            Route::get('/', [GuaranteeLevelAdminController::class, 'index'])->name('index');
            Route::get('create', [GuaranteeLevelAdminController::class, 'create'])->name('create');
            Route::post('/', [GuaranteeLevelAdminController::class, 'store'])->name('store');
            Route::get('{guaranteeLevel}/edit', [GuaranteeLevelAdminController::class, 'edit'])->whereNumber('guaranteeLevel')->name('edit');
            Route::put('{guaranteeLevel}', [GuaranteeLevelAdminController::class, 'update'])->whereNumber('guaranteeLevel')->name('update');
            Route::delete('{guaranteeLevel}', [GuaranteeLevelAdminController::class, 'destroy'])->whereNumber('guaranteeLevel')->name('destroy');
            Route::post('{guaranteeLevel}/toggle', [GuaranteeLevelAdminController::class, 'toggle'])->whereNumber('guaranteeLevel')->name('toggle');
        });

        Route::prefix('guarantees')->name('guarantees.')->group(function () {
            Route::get('/', [GuaranteeAdminController::class, 'index'])->name('index');
            Route::post('{guarantee}/sync-coverage', [GuaranteeAdminController::class, 'syncCoverage'])->whereNumber('guarantee')->name('sync');
            Route::post('{guarantee}/process-grace-now', [GuaranteeAdminController::class, 'processGraceNow'])->whereNumber('guarantee')->name('process-grace');
            Route::post('{guarantee}/expire-if-due', [GuaranteeAdminController::class, 'expireIfDue'])->whereNumber('guarantee')->name('expire-if-due');
            Route::post('{guarantee}/expire-now', [GuaranteeAdminController::class, 'expireNow'])->whereNumber('guarantee')->name('expire-now');
            Route::post('{guarantee}/suspend', [GuaranteeAdminController::class, 'suspend'])->whereNumber('guarantee')->name('suspend');
            Route::post('{guarantee}/reactivate', [GuaranteeAdminController::class, 'reactivate'])->whereNumber('guarantee')->name('reactivate');
            Route::post('{guarantee}/auto-upgrade', [GuaranteeAdminController::class, 'autoUpgrade'])->whereNumber('guarantee')->name('auto-upgrade');
            Route::post('{guarantee}/auto-downgrade', [GuaranteeAdminController::class, 'autoDowngrade'])->whereNumber('guarantee')->name('auto-downgrade');
            Route::get('{guarantee}', [GuaranteeAdminController::class, 'show'])->whereNumber('guarantee')->name('show');
        });

        Route::resource('wallet-notes', WalletNoteTemplateController::class)->except(['show'])->names('wallet-notes');

        Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
            Route::get('/', [SubscriptionController::class, 'index'])->name('index');
            Route::get('{subscription}', [SubscriptionController::class, 'show'])->whereNumber('subscription')->name('show');
            Route::get('{subscription}/edit', [SubscriptionController::class, 'edit'])->whereNumber('subscription')->name('edit');
            Route::put('{subscription}', [SubscriptionController::class, 'update'])->whereNumber('subscription')->name('update');
            Route::post('{subscription}/toggle-active', [SubscriptionController::class, 'toggleActive'])->whereNumber('subscription')->name('toggle-active');
        });

        Route::prefix('payments')->name('payments.')->group(function () {
            Route::get('/', [PaymentController::class, 'index'])->name('index');
            Route::post('{paymentId}/confirm', [PaymentController::class, 'confirm'])->whereNumber('paymentId')->name('confirm');
        });

        Route::prefix('bookings')->name('bookings.')->group(function () {
            Route::get('/', [BookingController::class, 'index'])->name('index');
            Route::get('create', [BookingController::class, 'create'])->name('create');
            Route::post('/', [BookingController::class, 'store'])->name('store');
            Route::get('service-lookup', [BookingController::class, 'serviceLookup'])->name('serviceLookup');
            Route::get('bookable-items-lookup', [BookingController::class, 'bookableItemsLookup'])->name('bookableItemsLookup');
            Route::get('pricing-preview', [BookingController::class, 'pricingPreview'])->name('pricingPreview');
            Route::post('{booking}/start-confirm-client', [BookingController::class, 'startConfirmClient'])->whereNumber('booking')->name('start_confirm.client');
            Route::post('{booking}/start-confirm-business', [BookingController::class, 'startConfirmBusiness'])->whereNumber('booking')->name('start_confirm.business');
            Route::post('{booking}/deposit-confirm-client', [BookingController::class, 'depositConfirmClient'])->whereNumber('booking')->name('deposit.confirm.client');
            Route::post('{booking}/deposit-confirm-business', [BookingController::class, 'depositConfirmBusiness'])->whereNumber('booking')->name('deposit.confirm.business');
            Route::post('{booking}/start', [BookingController::class, 'start'])->whereNumber('booking')->name('start');
            Route::post('{booking}/complete', [BookingController::class, 'complete'])->whereNumber('booking')->name('complete');
            Route::post('{booking}/cancel', [BookingController::class, 'cancel'])->whereNumber('booking')->name('cancel');
            Route::post('{booking}/deposit-freeze', [BookingController::class, 'depositFreeze'])->whereNumber('booking')->name('deposit.freeze');
            Route::post('{booking}/deposit-release', [BookingController::class, 'depositRelease'])->whereNumber('booking')->name('deposit.release');
            Route::post('{booking}/deposit-refund', [BookingController::class, 'depositRefund'])->whereNumber('booking')->name('deposit.refund');
            Route::post('{booking}/deposit-dispute-open', [BookingController::class, 'depositDisputeOpen'])->whereNumber('booking')->name('deposit.dispute.open');
            Route::post('{booking}/deposit-agree-release', [BookingController::class, 'depositAgreeRelease'])->whereNumber('booking')->name('deposit.agree.release');
            Route::post('{booking}/deposit-agree-refund', [BookingController::class, 'depositAgreeRefund'])->whereNumber('booking')->name('deposit.agree.refund');
            Route::get('{booking}', [BookingController::class, 'show'])->whereNumber('booking')->name('show');
            Route::get('{booking}/edit', [BookingController::class, 'edit'])->whereNumber('booking')->name('edit');
            Route::put('{booking}', [BookingController::class, 'update'])->whereNumber('booking')->name('update');
            Route::delete('{booking}', [BookingController::class, 'destroy'])->whereNumber('booking')->name('destroy');
        });

        Route::prefix('disputes')->name('disputes.')->group(function () {
            Route::get('/', [DisputeController::class, 'index'])->name('index');
            Route::post('/', [DisputeController::class, 'store'])->name('store');
            Route::post('bookings/{booking}/open', [DisputeController::class, 'openForBooking'])->whereNumber('booking')->name('bookings.open');
            Route::get('{dispute}', [DisputeController::class, 'show'])->whereNumber('dispute')->name('show');
            Route::post('{dispute}/under-review', [DisputeController::class, 'setUnderReview'])->whereNumber('dispute')->name('under-review');
            Route::post('{dispute}/cancel', [DisputeController::class, 'cancel'])->whereNumber('dispute')->name('cancel');
            Route::post('{dispute}/close', [DisputeController::class, 'close'])->whereNumber('dispute')->name('close');
            Route::post('{dispute}/resolve-release-business', [DisputeController::class, 'resolveReleaseBusiness'])->whereNumber('dispute')->name('resolve.release-business');
            Route::post('{dispute}/resolve-refund-client', [DisputeController::class, 'resolveRefundClient'])->whereNumber('dispute')->name('resolve.refund-client');
            Route::post('{dispute}/resolve-split', [DisputeController::class, 'resolveSplit'])->whereNumber('dispute')->name('resolve.split');
            Route::post('{dispute}/resolve-no-action', [DisputeController::class, 'resolveNoAction'])->whereNumber('dispute')->name('resolve.no-action');
        });

        Route::prefix('business-service-prices')->name('business_service_prices.')->group(function () {
            Route::get('/', [BusinessServicePriceController::class, 'index'])->name('index');
            Route::get('create', [BusinessServicePriceController::class, 'create'])->name('create');
            Route::post('/', [BusinessServicePriceController::class, 'store'])->name('store');
            Route::get('{row}/edit', [BusinessServicePriceController::class, 'edit'])->whereNumber('row')->name('edit');
            Route::put('{row}', [BusinessServicePriceController::class, 'update'])->whereNumber('row')->name('update');
            Route::delete('{row}', [BusinessServicePriceController::class, 'destroy'])->whereNumber('row')->name('destroy');
        });

        Route::prefix('platform-services')->name('platform-services.')->group(function () {
            Route::get('/', [PlatformServiceController::class, 'index'])->name('index');
            Route::get('create', [PlatformServiceController::class, 'create'])->name('create');
            Route::post('/', [PlatformServiceController::class, 'store'])->name('store');
            Route::get('{platformService}/edit', [PlatformServiceController::class, 'edit'])->whereNumber('platformService')->name('edit');
            Route::put('{platformService}', [PlatformServiceController::class, 'update'])->whereNumber('platformService')->name('update');
            Route::delete('{platformService}', [PlatformServiceController::class, 'destroy'])->whereNumber('platformService')->name('destroy');
        });

        Route::prefix('platform-service-item-types')->name('platform-service-item-types.')->group(function () {
            Route::get('/', [PlatformServiceItemTypeController::class, 'index'])->name('index');
            Route::get('create', [PlatformServiceItemTypeController::class, 'create'])->name('create');
            Route::post('/', [PlatformServiceItemTypeController::class, 'store'])->name('store');
            Route::get('{platformServiceItemType}/edit', [PlatformServiceItemTypeController::class, 'edit'])->whereNumber('platformServiceItemType')->name('edit');
            Route::put('{platformServiceItemType}', [PlatformServiceItemTypeController::class, 'update'])->whereNumber('platformServiceItemType')->name('update');
            Route::delete('{platformServiceItemType}', [PlatformServiceItemTypeController::class, 'destroy'])->whereNumber('platformServiceItemType')->name('destroy');
        });

        Route::prefix('bookable-items/bulk')->name('bookable-items.bulk.')->group(function () {
            Route::get('/', [BookableItemBulkController::class, 'index'])->name('index');
            Route::post('block', [BookableItemBulkController::class, 'applyBlock'])->name('block');
            Route::post('price', [BookableItemBulkController::class, 'applyPrice'])->name('price');
        });

        Route::prefix('bookable-items')->name('bookable-items.')->group(function () {
            Route::get('/', [BookableItemController::class, 'index'])->name('index');
            Route::get('create', [BookableItemController::class, 'create'])->name('create');
            Route::post('/', [BookableItemController::class, 'store'])->name('store');
            Route::get('{bookableItem}', [BookableItemController::class, 'show'])->whereNumber('bookableItem')->name('show');
            Route::get('{bookableItem}/edit', [BookableItemController::class, 'edit'])->whereNumber('bookableItem')->name('edit');
            Route::put('{bookableItem}', [BookableItemController::class, 'update'])->whereNumber('bookableItem')->name('update');
            Route::delete('{bookableItem}', [BookableItemController::class, 'destroy'])->whereNumber('bookableItem')->name('destroy');
            Route::get('{bookableItem}/calendar', [BookableItemCalendarController::class, 'index'])->whereNumber('bookableItem')->name('calendar');
            Route::post('{bookableItem}/calendar/blocked-slot', [BookableItemCalendarController::class, 'storeBlockedSlot'])->whereNumber('bookableItem')->name('calendar.blocked-slot.store');
            Route::post('{bookableItem}/calendar/price-rule', [BookableItemCalendarController::class, 'storePriceRule'])->whereNumber('bookableItem')->name('calendar.price-rule.store');
            Route::get('{bookableItem}/blocked-slots', [BookableItemBlockedSlotController::class, 'index'])->whereNumber('bookableItem')->name('blocked-slots.index');
            Route::get('{bookableItem}/blocked-slots/create', [BookableItemBlockedSlotController::class, 'create'])->whereNumber('bookableItem')->name('blocked-slots.create');
            Route::post('{bookableItem}/blocked-slots', [BookableItemBlockedSlotController::class, 'store'])->whereNumber('bookableItem')->name('blocked-slots.store');
            Route::get('{bookableItem}/blocked-slots/{slot}/edit', [BookableItemBlockedSlotController::class, 'edit'])->whereNumber('bookableItem')->whereNumber('slot')->name('blocked-slots.edit');
            Route::put('{bookableItem}/blocked-slots/{slot}', [BookableItemBlockedSlotController::class, 'update'])->whereNumber('bookableItem')->whereNumber('slot')->name('blocked-slots.update');
            Route::delete('{bookableItem}/blocked-slots/{slot}', [BookableItemBlockedSlotController::class, 'destroy'])->whereNumber('bookableItem')->whereNumber('slot')->name('blocked-slots.destroy');
            Route::get('{bookableItem}/price-rules', [BookableItemPriceRuleController::class, 'index'])->whereNumber('bookableItem')->name('price-rules.index');
            Route::get('{bookableItem}/price-rules/create', [BookableItemPriceRuleController::class, 'create'])->whereNumber('bookableItem')->name('price-rules.create');
            Route::post('{bookableItem}/price-rules', [BookableItemPriceRuleController::class, 'store'])->whereNumber('bookableItem')->name('price-rules.store');
            Route::get('{bookableItem}/price-rules/{rule}/edit', [BookableItemPriceRuleController::class, 'edit'])->whereNumber('bookableItem')->whereNumber('rule')->name('price-rules.edit');
            Route::put('{bookableItem}/price-rules/{rule}', [BookableItemPriceRuleController::class, 'update'])->whereNumber('bookableItem')->whereNumber('rule')->name('price-rules.update');
            Route::delete('{bookableItem}/price-rules/{rule}', [BookableItemPriceRuleController::class, 'destroy'])->whereNumber('bookableItem')->whereNumber('rule')->name('price-rules.destroy');
        });

        Route::prefix('category-child-service-fees')->name('category-child-service-fees.')->group(function () {
            Route::prefix('bulk')->name('bulk.')->group(function () {
                Route::get('edit', [CategoryChildServiceFeeBulkController::class, 'edit'])->name('edit');
                Route::post('update', [CategoryChildServiceFeeBulkController::class, 'update'])->name('update');
            });
            Route::get('{categoryChild}', [CategoryChildServiceFeeController::class, 'edit'])->whereNumber('categoryChild')->name('edit');
            Route::put('{categoryChild}', [CategoryChildServiceFeeController::class, 'update'])->whereNumber('categoryChild')->name('update');
        });

        Route::patch('platform-service-fee-promotions/{platformServiceFeePromotion}/toggle', [PlatformServiceFeePromotionController::class, 'toggle'])->name('platform-service-fee-promotions.toggle');
        Route::resource('platform-service-fee-promotions', PlatformServiceFeePromotionController::class)->except(['show']);

        Route::prefix('booking-test')->name('booking-test.')->group(function () {
            Route::get('client', [BookingTestController::class, 'index'])->name('client');
            Route::get('client/children', [BookingTestController::class, 'children'])->name('client.children');
            Route::get('client/businesses', [BookingTestController::class, 'businesses'])->name('client.businesses');
            Route::get('client/bookable-items', [BookingTestController::class, 'bookableItems'])->name('client.bookable-items');
            Route::post('client/pricing-preview', [BookingTestController::class, 'pricingPreview'])->name('client.pricing-preview');
            Route::post('client/store', [BookingTestController::class, 'store'])->name('client.store');
        });
    });
});
