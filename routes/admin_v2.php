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
    CatalogAttributeController,
    CatalogManufacturerController,
    CatalogUnitController,
    CategoryChildController,
    CategoryChildOptionController,
    CategoryChildServiceFeeBulkController,
    CategoryChildServiceFeeController,
    CategoryController,
    CategoryServiceBulkController,
    BusinessTableAdminController,
    CommercialOfferController,
    DashboardController,
    DeliveryAdminController,
    DisputeController,
    GuaranteeAdminController,
    GuaranteeLevelAdminController,
    JobPostController,
    MenuItemController,
    MenuItemExtraController,
    MenuItemVariantController,
    OfferPerformanceController,
    OptionController,
    OptionGroupController,
    PaymentController,
    PlatformServiceController,
    PlatformServiceFeePromotionController,
    PlatformServiceItemGroupController,
    PlatformServiceItemTypeController,
    ServiceBranchBoardController,
    PostController,
    SponsorController,
    SubscriptionController,
    UploadController,
    CatalogBrandController,
    CatalogProductController,
    ProductCategoryChildController,
    ProductCategoryController,
    UserServiceFeeConsentController,
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

        // Shared search-as-you-type business picker for every form/filter.
        Route::get('business-lookup', \App\Http\Controllers\AdminV2\BusinessLookupController::class)->name('business-lookup');
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
            Route::get('/', [CategoryChildController::class, 'index'])->name('index');
            Route::get('create', [CategoryChildController::class, 'create'])->name('create');
            Route::post('/', [CategoryChildController::class, 'store'])->name('store');
            Route::post('{parent}/sync', [CategoryChildController::class, 'syncChildren'])->whereNumber('parent')->name('sync');
            Route::get('{categoryChild}/edit', [CategoryChildController::class, 'edit'])->whereNumber('categoryChild')->name('edit');
            Route::put('{categoryChild}', [CategoryChildController::class, 'update'])->whereNumber('categoryChild')->name('update');
            Route::delete('{categoryChild}', [CategoryChildController::class, 'destroy'])->whereNumber('categoryChild')->name('destroy');
            Route::delete('{categoryChild}/parents/{parent}', [CategoryChildController::class, 'detachParent'])->whereNumber('categoryChild')->whereNumber('parent')->name('detach-parent');
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

        Route::prefix('category-child-service-fees')->name('category-child-service-fees.')->group(function () {
            Route::get('bulk/edit', [CategoryChildServiceFeeBulkController::class, 'edit'])->name('bulk.edit');
            Route::post('bulk/update', [CategoryChildServiceFeeBulkController::class, 'update'])->name('bulk.update');
            Route::get('{categoryChild}', [CategoryChildServiceFeeController::class, 'edit'])->whereNumber('categoryChild')->name('edit');
            Route::put('{categoryChild}', [CategoryChildServiceFeeController::class, 'update'])->whereNumber('categoryChild')->name('update');
        });

        Route::prefix('user-service-fee-consents')->name('user-service-fee-consents.')->group(function () {
            Route::get('{user}/edit', [UserServiceFeeConsentController::class, 'edit'])->whereNumber('user')->name('edit');
            Route::put('{user}', [UserServiceFeeConsentController::class, 'update'])->whereNumber('user')->name('update');
            Route::post('{user}/enable-charging', [UserServiceFeeConsentController::class, 'enableCharging'])->whereNumber('user')->name('enable-charging');
            Route::post('{user}/disable-charging', [UserServiceFeeConsentController::class, 'disableCharging'])->whereNumber('user')->name('disable-charging');
        });

        Route::post('options/bulk-assign-group', [OptionController::class, 'bulkAssignGroup'])->name('options.bulk-assign-group');
        Route::delete('options/bulk-delete', [OptionController::class, 'bulkDelete'])->name('options.bulk-delete');
        Route::resource('options', OptionController::class)->except(['show']);
        Route::resource('option-groups', OptionGroupController::class)->except(['show'])->names('option-groups');

        Route::get('catalog-products', [CatalogProductController::class, 'index'])->name('catalog-products.index');
        Route::post('catalog-products/bulk-action', [CatalogProductController::class, 'bulkAction'])->name('catalog-products.bulk-action');
        Route::post('catalog-products/{product}/inline-update', [CatalogProductController::class, 'inlineUpdate'])->whereNumber('product')->name('catalog-products.inline-update');

        Route::get('product-categories', [ProductCategoryController::class, 'index'])->name('product-categories.index');
        Route::get('product-category-children', [ProductCategoryChildController::class, 'index'])->name('product-category-children.index');
        Route::get('catalog-brands', [CatalogBrandController::class, 'index'])->name('catalog-brands.index');

        Route::get('catalog-manufacturers', [CatalogManufacturerController::class, 'index'])->name('catalog-manufacturers.index');
        Route::get('catalog-units', [CatalogUnitController::class, 'index'])->name('catalog-units.index');
        Route::get('catalog-attributes', [CatalogAttributeController::class, 'index'])->name('catalog-attributes.index');

        Route::resource('platform-services', PlatformServiceController::class)->except(['show'])->names('platform-services');
        Route::post('platform-services/{platformService}/toggle-active', [PlatformServiceController::class, 'toggleActive'])->whereNumber('platformService')->name('platform-services.toggle-active');

        Route::resource('platform-service-fee-promotions', PlatformServiceFeePromotionController::class)->except(['show'])->names('platform-service-fee-promotions');
        Route::post('platform-service-fee-promotions/{platformServiceFeePromotion}/toggle', [PlatformServiceFeePromotionController::class, 'toggle'])->whereNumber('platformServiceFeePromotion')->name('platform-service-fee-promotions.toggle');

        Route::post('platform-service-item-groups/{platformServiceItemGroup}/toggle-active', [PlatformServiceItemGroupController::class, 'toggleActive'])->whereNumber('platformServiceItemGroup')->name('platform-service-item-groups.toggle-active');
        Route::post('platform-service-item-groups/{platformServiceItemGroup}/types/attach', [PlatformServiceItemGroupController::class, 'attachType'])->whereNumber('platformServiceItemGroup')->name('platform-service-item-groups.types.attach');
        Route::post('platform-service-item-groups/{platformServiceItemGroup}/types/detach', [PlatformServiceItemGroupController::class, 'detachType'])->whereNumber('platformServiceItemGroup')->name('platform-service-item-groups.types.detach');
        Route::post('platform-service-item-groups/{platformServiceItemGroup}/types/create', [PlatformServiceItemGroupController::class, 'storeType'])->whereNumber('platformServiceItemGroup')->name('platform-service-item-groups.types.create');
        Route::resource('platform-service-item-groups', PlatformServiceItemGroupController::class)->except(['show'])->names('platform-service-item-groups');

        Route::prefix('service-branches')->name('service-branches.')->group(function () {
            Route::get('/', [ServiceBranchBoardController::class, 'index'])->name('index');
            Route::post('toggle', [ServiceBranchBoardController::class, 'toggle'])->name('toggle');
            Route::post('save', [ServiceBranchBoardController::class, 'save'])->name('save');
            Route::post('branches', [ServiceBranchBoardController::class, 'storeBranch'])->name('branches.store');
            Route::post('branches/{platformServiceItemGroup}/rename', [ServiceBranchBoardController::class, 'renameBranch'])->whereNumber('platformServiceItemGroup')->name('branches.rename');
            Route::delete('branches/{platformServiceItemGroup}', [ServiceBranchBoardController::class, 'destroyBranch'])->whereNumber('platformServiceItemGroup')->name('branches.destroy');
        });

        Route::resource('platform-service-item-types', PlatformServiceItemTypeController::class)->except(['show'])->names('platform-service-item-types');
        Route::get('business-service-prices/business-lookup', [BusinessServicePriceController::class, 'businessLookup'])->name('business_service_prices.business-lookup');
        Route::get('business-service-prices/item-types-lookup', [BusinessServicePriceController::class, 'itemTypesLookup'])->name('business_service_prices.item-types-lookup');
        Route::resource('business-service-prices', BusinessServicePriceController::class)->except(['show'])->names('business_service_prices');

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

        Route::resource('commercial-offers', CommercialOfferController::class)->except(['show'])->names('commercial-offers');
        Route::post('commercial-offers/{commercialOffer}/toggle', [CommercialOfferController::class, 'toggle'])->whereNumber('commercialOffer')->name('commercial-offers.toggle');
        Route::get('offer-performance', [OfferPerformanceController::class, 'index'])->name('offer-performance.index');
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
            Route::get('item-types-lookup', [BookableItemController::class, 'itemTypesLookup'])->name('item-types-lookup');
            Route::get('business-lookup', [BookableItemController::class, 'businessLookup'])->name('business-lookup');

            Route::get('{bookableItem}/calendar', [BookableItemCalendarController::class, 'index'])->whereNumber('bookableItem')->name('calendar');
            Route::post('{bookableItem}/calendar/blocked-slots', [BookableItemCalendarController::class, 'storeBlockedSlot'])->whereNumber('bookableItem')->name('calendar.blocked-slot.store');
            Route::post('{bookableItem}/calendar/price-rules', [BookableItemCalendarController::class, 'storePriceRule'])->whereNumber('bookableItem')->name('calendar.price-rule.store');

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

        Route::prefix('menu-items')->name('menu-items.')->group(function () {
            Route::get('/', [MenuItemController::class, 'index'])->name('index');
            Route::get('create', [MenuItemController::class, 'create'])->name('create');
            Route::post('/', [MenuItemController::class, 'store'])->name('store');
            Route::get('{menuItem}/edit', [MenuItemController::class, 'edit'])->whereNumber('menuItem')->name('edit');
            Route::put('{menuItem}', [MenuItemController::class, 'update'])->whereNumber('menuItem')->name('update');
            Route::delete('{menuItem}', [MenuItemController::class, 'destroy'])->whereNumber('menuItem')->name('destroy');

            Route::prefix('{menuItem}/variants')->whereNumber('menuItem')->name('variants.')->group(function () {
                Route::post('/', [MenuItemVariantController::class, 'store'])->name('store');
                Route::put('{variant}', [MenuItemVariantController::class, 'update'])->whereNumber('variant')->name('update');
                Route::delete('{variant}', [MenuItemVariantController::class, 'destroy'])->whereNumber('variant')->name('destroy');
            });

            Route::prefix('{menuItem}/extras')->whereNumber('menuItem')->name('extras.')->group(function () {
                Route::post('/', [MenuItemExtraController::class, 'store'])->name('store');
                Route::put('{extra}', [MenuItemExtraController::class, 'update'])->whereNumber('extra')->name('update');
                Route::delete('{extra}', [MenuItemExtraController::class, 'destroy'])->whereNumber('extra')->name('destroy');
            });
        });

        // Connected delivery loop oversight (drivers + success ledger).
        Route::prefix('delivery')->name('delivery.')->group(function () {
            Route::get('drivers', [DeliveryAdminController::class, 'drivers'])->name('drivers.index');
            Route::post('drivers/{driver}/toggle', [DeliveryAdminController::class, 'toggle'])->whereNumber('driver')->name('drivers.toggle');
            Route::get('completions', [DeliveryAdminController::class, 'completions'])->name('completions.index');
        });

        // Restaurant tables (table QR) read oversight.
        Route::get('business-tables', [BusinessTableAdminController::class, 'index'])->name('business-tables.index');

        Route::prefix('wallet-transactions')->name('wallet-transactions.')->group(function () {
            Route::get('/', [WalletTransactionController::class, 'index'])->name('index');
            Route::get('user/{user}', [WalletTransactionController::class, 'user'])->whereNumber('user')->name('user');
            Route::get('{walletTransaction}', [WalletTransactionController::class, 'show'])->whereNumber('walletTransaction')->name('show');
        });

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
            Route::post('{guarantee}/unlock-to-balance', [GuaranteeAdminController::class, 'unlockToBalance'])->whereNumber('guarantee')->name('unlock-to-balance');
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
            Route::post('{dispute}/under-review', [DisputeController::class, 'setUnderReview'])->whereNumber('dispute')->name('under-review');
            Route::post('{dispute}/close', [DisputeController::class, 'close'])->whereNumber('dispute')->name('close');
            Route::post('{dispute}/resolve/release-business', [DisputeController::class, 'resolveReleaseBusiness'])->whereNumber('dispute')->name('resolve.release-business');
            Route::post('{dispute}/resolve/refund-client', [DisputeController::class, 'resolveRefundClient'])->whereNumber('dispute')->name('resolve.refund-client');
            Route::post('{dispute}/resolve/split', [DisputeController::class, 'resolveSplit'])->whereNumber('dispute')->name('resolve.split');
            Route::post('{dispute}/resolve/no-action', [DisputeController::class, 'resolveNoAction'])->whereNumber('dispute')->name('resolve.no-action');
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

        Route::resource('posts', PostController::class)->names('posts');
        Route::post('posts/{post}/toggle-active', [PostController::class, 'toggleActive'])->whereNumber('post')->name('posts.toggleActive');
        Route::delete('posts/{post}/main-image', [PostController::class, 'destroyMainImage'])->whereNumber('post')->name('posts.main_image.destroy');
        Route::delete('posts/{post}/images/{image}', [PostController::class, 'destroyImage'])->whereNumber('post')->whereNumber('image')->name('posts.images.destroy');

        Route::resource('jobs', JobPostController::class)->names('jobs');

        Route::resource('sponsors', SponsorController::class)->except(['show'])->names('sponsors');
        Route::post('sponsors/{sponsor}/toggle-active', [SponsorController::class, 'toggleActive'])->whereNumber('sponsor')->name('sponsors.toggleActive');

        Route::resource('albums', AlbumController::class)->names('albums');
        Route::post('albums/{album}/images/{imageId}/set-cover', [AlbumController::class, 'setCover'])->whereNumber('album')->whereNumber('imageId')->name('albums.images.set-cover');
        Route::delete('albums/{album}/images/{imageId}', [AlbumController::class, 'deleteImage'])->whereNumber('album')->whereNumber('imageId')->name('albums.images.delete');
    });
});
