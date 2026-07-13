<?php

use App\Http\Controllers\Api\V2\AddressController;
use App\Http\Controllers\Api\V2\AuthController;
use App\Http\Controllers\Api\V2\BookingController;
use App\Http\Controllers\Api\V2\BusinessOfferController;
use App\Http\Controllers\Api\V2\CartController;
use App\Http\Controllers\Api\V2\DeliveryController;
use App\Http\Controllers\Api\V2\DiscoveryController;
use App\Http\Controllers\Api\V2\MenuDiscoveryController;
use App\Http\Controllers\Api\V2\GuaranteeController;
use App\Http\Controllers\Api\V2\NotificationCenterController;
use App\Http\Controllers\Api\V2\OfferBoostController;
use App\Http\Controllers\Api\V2\OfferComparisonController;
use App\Http\Controllers\Api\V2\OfferDiscoveryController;
use App\Http\Controllers\Api\V2\OfferFollowController;
use App\Http\Controllers\Api\V2\OfferNotificationController;
use App\Http\Controllers\Api\V2\OfferTrackingController;
use App\Http\Controllers\Api\V2\OperationGuarantorController;
use App\Http\Controllers\Api\V2\OrderHandoverController;
use App\Http\Controllers\Api\V2\ProfileController;
use App\Http\Controllers\Api\V2\PushTokenController;
use App\Http\Controllers\Api\V2\RetailDiscoveryController;
use App\Http\Controllers\Api\V2\SharedCartController;
use App\Http\Controllers\Api\V2\SearchOffersController;
use App\Http\Controllers\Api\V2\TableController;
use Illuminate\Support\Facades\Route;

Route::prefix('v2')->group(function () {
    // Auth: the mobile app's token entry point (v2 is self-sufficient, no v1).
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
    });

    Route::prefix('offers')->group(function () {
        Route::get('/', [OfferDiscoveryController::class, 'index']);
        Route::get('lowest', [OfferDiscoveryController::class, 'lowestForOfferable']);
        Route::get('business/{business}', [OfferDiscoveryController::class, 'byBusiness'])->whereNumber('business');
        Route::post('{offer}/track', [OfferTrackingController::class, 'track'])->whereNumber('offer');
        Route::get('{offer}', [OfferDiscoveryController::class, 'show'])->whereNumber('offer');
    });

    Route::prefix('search')->group(function () {
        Route::get('offers', [SearchOffersController::class, 'index']);
        Route::get('business/{business}/offers', [SearchOffersController::class, 'business'])->whereNumber('business');
    });

    // Customer discovery: specialty (category child) -> service + item types -> businesses.
    Route::prefix('discovery')->group(function () {
        Route::get('filters', [DiscoveryController::class, 'filters']);
        Route::get('businesses', [DiscoveryController::class, 'businesses']);

        // Retail: browse catalog products businesses sell -> product -> offers.
        Route::prefix('retail')->group(function () {
            Route::get('filters', [RetailDiscoveryController::class, 'filters']);
            Route::get('products', [RetailDiscoveryController::class, 'products']);
            Route::get('products/{product}', [RetailDiscoveryController::class, 'show'])->whereNumber('product');
        });

        // Menu: browse a business's menu grouped by sections, with variants + extras.
        Route::get('menu/{business}', [MenuDiscoveryController::class, 'show'])->whereNumber('business');
    });

    Route::middleware('auth:sanctum')->group(function () {
        // Account: current user + token lifecycle.
        Route::prefix('auth')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('logout-all', [AuthController::class, 'logoutAll']);
        });

        // Own profile + saved address book.
        Route::get('profile', [ProfileController::class, 'show']);
        Route::match(['put', 'patch'], 'profile', [ProfileController::class, 'update']);
        Route::post('profile/password', [ProfileController::class, 'updatePassword']);

        Route::prefix('addresses')->group(function () {
            Route::get('/', [AddressController::class, 'index']);
            Route::post('/', [AddressController::class, 'store']);
            Route::match(['put', 'patch'], '{address}', [AddressController::class, 'update'])->whereNumber('address');
            Route::post('{address}/primary', [AddressController::class, 'setPrimary'])->whereNumber('address');
            Route::delete('{address}', [AddressController::class, 'destroy'])->whereNumber('address');
        });

        // Customer cart over the offering layer (retail listings + menu items).
        Route::prefix('cart')->group(function () {
            Route::get('/', [CartController::class, 'index']);
            Route::post('items', [CartController::class, 'addItem']);
            Route::patch('items/{item}', [CartController::class, 'updateItem'])->whereNumber('item');
            Route::delete('items/{item}', [CartController::class, 'removeItem'])->whereNumber('item');
            Route::post('{business}/checkout', [CartController::class, 'checkout'])->whereNumber('business');

            // Shared (group) cart: host shares, friends join by token, each adds
            // their own attributed lines; the host checks out one invoice.
            Route::post('{business}/share', [SharedCartController::class, 'share'])->whereNumber('business');
            Route::post('join/{token}', [SharedCartController::class, 'join']);
            Route::get('shared/{order}', [SharedCartController::class, 'show'])->whereNumber('order');
            Route::post('shared/{order}/items', [SharedCartController::class, 'addItem'])->whereNumber('order');
            Route::patch('shared/{order}/items/{item}', [SharedCartController::class, 'updateItem'])->whereNumber(['order', 'item']);
            Route::delete('shared/{order}/items/{item}', [SharedCartController::class, 'removeItem'])->whereNumber(['order', 'item']);
            Route::post('shared/{order}/checkout', [SharedCartController::class, 'checkout'])->whereNumber('order');
            Route::post('shared/{order}/leave', [SharedCartController::class, 'leave'])->whereNumber('order');
            Route::delete('shared/{order}', [SharedCartController::class, 'cancel'])->whereNumber('order');
        });

        // Restaurant-table QR (BIM-13.3): scan a table's permanent token to join
        // or open its dine-in shared cart.
        Route::post('table/{token}/scan', [TableController::class, 'scan']);

        // Order-handover QR (BIM-13.5): issue a ready order's one-time token, and
        // confirm the handover by scanning it (flips the order to completed).
        Route::post('orders/{order}/handover/issue', [OrderHandoverController::class, 'issue'])->whereNumber('order');
        Route::post('handover/{token}/confirm', [OrderHandoverController::class, 'confirm']);

        // Connected delivery loop: driver accepts → pickup QR (stage 1) → delivery
        // QR (stage 2) → completed + restaurant notified + success ledgered.
        Route::prefix('delivery')->group(function () {
            Route::post('register', [DeliveryController::class, 'register']);
            Route::post('availability', [DeliveryController::class, 'availability']);
            Route::get('available-orders', [DeliveryController::class, 'available']);
            Route::post('orders/{order}/accept', [DeliveryController::class, 'accept'])->whereNumber('order');
            Route::post('orders/{order}/pickup-token', [DeliveryController::class, 'issuePickupToken'])->whereNumber('order');
            Route::post('orders/{order}/delivery-token', [DeliveryController::class, 'issueDeliveryToken'])->whereNumber('order');
            Route::post('pickup/{token}/confirm', [DeliveryController::class, 'confirmPickup']);
            Route::post('deliver/{token}/confirm', [DeliveryController::class, 'confirmDelivery']);
        });

        // Friend co-guarantors for an operation (guarantee-as-deposit).
        Route::get('bookings/{booking}/guarantors', [OperationGuarantorController::class, 'index'])->whereNumber('booking');
        Route::post('bookings/{booking}/guarantors', [OperationGuarantorController::class, 'invite'])->whereNumber('booking');
        Route::post('guarantors/{guarantor}/accept', [OperationGuarantorController::class, 'accept'])->whereNumber('guarantor');
        Route::post('guarantors/{guarantor}/decline', [OperationGuarantorController::class, 'decline'])->whereNumber('guarantor');

        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationCenterController::class, 'index']);
            Route::get('unread-count', [NotificationCenterController::class, 'unreadCount']);
            Route::post('mark-all-read', [NotificationCenterController::class, 'markAllRead']);
            Route::get('{notification}', [NotificationCenterController::class, 'show'])->whereNumber('notification');
            Route::post('{notification}/read', [NotificationCenterController::class, 'markRead'])->whereNumber('notification');
            Route::post('{notification}/archive', [NotificationCenterController::class, 'archive'])->whereNumber('notification');
        });

        Route::prefix('push-tokens')->group(function () {
            Route::post('/', [PushTokenController::class, 'store']);
            Route::delete('/', [PushTokenController::class, 'destroy']);
        });

        Route::prefix('guarantees')->group(function () {
            Route::get('levels', [GuaranteeController::class, 'levels']);
            Route::get('me', [GuaranteeController::class, 'me']);
            Route::get('transactions', [GuaranteeController::class, 'transactions']);
            Route::post('activate', [GuaranteeController::class, 'activate']);
            Route::post('unlock', [GuaranteeController::class, 'unlock']);
            Route::post('check-operation', [GuaranteeController::class, 'checkOperationCoverage']);
        });

        Route::prefix('bookings')->group(function () {
            Route::get('/', [BookingController::class, 'index']);
            Route::post('/', [BookingController::class, 'store']);
            Route::get('{booking}', [BookingController::class, 'show'])->whereNumber('booking');
            Route::get('{booking}/financial-preview', [BookingController::class, 'financialPreview'])->whereNumber('booking');
            Route::post('{booking}/accept', [BookingController::class, 'accept'])->whereNumber('booking');
            Route::post('{booking}/reject', [BookingController::class, 'reject'])->whereNumber('booking');
            Route::post('{booking}/cancel', [BookingController::class, 'cancel'])->whereNumber('booking');
            Route::post('{booking}/client-confirm', [BookingController::class, 'clientConfirm'])->whereNumber('booking');
            Route::post('{booking}/business-confirm', [BookingController::class, 'businessConfirm'])->whereNumber('booking');
            Route::post('{booking}/start', [BookingController::class, 'start'])->whereNumber('booking');
            Route::post('{booking}/complete', [BookingController::class, 'complete'])->whereNumber('booking');
        });

        Route::prefix('offers')->group(function () {
            Route::get('compare', [OfferComparisonController::class, 'compare']);
            Route::post('compare', [OfferComparisonController::class, 'compare']);
        });

        Route::prefix('offer-notifications')->group(function () {
            Route::get('/', [OfferNotificationController::class, 'index']);
            Route::get('unread-count', [OfferNotificationController::class, 'unreadCount']);
            Route::post('mark-all-read', [OfferNotificationController::class, 'markAllRead']);
            Route::get('{notification}', [OfferNotificationController::class, 'show'])->whereNumber('notification');
            Route::post('{notification}/read', [OfferNotificationController::class, 'markRead'])->whereNumber('notification');
            Route::post('{notification}/archive', [OfferNotificationController::class, 'archive'])->whereNumber('notification');
        });

        Route::prefix('offer-follows')->group(function () {
            Route::get('/', [OfferFollowController::class, 'index']);
            Route::post('/', [OfferFollowController::class, 'store']);
            Route::delete('{follow}', [OfferFollowController::class, 'destroy'])->whereNumber('follow');
            Route::get('notifications', [OfferFollowController::class, 'notifications']);
            Route::post('notifications/{notification}/read', [OfferFollowController::class, 'markRead'])->whereNumber('notification');
        });

        Route::prefix('business/offers')->group(function () {
            Route::get('/', [BusinessOfferController::class, 'index']);
            Route::post('/', [BusinessOfferController::class, 'store']);
            Route::get('performance/me', [OfferTrackingController::class, 'myPerformance']);
            Route::get('boost/packages', [OfferBoostController::class, 'packages']);
            Route::get('boost/purchases', [OfferBoostController::class, 'myPurchases']);
            Route::post('{offer}/boost', [OfferBoostController::class, 'activate'])->whereNumber('offer');
            Route::put('{offer}', [BusinessOfferController::class, 'update'])->whereNumber('offer');
            Route::patch('{offer}', [BusinessOfferController::class, 'update'])->whereNumber('offer');
            Route::post('{offer}/toggle', [BusinessOfferController::class, 'toggle'])->whereNumber('offer');
            Route::delete('{offer}', [BusinessOfferController::class, 'destroy'])->whereNumber('offer');
        });
    });
});
