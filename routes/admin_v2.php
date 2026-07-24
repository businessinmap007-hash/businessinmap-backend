<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AdminV2\{
    AdminRoleController,
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
    ArbitratorController,
    DisputeController,
    DisputeFeeController,
    DisputeRuleController,
    FineController,
    FraudFlagController,
    GuaranteeAdminController,
    GuaranteeLevelAdminController,
    HeldDeletionController,
    JobFollowController,
    JobPostController,
    TripScheduleAdminController,
    MenuItemController,
    MenuItemExtraController,
    MenuItemVariantController,
    NotificationCenterAdminController,
    OfferBoostPackageController,
    OfferFollowDashboardController,
    OfferPerformanceController,
    OptionController,
    OptionGroupController,
    PaymentController,
    PlatformServiceController,
    PlatformServiceFeePromotionController,
    ServiceFeeRuleController,
    PlatformServiceItemGroupController,
    PlatformServiceItemTypeController,
    ServiceBranchBoardController,
    PostController,
    SponsorController,
    SubscriptionController,
    UploadController,
    CatalogBrandController,
    CatalogProductController,
    PaymentSettingsController,
    MerchantPaymentAccountController,
    MerchantAccountRequestController,
    PushSettingsController,
    ProductCategoryChildController,
    ProductCategoryController,
    UserServiceFeeConsentController,
    Users\UserController,
    WalletNoteTemplateController,
    WalletOpsController,
    WalletOverviewController,
    WalletTopupAdminController,
    MerchantPaymentAdminController,
    WalletTransactionController
};
use App\Support\AdminAbility;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->name('login.post');
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');

    // Language toggle. Outside the admin.v2 group on purpose — see LocaleController.
    Route::get('locale/{locale}', [\App\Http\Controllers\AdminV2\LocaleController::class, 'switch'])
        ->name('locale.switch');

    Route::post('payments/callback/success', [PaymentController::class, 'callbackSuccess'])->name('payments.callback.success');

    // BIM-14.1 — every route below carries `admin.v2` (are you an admin?) AND a
    // `can:` ability (which admin are you?). AdminAbilityCoverageTest enforces
    // that pairing, so a new route added without an ability fails the suite
    // instead of shipping open to anyone who can reach the panel.
    Route::middleware(['admin.v2'])->group(function () {
        Route::middleware('can:' . AdminAbility::ACCESS)->group(function () {
            Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

            // Shared search-as-you-type business picker for every form/filter.
            Route::get('business-lookup', \App\Http\Controllers\AdminV2\BusinessLookupController::class)->name('business-lookup');
            Route::post('upload/image', [UploadController::class, 'store'])->name('upload.image');
        });

        Route::prefix('users')->name('users.')->middleware('can:' . AdminAbility::USERS)->group(function () {
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
            Route::post('{user}/ban', [UserController::class, 'ban'])->whereNumber('user')->name('ban');
            Route::post('{user}/unban', [UserController::class, 'unban'])->whereNumber('user')->name('unban');
        });

        // Suspected-fraud review (fines system, stage C). USERS-gated: it leads
        // to a ban or a fine, and only ever suggests — the scan raises flags,
        // the admin acts. Read + dismiss only.
        Route::prefix('fraud-flags')->name('fraud-flags.')->middleware('can:' . AdminAbility::USERS)->group(function () {
            Route::get('/', [FraudFlagController::class, 'index'])->name('index');
            Route::post('scan', [FraudFlagController::class, 'scan'])->name('scan');
            Route::post('{flag}/dismiss', [FraudFlagController::class, 'dismiss'])->whereNumber('flag')->name('dismiss');
        });

        Route::prefix('categories')->name('categories.')->middleware('can:' . AdminAbility::CATALOG)->group(function () {
            Route::get('/', [CategoryController::class, 'index'])->name('index');
            Route::get('create', [CategoryController::class, 'create'])->name('create');
            Route::post('/', [CategoryController::class, 'store'])->name('store');
            Route::get('{category}/edit', [CategoryController::class, 'edit'])->whereNumber('category')->name('edit');
            Route::put('{category}', [CategoryController::class, 'update'])->whereNumber('category')->name('update');
            Route::delete('{category}', [CategoryController::class, 'destroy'])->whereNumber('category')->name('destroy');
            Route::post('{category}/toggle-active', [CategoryController::class, 'toggleActive'])->whereNumber('category')->name('toggleActive');
            Route::post('{category}/reorder', [CategoryController::class, 'updateReorder'])->whereNumber('category')->name('reorder');
        });

        Route::prefix('category-children')->name('category-children.')->middleware('can:' . AdminAbility::CATALOG)->group(function () {
            Route::get('/', [CategoryChildController::class, 'index'])->name('index');
            Route::get('create', [CategoryChildController::class, 'create'])->name('create');
            Route::post('/', [CategoryChildController::class, 'store'])->name('store');
            Route::post('{parent}/sync', [CategoryChildController::class, 'syncChildren'])->whereNumber('parent')->name('sync');
            Route::get('{categoryChild}/edit', [CategoryChildController::class, 'edit'])->whereNumber('categoryChild')->name('edit');
            Route::put('{categoryChild}', [CategoryChildController::class, 'update'])->whereNumber('categoryChild')->name('update');
            Route::delete('{categoryChild}', [CategoryChildController::class, 'destroy'])->whereNumber('categoryChild')->name('destroy');
            Route::delete('{categoryChild}/parents/{parent}', [CategoryChildController::class, 'detachParent'])->whereNumber('categoryChild')->whereNumber('parent')->name('detach-parent');
        });

        Route::prefix('categories/services-bulk')->name('categories.services-bulk.')->middleware('can:' . AdminAbility::CATALOG)->group(function () {
            Route::get('/', [CategoryServiceBulkController::class, 'index'])->name('index');
            Route::get('apply', fn () => redirect()->route('admin.categories.index'))->name('apply.get');
            Route::post('apply', [CategoryServiceBulkController::class, 'apply'])->name('apply');
        });

        Route::prefix('category-child-options')->name('category-child-options.')->middleware('can:' . AdminAbility::CATALOG)->group(function () {
            // Was a closure redirecting to categories.services-bulk, which made the
            // sidebar's «خيارات التصنيفات الفرعية» open «Bulk Services + Fees» — a
            // different screen entirely — while CategoryChildOptionController@bulkEdit
            // and its view sat unreachable. Options are the ATTRIBUTES axis, not the
            // services one (see docs/architecture-blueprint.md §3.1); the two must not
            // share a screen.
            Route::get('bulk/edit', [CategoryChildOptionController::class, 'bulkEdit'])->name('bulk.edit');
            Route::post('bulk/update', [CategoryChildOptionController::class, 'bulkUpdate'])->name('bulk.update');
            Route::get('{categoryChild}', [CategoryChildOptionController::class, 'edit'])->whereNumber('categoryChild')->name('edit');
            Route::put('{categoryChild}', [CategoryChildOptionController::class, 'update'])->whereNumber('categoryChild')->name('update');
        });

        Route::prefix('category-child-service-fees')->name('category-child-service-fees.')->middleware('can:' . AdminAbility::FEES)->group(function () {
            Route::get('bulk/edit', [CategoryChildServiceFeeBulkController::class, 'edit'])->name('bulk.edit');
            Route::post('bulk/update', [CategoryChildServiceFeeBulkController::class, 'update'])->name('bulk.update');
            Route::get('{categoryChild}', [CategoryChildServiceFeeController::class, 'edit'])->whereNumber('categoryChild')->name('edit');
            Route::put('{categoryChild}', [CategoryChildServiceFeeController::class, 'update'])->whereNumber('categoryChild')->name('update');
        });

        Route::prefix('user-service-fee-consents')->name('user-service-fee-consents.')->middleware('can:' . AdminAbility::FEES)->group(function () {
            Route::get('{user}/edit', [UserServiceFeeConsentController::class, 'edit'])->whereNumber('user')->name('edit');
            Route::put('{user}', [UserServiceFeeConsentController::class, 'update'])->whereNumber('user')->name('update');
            Route::post('{user}/enable-charging', [UserServiceFeeConsentController::class, 'enableCharging'])->whereNumber('user')->name('enable-charging');
            Route::post('{user}/disable-charging', [UserServiceFeeConsentController::class, 'disableCharging'])->whereNumber('user')->name('disable-charging');
        });

        Route::middleware('can:' . AdminAbility::CATALOG)->group(function () {
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

            Route::post('platform-service-item-groups/{platformServiceItemGroup}/toggle-active', [PlatformServiceItemGroupController::class, 'toggleActive'])->whereNumber('platformServiceItemGroup')->name('platform-service-item-groups.toggle-active');
            Route::post('platform-service-item-groups/{platformServiceItemGroup}/types/attach', [PlatformServiceItemGroupController::class, 'attachType'])->whereNumber('platformServiceItemGroup')->name('platform-service-item-groups.types.attach');
            Route::post('platform-service-item-groups/{platformServiceItemGroup}/types/detach', [PlatformServiceItemGroupController::class, 'detachType'])->whereNumber('platformServiceItemGroup')->name('platform-service-item-groups.types.detach');
            Route::post('platform-service-item-groups/{platformServiceItemGroup}/types/create', [PlatformServiceItemGroupController::class, 'storeType'])->whereNumber('platformServiceItemGroup')->name('platform-service-item-groups.types.create');
            Route::resource('platform-service-item-groups', PlatformServiceItemGroupController::class)->except(['show'])->names('platform-service-item-groups');
        });

        // What the platform charges. Separate from CATALOG: deciding a fee is a
        // different job from arranging the taxonomy it hangs on.
        Route::middleware('can:' . AdminAbility::FEES)->group(function () {
            Route::resource('platform-service-fee-promotions', PlatformServiceFeePromotionController::class)->except(['show'])->names('platform-service-fee-promotions');
            Route::post('platform-service-fee-promotions/{platformServiceFeePromotion}/toggle', [PlatformServiceFeePromotionController::class, 'toggle'])->whereNumber('platformServiceFeePromotion')->name('platform-service-fee-promotions.toggle');

            // BIM-3.5 — dynamic fee rules (the policy layer between the static base
            // fee and the promotions above).
            Route::resource('service-fee-rules', ServiceFeeRuleController::class)->except(['show'])->names('service-fee-rules');
            Route::post('service-fee-rules/{serviceFeeRule}/toggle', [ServiceFeeRuleController::class, 'toggle'])->whereNumber('serviceFeeRule')->name('service-fee-rules.toggle');
        });

        Route::prefix('service-branches')->name('service-branches.')->middleware('can:' . AdminAbility::CATALOG)->group(function () {
            Route::get('/', [ServiceBranchBoardController::class, 'index'])->name('index');
            Route::post('toggle', [ServiceBranchBoardController::class, 'toggle'])->name('toggle');
            Route::post('save', [ServiceBranchBoardController::class, 'save'])->name('save');
            Route::post('branches', [ServiceBranchBoardController::class, 'storeBranch'])->name('branches.store');
            Route::post('branches/{platformServiceItemGroup}/rename', [ServiceBranchBoardController::class, 'renameBranch'])->whereNumber('platformServiceItemGroup')->name('branches.rename');
            Route::delete('branches/{platformServiceItemGroup}', [ServiceBranchBoardController::class, 'destroyBranch'])->whereNumber('platformServiceItemGroup')->name('branches.destroy');
        });

        Route::resource('platform-service-item-types', PlatformServiceItemTypeController::class)->except(['show'])->names('platform-service-item-types')->middleware('can:' . AdminAbility::CATALOG);

        Route::middleware('can:' . AdminAbility::COMMERCE)->group(function () {
            Route::get('business-service-prices/business-lookup', [BusinessServicePriceController::class, 'businessLookup'])->name('business_service_prices.business-lookup');
            Route::get('business-service-prices/item-types-lookup', [BusinessServicePriceController::class, 'itemTypesLookup'])->name('business_service_prices.item-types-lookup');
            Route::resource('business-service-prices', BusinessServicePriceController::class)->except(['show'])->names('business_service_prices');
        });

        Route::prefix('business-partnerships')->name('business-partnerships.')->middleware('can:' . AdminAbility::COMMERCE)->group(function () {
            Route::get('/', [BusinessPartnershipController::class, 'index'])->name('index');
            Route::get('create', [BusinessPartnershipController::class, 'create'])->name('create');
            Route::post('/', [BusinessPartnershipController::class, 'store'])->name('store');
            Route::get('{businessPartnership}/edit', [BusinessPartnershipController::class, 'edit'])->whereNumber('businessPartnership')->name('edit');
            Route::put('{businessPartnership}', [BusinessPartnershipController::class, 'update'])->whereNumber('businessPartnership')->name('update');
            Route::delete('{businessPartnership}', [BusinessPartnershipController::class, 'destroy'])->whereNumber('businessPartnership')->name('destroy');
            Route::post('{businessPartnership}/activate', [BusinessPartnershipController::class, 'activate'])->whereNumber('businessPartnership')->name('activate');
            Route::post('{businessPartnership}/pause', [BusinessPartnershipController::class, 'pause'])->whereNumber('businessPartnership')->name('pause');
        });

        Route::prefix('bookable-allocations')->name('bookable-allocations.')->middleware('can:' . AdminAbility::COMMERCE)->group(function () {
            Route::get('/', [BookableAllocationController::class, 'index'])->name('index');
            Route::get('create', [BookableAllocationController::class, 'create'])->name('create');
            Route::post('/', [BookableAllocationController::class, 'store'])->name('store');
            Route::get('{bookableAllocation}/edit', [BookableAllocationController::class, 'edit'])->whereNumber('bookableAllocation')->name('edit');
            Route::put('{bookableAllocation}', [BookableAllocationController::class, 'update'])->whereNumber('bookableAllocation')->name('update');
            Route::delete('{bookableAllocation}', [BookableAllocationController::class, 'destroy'])->whereNumber('bookableAllocation')->name('destroy');
            Route::post('{bookableAllocation}/activate', [BookableAllocationController::class, 'activate'])->whereNumber('bookableAllocation')->name('activate');
            Route::post('{bookableAllocation}/stop', [BookableAllocationController::class, 'stop'])->whereNumber('bookableAllocation')->name('stop');
        });

        Route::middleware('can:' . AdminAbility::COMMERCE)->group(function () {
            Route::resource('commercial-offers', CommercialOfferController::class)->except(['show'])->names('commercial-offers');
            Route::post('commercial-offers/{commercialOffer}/toggle', [CommercialOfferController::class, 'toggle'])->whereNumber('commercialOffer')->name('commercial-offers.toggle');
            Route::get('offer-performance', [OfferPerformanceController::class, 'index'])->name('offer-performance.index');
            Route::get('offer-boost-packages', fn () => redirect()->route('admin.business-offers-subscriptions.form'))->name('offer-boost-packages.index');
            Route::get('offer-boost-packages/boost', fn () => redirect()->route('admin.business-offers-subscriptions.form'))->name('offer-boost-packages.boost-form');
        });

        Route::prefix('business-offers-subscriptions')->name('business-offers-subscriptions.')->middleware('can:' . AdminAbility::COMMERCE)->group(function () {
            Route::get('/', [BusinessOffersSubscriptionController::class, 'form'])->name('form');
            Route::post('activate', [BusinessOffersSubscriptionController::class, 'activate'])->name('activate');
            Route::post('deactivate', [BusinessOffersSubscriptionController::class, 'deactivate'])->name('deactivate');
        });

        Route::prefix('bookable-items')->name('bookable-items.')->middleware('can:' . AdminAbility::OPERATIONS)->group(function () {
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

        Route::prefix('menu-items')->name('menu-items.')->middleware('can:' . AdminAbility::OPERATIONS)->group(function () {
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
        Route::prefix('delivery')->name('delivery.')->middleware('can:' . AdminAbility::OPERATIONS)->group(function () {
            Route::get('drivers', [DeliveryAdminController::class, 'drivers'])->name('drivers.index');
            Route::post('drivers/{driver}/toggle', [DeliveryAdminController::class, 'toggle'])->whereNumber('driver')->name('drivers.toggle');
            Route::get('completions', [DeliveryAdminController::class, 'completions'])->name('completions.index');
        });

        // Restaurant tables (table QR) read oversight.
        Route::get('business-tables', [BusinessTableAdminController::class, 'index'])->middleware('can:' . AdminAbility::OPERATIONS)->name('business-tables.index');

        // Everything that moves money, or authorises its movement, behind one
        // ability — this is the boundary the whole exercise exists to draw.
        Route::middleware('can:' . AdminAbility::MONEY)->group(function () {
            // Wallet top-ups (money-in) oversight for reconciliation.
            Route::get('wallet-topups', [WalletTopupAdminController::class, 'index'])->name('wallet-topups.index');

            // Customer→merchant payments (money-in that settles to the merchant).
            Route::get('merchant-payments', [MerchantPaymentAdminController::class, 'index'])->name('merchant-payments.index');

            // Deletions the day-31 sweep refused. Gated on MONEY, not USERS:
            // both actions move money — finalizing escheats the balance to the
            // treasury, restoring unfreezes a blocked wallet.
            Route::prefix('held-deletions')->name('held-deletions.')->group(function () {
                Route::get('/', [HeldDeletionController::class, 'index'])->name('index');
                Route::post('{user}/finalize', [HeldDeletionController::class, 'finalize'])->whereNumber('user')->name('finalize');
                Route::post('{user}/restore', [HeldDeletionController::class, 'restore'])->whereNumber('user')->name('restore');
            });

            Route::prefix('wallet-transactions')->name('wallet-transactions.')->group(function () {
                Route::get('/', [WalletTransactionController::class, 'index'])->name('index');
                Route::get('user/{user}', [WalletTransactionController::class, 'user'])->whereNumber('user')->name('user');
                Route::get('{walletTransaction}', [WalletTransactionController::class, 'show'])->whereNumber('walletTransaction')->name('show');
            });

            Route::get('wallet-ops/recharge', [WalletOpsController::class, 'rechargeForm'])->name('wallet-ops.recharge.form');
            Route::get('wallet-ops/users/search', [WalletOpsController::class, 'searchUsersJson'])->name('wallet-ops.users.search');
            Route::post('wallet-ops/recharge', [WalletOpsController::class, 'recharge'])->name('wallet-ops.recharge');
            Route::post('wallet-ops/activate-guarantee', [WalletOpsController::class, 'activateGuarantee'])->name('wallet-ops.activate-guarantee');

            // Platform fines (fraud/abuse) — freeze → appeal window → capture.
            // MONEY, not DISPUTES: it takes money from one user, not rules
            // between two. Nothing is captured here; the sweep does that.
            Route::prefix('fines')->name('fines.')->group(function () {
                Route::get('/', [FineController::class, 'index'])->name('index');
                Route::get('create', [FineController::class, 'create'])->name('create');
                Route::post('/', [FineController::class, 'store'])->name('store');
                Route::get('{fine}', [FineController::class, 'show'])->whereNumber('fine')->name('show');
                Route::post('{fine}/appeal-decision', [FineController::class, 'decideAppeal'])->whereNumber('fine')->name('appeal-decision');
                Route::post('{fine}/cancel', [FineController::class, 'cancel'])->whereNumber('fine')->name('cancel');
            });
        });

        Route::prefix('guarantee-levels')->name('guarantee-levels.')->middleware('can:' . AdminAbility::TRUST)->group(function () {
            Route::get('/', [GuaranteeLevelAdminController::class, 'index'])->name('index');
            Route::get('create', [GuaranteeLevelAdminController::class, 'create'])->name('create');
            Route::post('/', [GuaranteeLevelAdminController::class, 'store'])->name('store');
            Route::get('{guaranteeLevel}/edit', [GuaranteeLevelAdminController::class, 'edit'])->whereNumber('guaranteeLevel')->name('edit');
            Route::put('{guaranteeLevel}', [GuaranteeLevelAdminController::class, 'update'])->whereNumber('guaranteeLevel')->name('update');
            Route::delete('{guaranteeLevel}', [GuaranteeLevelAdminController::class, 'destroy'])->whereNumber('guaranteeLevel')->name('destroy');
            Route::post('{guaranteeLevel}/toggle', [GuaranteeLevelAdminController::class, 'toggle'])->whereNumber('guaranteeLevel')->name('toggle');
        });

        // Scheduling/routes service oversight (read-only).
        Route::prefix('trip-schedules')->name('trip-schedules.')->middleware('can:' . AdminAbility::OPERATIONS)->group(function () {
            Route::get('/', [TripScheduleAdminController::class, 'schedules'])->name('index');
            Route::get('reservations', [TripScheduleAdminController::class, 'reservations'])->name('reservations');
        });

        Route::prefix('guarantees')->name('guarantees.')->middleware('can:' . AdminAbility::TRUST)->group(function () {
            Route::get('/', [GuaranteeAdminController::class, 'index'])->name('index');
            Route::post('{guarantee}/sync-coverage', [GuaranteeAdminController::class, 'syncCoverage'])->whereNumber('guarantee')->name('sync');
            Route::post('{guarantee}/process-grace-now', [GuaranteeAdminController::class, 'processGraceNow'])->whereNumber('guarantee')->name('process-grace');
            Route::post('{guarantee}/expire-if-due', [GuaranteeAdminController::class, 'expireIfDue'])->whereNumber('guarantee')->name('expire-if-due');
            Route::post('{guarantee}/expire-now', [GuaranteeAdminController::class, 'expireNow'])->whereNumber('guarantee')->name('expire-now');
            Route::post('{guarantee}/suspend', [GuaranteeAdminController::class, 'suspend'])->whereNumber('guarantee')->name('suspend');
            Route::post('{guarantee}/reactivate', [GuaranteeAdminController::class, 'reactivate'])->whereNumber('guarantee')->name('reactivate');
            // Frees locked coverage into spendable balance — a money movement
            // wearing a trust-screen label, so it needs MONEY on top of TRUST.
            Route::post('{guarantee}/unlock-to-balance', [GuaranteeAdminController::class, 'unlockToBalance'])->whereNumber('guarantee')->middleware('can:' . AdminAbility::MONEY)->name('unlock-to-balance');
            Route::post('{guarantee}/auto-upgrade', [GuaranteeAdminController::class, 'autoUpgrade'])->whereNumber('guarantee')->name('auto-upgrade');
            Route::post('{guarantee}/auto-downgrade', [GuaranteeAdminController::class, 'autoDowngrade'])->whereNumber('guarantee')->name('auto-downgrade');
            Route::get('{guarantee}', [GuaranteeAdminController::class, 'show'])->whereNumber('guarantee')->name('show');
        });

        Route::resource('wallet-notes', WalletNoteTemplateController::class)->except(['show'])->names('wallet-notes')->middleware('can:' . AdminAbility::MONEY);

        Route::prefix('subscriptions')->name('subscriptions.')->middleware('can:' . AdminAbility::COMMERCE)->group(function () {
            Route::get('/', [SubscriptionController::class, 'index'])->name('index');
            Route::get('{subscription}', [SubscriptionController::class, 'show'])->whereNumber('subscription')->name('show');
            Route::get('{subscription}/edit', [SubscriptionController::class, 'edit'])->whereNumber('subscription')->name('edit');
            Route::put('{subscription}', [SubscriptionController::class, 'update'])->whereNumber('subscription')->name('update');
            Route::post('{subscription}/toggle-active', [SubscriptionController::class, 'toggleActive'])->whereNumber('subscription')->name('toggle-active');
        });

        Route::middleware('can:' . AdminAbility::MONEY)->group(function () {
            Route::prefix('payments')->name('payments.')->group(function () {
                Route::get('/', [PaymentController::class, 'index'])->name('index');
                Route::post('{paymentId}/confirm', [PaymentController::class, 'confirm'])->whereNumber('paymentId')->name('confirm');
            });

            // Live payment-gateway credentials (Fawry) — paste-and-go, no
            // redeploy. MONEY rather than SETTINGS: rewriting these redirects
            // real money, which makes it the most dangerous form in the panel.
            Route::get('payment-settings', [PaymentSettingsController::class, 'edit'])->name('payment-settings.edit');
            Route::put('payment-settings', [PaymentSettingsController::class, 'update'])->name('payment-settings.update');

            // Per-merchant Fawry sub-accounts: global toggle + each merchant's own
            // gateway code/key, so a customer's payment can be routed to the
            // merchant's Fawry account. Also MONEY — it redirects real money.
            Route::get('merchant-payment-accounts', [MerchantPaymentAccountController::class, 'index'])->name('merchant-payment-accounts.index');
            Route::put('merchant-payment-accounts/toggle', [MerchantPaymentAccountController::class, 'toggle'])->name('merchant-payment-accounts.toggle');
            Route::post('merchant-payment-accounts', [MerchantPaymentAccountController::class, 'save'])->name('merchant-payment-accounts.save');

            // Businesses applying for a merchant sub-account: review + provision.
            Route::get('merchant-account-requests', [MerchantAccountRequestController::class, 'index'])->name('merchant-account-requests.index');
            Route::post('merchant-account-requests/{merchantAccountRequest}/approve', [MerchantAccountRequestController::class, 'approve'])->name('merchant-account-requests.approve');
            Route::post('merchant-account-requests/{merchantAccountRequest}/reject', [MerchantAccountRequestController::class, 'reject'])->name('merchant-account-requests.reject');
        });

        // Live push credentials (Firebase service-account JSON) — paste-and-go.
        Route::middleware('can:' . AdminAbility::SETTINGS)->group(function () {
            Route::get('push-settings', [PushSettingsController::class, 'edit'])->name('push-settings.edit');
            Route::put('push-settings', [PushSettingsController::class, 'update'])->name('push-settings.update');
            Route::post('push-settings/test', [PushSettingsController::class, 'test'])->name('push-settings.test');
        });

        // Who may do what. Its own ability, NOT SETTINGS: whoever can hand out
        // MONEY effectively has MONEY, so bundling it with the push-credentials
        // screen would have quietly made SETTINGS equal to everything.
        Route::prefix('admin-roles')->name('admin-roles.')->middleware('can:' . AdminAbility::ROLES)->group(function () {
            Route::get('/', [AdminRoleController::class, 'index'])->name('index');
            Route::get('{user}/edit', [AdminRoleController::class, 'edit'])->whereNumber('user')->name('edit');
            Route::put('{user}', [AdminRoleController::class, 'update'])->whereNumber('user')->name('update');
        });

        // Appointing the people who rule on other people's money is a staffing
        // decision, so it sits behind ROLES, not DISPUTES: an arbitrator runs
        // their own queue and can still never appoint another arbitrator.
        Route::prefix('arbitrators')->name('arbitrators.')->middleware('can:' . AdminAbility::ROLES)->group(function () {
            Route::get('/', [ArbitratorController::class, 'index'])->name('index');
            Route::get('{user}', [ArbitratorController::class, 'show'])->whereNumber('user')->name('show');
            Route::post('promote', [ArbitratorController::class, 'promote'])->name('promote');
            Route::delete('{user}', [ArbitratorController::class, 'demote'])->whereNumber('user')->name('demote');
        });

        // The rules parties must accept. Behind SETTINGS, not DISPUTES: this is
        // platform policy every case is judged by, not the handling of one case.
        Route::prefix('dispute-rules')->name('dispute-rules.')->middleware('can:' . AdminAbility::SETTINGS)->group(function () {
            Route::get('/', [DisputeRuleController::class, 'index'])->name('index');
            Route::post('/', [DisputeRuleController::class, 'store'])->name('store');
            Route::get('{ruleVersion}', [DisputeRuleController::class, 'show'])->whereNumber('ruleVersion')->name('show');
        });

        // What a session costs, per service. Platform policy — deliberately not
        // on the arbitrator's screen.
        Route::prefix('dispute-fees')->name('dispute-fees.')->middleware('can:' . AdminAbility::FEES)->group(function () {
            Route::get('/', [DisputeFeeController::class, 'index'])->name('index');
            Route::put('/', [DisputeFeeController::class, 'update'])->name('update');
        });

        Route::prefix('disputes')->name('disputes.')->middleware('can:' . AdminAbility::DISPUTES)->group(function () {
            Route::get('/', [DisputeController::class, 'index'])->name('index');
            Route::get('{dispute}', [DisputeController::class, 'show'])->whereNumber('dispute')->name('show');
            Route::post('{dispute}/under-review', [DisputeController::class, 'setUnderReview'])->whereNumber('dispute')->name('under-review');
            // Accepting states the fee BEFORE anything is heard, and announces
            // it to both parties.
            Route::post('{dispute}/accept-session', [DisputeController::class, 'acceptSession'])->whereNumber('dispute')->name('accept-session');
            // Compensation the escrow does not cover — ordered by the ruling,
            // paid from the loser's wallet, retried if it was short.
            Route::post('{dispute}/compensation', [DisputeController::class, 'awardCompensation'])->whereNumber('dispute')->name('compensation');
            Route::post('{dispute}/compensation/settle', [DisputeController::class, 'settleCompensation'])->whereNumber('dispute')->name('compensation.settle');
            // Recording a violation is a record, never an automatic penalty.
            Route::post('{dispute}/conduct-violation', [DisputeController::class, 'recordConductViolation'])->whereNumber('dispute')->name('conduct-violation');
            // Posting takes the arbitrator's seat — reading the case does not.
            Route::post('{dispute}/room', [DisputeController::class, 'roomPost'])->whereNumber('dispute')->name('room.post');
            Route::post('{dispute}/close', [DisputeController::class, 'close'])->whereNumber('dispute')->name('close');
            // The verdict that the ruling was carried out — refused while anything is unpaid.
            Route::post('{dispute}/close-complied', [DisputeController::class, 'closeWithCompliance'])->whereNumber('dispute')->name('close-complied');
            // Triage above; payout below. These three decide who gets the money,
            // so they need MONEY as well as DISPUTES — which is exactly what
            // lets a support agent work the queue without being able to pay
            // anyone out. resolve/no-action moves nothing, so it stays triage.
            Route::middleware('can:' . AdminAbility::MONEY)->group(function () {
                Route::post('{dispute}/resolve/release-business', [DisputeController::class, 'resolveReleaseBusiness'])->whereNumber('dispute')->name('resolve.release-business');
                Route::post('{dispute}/resolve/refund-client', [DisputeController::class, 'resolveRefundClient'])->whereNumber('dispute')->name('resolve.refund-client');
                Route::post('{dispute}/resolve/split', [DisputeController::class, 'resolveSplit'])->whereNumber('dispute')->name('resolve.split');
                // A fine the parties themselves agreed to as part of a mutual
                // settlement — non-appealable, so it is money and needs MONEY.
                Route::post('{dispute}/settlement-fine', [DisputeController::class, 'attachSettlementFine'])->whereNumber('dispute')->name('settlement-fine');
            });

            Route::post('{dispute}/resolve/no-action', [DisputeController::class, 'resolveNoAction'])->whereNumber('dispute')->name('resolve.no-action');
        });

        Route::prefix('bookings')->name('bookings.')->middleware('can:' . AdminAbility::OPERATIONS)->group(function () {
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
            // The deposit is somebody's escrow. Moving it from an operations
            // screen is still moving it, so MONEY applies here too.
            Route::middleware('can:' . AdminAbility::MONEY)->group(function () {
                Route::post('{booking}/deposit-freeze', [BookingController::class, 'depositFreeze'])->whereNumber('booking')->name('deposit.freeze');
                Route::post('{booking}/deposit-release', [BookingController::class, 'depositRelease'])->whereNumber('booking')->name('deposit.release');
                Route::post('{booking}/deposit-refund', [BookingController::class, 'depositRefund'])->whereNumber('booking')->name('deposit.refund');
                Route::post('{booking}/deposit-agree-release', [BookingController::class, 'depositAgreeRelease'])->whereNumber('booking')->name('deposit.agree.release');
                Route::post('{booking}/deposit-agree-refund', [BookingController::class, 'depositAgreeRefund'])->whereNumber('booking')->name('deposit.agree.refund');
            });

            // Opening a dispute moves nothing — it is the request for a ruling.
            Route::post('{booking}/deposit-dispute-open', [BookingController::class, 'depositDisputeOpen'])->whereNumber('booking')->name('deposit.dispute.open');
        });

        Route::middleware('can:' . AdminAbility::CONTENT)->group(function () {
            // No create/store: a feed post is written by a user in the app, and
            // PostController implements neither method — the generated routes
            // resolved to nothing and 500'd. Admins moderate posts, not author them.
            Route::resource('posts', PostController::class)->except(['create', 'store'])->names('posts');
            Route::post('posts/{post}/toggle-active', [PostController::class, 'toggleActive'])->whereNumber('post')->name('posts.toggleActive');
            Route::delete('posts/{post}/main-image', [PostController::class, 'destroyMainImage'])->whereNumber('post')->name('posts.main_image.destroy');
            Route::delete('posts/{post}/images/{image}', [PostController::class, 'destroyImage'])->whereNumber('post')->whereNumber('image')->name('posts.images.destroy');

            // Every jobs/* blade view calls route(..., ['post' => ...]) (matching
            // the controller's Post $post binding) — but Route::resource derives
            // {job} by default from the resource name, which silently broke every
            // generated link (index->show, show->edit, the delete modal...).
            // Verified by actually clicking through, not by reading the routes.
            Route::resource('jobs', JobPostController::class)->parameter('jobs', 'post')->names('jobs');
            Route::post('jobs/{post}/toggle-active', [JobPostController::class, 'toggleActive'])->whereNumber('post')->name('jobs.toggleActive');
            Route::get('jobs/{post}/applicants', [JobPostController::class, 'applicants'])->whereNumber('post')->name('jobs.applicants');

            // Its own path segment, not jobs/*, because Route::resource above
            // registers an unconstrained GET jobs/{post} that would swallow it.
            Route::get('job-follows', [JobFollowController::class, 'index'])->name('job-follows.index');

            Route::resource('sponsors', SponsorController::class)->names('sponsors');
            Route::post('sponsors/{sponsor}/toggle-active', [SponsorController::class, 'toggleActive'])->whereNumber('sponsor')->name('sponsors.toggleActive');

            Route::resource('albums', AlbumController::class)->names('albums');
            Route::post('albums/{album}/images/{imageId}/set-cover', [AlbumController::class, 'setCover'])->whereNumber('album')->whereNumber('imageId')->name('albums.images.set-cover');
            Route::delete('albums/{album}/images/{imageId}', [AlbumController::class, 'deleteImage'])->whereNumber('album')->whereNumber('imageId')->name('albums.images.delete');
        });

        // ── Merged in from the former routes/admin_v2_extras.php. Kept at the
        // end of this group so the offer-boost-packages redirect stubs above
        // still take precedence over the real routes below (same as the prior
        // load order admin_v2 → admin_v2_extras).
        Route::get('wallet-overview', [WalletOverviewController::class, 'index'])->middleware('can:' . AdminAbility::MONEY)->name('wallet-overview.index');
        Route::get('offer-follows', [OfferFollowDashboardController::class, 'index'])->middleware('can:' . AdminAbility::COMMERCE)->name('offer-follows.index');

        Route::prefix('notification-center')->name('notification-center.')->middleware('can:' . AdminAbility::SETTINGS)->group(function () {
            Route::get('/', [NotificationCenterAdminController::class, 'index'])->name('index');
            Route::post('sync-offers', [NotificationCenterAdminController::class, 'syncOffers'])->name('sync-offers');
        });

        Route::prefix('offer-boost-packages')->name('offer-boost-packages.')->middleware('can:' . AdminAbility::COMMERCE)->group(function () {
            Route::get('/', [OfferBoostPackageController::class, 'index'])->name('index');
            Route::get('create', [OfferBoostPackageController::class, 'create'])->name('create');
            Route::post('/', [OfferBoostPackageController::class, 'store'])->name('store');
            Route::get('boost', [OfferBoostPackageController::class, 'boostForm'])->name('boost-form');
            Route::post('boost', [OfferBoostPackageController::class, 'activateBoost'])->name('activate');
            Route::get('{offerBoostPackage}/edit', [OfferBoostPackageController::class, 'edit'])->whereNumber('offerBoostPackage')->name('edit');
            Route::put('{offerBoostPackage}', [OfferBoostPackageController::class, 'update'])->whereNumber('offerBoostPackage')->name('update');
            Route::post('{offerBoostPackage}/toggle', [OfferBoostPackageController::class, 'toggle'])->whereNumber('offerBoostPackage')->name('toggle');
        });
    });
});
