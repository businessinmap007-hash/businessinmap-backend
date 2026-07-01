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
    CategoryController,
    CategoryServiceBulkController,
    CommercialOfferController,
    DashboardController,
    DisputeController,
    GuaranteeAdminController,
    GuaranteeLevelAdminController,
    JobPostController,
    NotificationCenterAdminController,
    OfferPerformanceController,
    OptionController,
    OptionGroupController,
    PaymentController,
    PlatformServiceController,
    PlatformServiceFeePromotionController,
    PlatformServiceItemTypeController,
    PostController,
    SponsorController,
    StoreCatalogItemController,
    SubscriptionController,
    UploadController,
    Users\UserController,
    WalletNoteTemplateController,
    WalletOpsController,
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

        Route::resource('platform-services', PlatformServiceController::class)->except(['show'])->names('platform-services');
        Route::post('platform-services/{platformService}/toggle-active', [PlatformServiceController::class, 'toggleActive'])->whereNumber('platformService')->name('platform-services.toggle-active');

        Route::resource('platform-service-fee-promotions', PlatformServiceFeePromotionController::class)->except(['show'])->names('platform-service-fee-promotions');
        Route::post('platform-service-fee-promotions/{platformServiceFeePromotion}/toggle', [PlatformServiceFeePromotionController::class, 'toggle'])->whereNumber('platformServiceFeePromotion')->name('platform-service-fee-promotions.toggle');

        Route::resource('platform-service-item-types', PlatformServiceItemTypeController::class)->except(['show'])->names('platform-service-item-types');
        Route::resource('business-service-prices', BusinessServicePriceController::class)->except(['show'])->names('business_service_prices');

        Route::prefix('store-catalog-items')->name('store-catalog-items.')->group(function () {
            Route::get('/', [StoreCatalogItemController::class, 'index'])->name('index');
            Route::post('/', [StoreCatalogItemController::class, 'store'])->name('store');
            Route::delete('{id}', [StoreCatalogItemController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });

        Route::prefix('notification-center')->name('notification-center.')->group(function () {
            Route::get('/', [NotificationCenterAdminController::class, 'index'])->name('index');
            Route::post('sync-offers', [NotificationCenterAdminController::class, 'syncOffers'])->name('sync-offers');
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
            Route::post('{bookableAllocation}/activate', [BusinessPartnershipController::class, 'activate'])->whereNumber('bookableAllocation')->name('activate');
            Route::post('{bookableAllocation}/stop', [BookableAllocationController::class, 'stop'])->whereNumber('bookableAllocation')->name('stop');
        });

        Route::resource('commercial-offers', CommercialOfferController::class)->except(['show'])->names('commercial-offers');
        Route::post('commercial-offers/{commercialOffer}/toggle', [CommercialOfferController::class, 'toggle'])->whereNumber('commercialOffer')->name('commercial-offers.toggle');
        Route::get('offer-performance', [OfferPerformanceController::class, 'index'])->name('offer-performance.index');
        Route::get('offer-follows', fn () => redirect()->route('admin.notification-center.index'))->name('offer-follows.index');
        Route::get('offer-boost-packages', fn () => redirect()->route('admin.business-offers-subscriptions.form'))->name('offer-boost-packages.index');
        Route::get('offer-boost-packages/boost', fn () => redirect()->route('admin.business-offers-subscriptions.form'))->name('offer-boost-packages.boost-form');

        Route::prefix('business-offers-subscriptions')->name('business-offers-subscriptions.')->group(function () {
            Route::get('/', [BusinessOffersSubscriptionController::class, 'form'])->name('form');
            Route::post('activate', [BusinessOffersSubscriptionController::class, 'activate'])->name('activate');
            Route::post('deactivate', [BusinessOffersSubscriptionController::class, 'deactivate'])->name('deactivate');
        });

        Route::prefix('bookable-items')->name('bookable-items.')->group(function () {
            Route::get('bulk', [BookableItemBulkController::class, 'index'])->name('bulk.index');
            Route::post('bulk/block', [BookableItemBulkController::class, 'applyBlock'])->name('bulk.block');
            Route::post('bulk/price', [BookableItemBulkController::class, 'applyPrice'])->name('bulk.price');

            Route::get('/', [BookableItemController::class, 'index'])->name('index');
            Route::get('create', [BookableItemController::class, 'create'])->name('create');
            Route::post('/', [BookableItemController::class, 'store'])->name('store');

            Route::get('{bookableItem}/calendar', [BookableItemCalendarController::class, 'index'])->whereNumber('bookableItem')->name('calendar');
            Route::post('{bookableItem}/calendar/blocked-slots', [BookableItemCalendarController::class, 'storeBlockedSlot'])->whereNumber('bookableItem')->name('calendar.blocked-slots.store');
            Route::post('{bookableItem}/calendar/price-rules', [BookableItemCalendarController::class, 'storePriceRule'])->whereNumber('bookableItem')->name('calendar.price-rules.store');

            Route::prefix('{bookableItem}/blocked-slots')->whereNumber('bookableItem')->name('blocked-slots.')->group(function () {
                Route::get('/', [BookableItemBlockedSlotController::class, 'index'])->name('index');
                Route::get('create', [BookableItemBlockedSlotController::class, 'create'])->name('create');
                Route::post('/', [BookableItemBlockedSlotController::class, 'store'])->name('store');
                Route::get('{slot}/edit', [BookableItemBlockedSlotController::class, 'edit'])->whereNumber('slot')->name('edit');
                Route::put('{slot}', [BookableItemBlockedSlotController::class, 'update'])->whereNumber('slot')->name('update');
                Route::delete('{slot}', [BookableItemBlockedSlotController::class, 'destroy'])->whereNumber('slot')->name('destroy');
            });

            Route::prefix('{bookableItem}/price-rules')->whereNumber('bookableItem')->name('price-rules.')->group(function () {
                Route::get('/', [BookableItemPriceRuleController::class, 'index'])->name('index');
                Route::get('create', [BookableItemPriceRuleController::class, 'create'])->name('create');
                Route::post('/', [BookableItemPriceRuleController::class, 'store'])->name('store');
                Route::get('{rule}/edit', [BookableItemPriceRuleController::class, 'edit'])->whereNumber('rule')->name('edit');
                Route::put('{rule}', [BookableItemPriceRuleController::class, 'update'])->whereNumber('rule')->name('update');
                Route::delete('{rule}', [BookableItemPriceRuleController::class, 'destroy'])->whereNumber('rule')->name('destroy');
            });

            Route::get('{bookableItem}/edit', [BookableItemController::class, 'edit'])->whereNumber('bookableItem')->name('edit');
            Route::put('{bookableItem}', [BookableItemController::class, 'update'])->whereNumber('bookableItem')->name('update');
            Route::delete('{bookableItem}', [BookableItemController::class, 'destroy'])->whereNumber('bookableItem')->name('destroy');
        });

        Route::prefix('wallet-transactions')->name('wallet-transactions.')->group(function () {
            Route::get('/', [WalletTransactionController::class, 'index'])->name('index');
            Route::get('user/{user}', [WalletTransactionController::class, 'user'])->whereNumber('user')->name('user');
            Route::get('{walletTransaction}', [WalletTransactionController::class, 'show'])->whereNumber('walletTransaction')->name('show');
        });
        Route::get('wallet-overview', fn () => redirect()->route('admin.wallet-transactions.index'))->name('wallet-overview.index');

        Route::get('wallet-ops/recharge', [WalletOpsController::class, 'rechargeForm'])->name('wallet-ops.recharge.form');
        Route::get('wallet-ops/users/search', [WalletOpsController::class, 'searchUsersJson'])->name('wallet-ops.users.search');
        Route::post('wallet-ops/recharge', [WalletOpsController::class, 'recharge'])->name('wallet-ops.recharge');
        Route::post('wallet-ops/activate-guarantee', [WalletOpsController::class, 'activateGuarantee'])->name('wallet-ops.activate-guarantee');

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

        Route::prefix('disputes')->name('disputes.')->group(function () {
            Route::get('/', [DisputeController::class, 'index'])->name('index');
            Route::get('{dispute}', [DisputeController::class, 'show'])->whereNumber('dispute')->name('show');
            Route::post('{dispute}/under-review', [DisputeController::class, 'underReview'])->whereNumber('dispute')->name('under-review');
            Route::post('{dispute}/close', [DisputeController::class, 'close'])->whereNumber('dispute')->name('close');
        });

        Route::prefix('bookings')->name('bookings.')->group(function () {
            Route::get('/', [BookingController::class, 'index'])->name('index');
            Route::get('create', [BookingController::class, 'create'])->name('create');
            Route::post('/', [BookingController::class, 'store'])->name('store');
            Route::get('service-lookup', [BookingController::class, 'serviceLookup'])->name('serviceLookup');
            Route::get('bookable-items-lookup', [BookingController::class, 'bookableItemsLookup'])->name('bookableItemsLookup');
            Route::get('pricing-preview', [BookingController::class, 'pricingPreview'])->name('pricingPreview');
            Route::get('{booking}', [BookingController::class, 'show'])->whereNumber('booking')->name('show');
            Route::get('{booking}/edit', [BookingController::class, 'edit'])->whereNumber('booking')->name('edit');
            Route::put('{booking}', [BookingController::class, 'update'])->whereNumber('booking')->name('update');
            Route::delete('{booking}', [BookingController::class, 'destroy'])->whereNumber('booking')->name('destroy');
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
        });

        Route::resource('posts', PostController::class)->except(['show'])->names('posts');
        Route::resource('jobs', JobPostController::class)->except(['show'])->names('jobs');
        Route::resource('sponsors', SponsorController::class)->except(['show'])->names('sponsors');
        Route::resource('albums', AlbumController::class)->names('albums');
    });
});
