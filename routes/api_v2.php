<?php

use App\Http\Controllers\Api\V2\AccountDeletionController;
use App\Http\Controllers\Api\V2\AddressController;
use App\Http\Controllers\Api\V2\AuthController;
use App\Http\Controllers\Api\V2\BookingController;
use App\Http\Controllers\Api\V2\BusinessMenuItemController;
use App\Http\Controllers\Api\V2\BusinessMenuSectionController;
use App\Http\Controllers\Api\V2\BusinessOfferController;
use App\Http\Controllers\Api\V2\CartController;
use App\Http\Controllers\Api\V2\CategoryController;
use App\Http\Controllers\Api\V2\DeliveryController;
use App\Http\Controllers\Api\V2\DepositController;
use App\Http\Controllers\Api\V2\DiscoveryController;
use App\Http\Controllers\Api\V2\DisputeController;
use App\Http\Controllers\Api\V2\MenuDiscoveryController;
use App\Http\Controllers\Api\V2\GuaranteeController;
use App\Http\Controllers\Api\V2\JobController;
use App\Http\Controllers\Api\V2\JobFollowController;
use App\Http\Controllers\Api\V2\LocationController;
use App\Http\Controllers\Api\V2\RatingController;
use App\Http\Controllers\Api\V2\NotificationCenterController;
use App\Http\Controllers\Api\V2\OfferBoostController;
use App\Http\Controllers\Api\V2\OfferComparisonController;
use App\Http\Controllers\Api\V2\OfferDiscoveryController;
use App\Http\Controllers\Api\V2\OfferFollowController;
use App\Http\Controllers\Api\V2\OfferTrackingController;
use App\Http\Controllers\Api\V2\OperationGuarantorController;
use App\Http\Controllers\Api\V2\OrderController;
use App\Http\Controllers\Api\V2\OrderHandoverController;
use App\Http\Controllers\Api\V2\PasswordResetController;
use App\Http\Controllers\Api\V2\CommentController;
use App\Http\Controllers\Api\V2\PostController;
use App\Http\Controllers\Api\V2\ProfileController;
use App\Http\Controllers\Api\V2\PushTokenController;
use App\Http\Controllers\Api\V2\RetailDiscoveryController;
use App\Http\Controllers\Api\V2\SharedCartController;
use App\Http\Controllers\Api\V2\SearchOffersController;
use App\Http\Controllers\Api\V2\TableController;
use App\Http\Controllers\Api\V2\TripReservationController;
use App\Http\Controllers\Api\V2\TripScheduleController;
use App\Http\Controllers\Api\V2\WalletController;
use App\Http\Controllers\Api\V2\WalletTopupController;
use Illuminate\Support\Facades\Route;

Route::prefix('v2')->group(function () {
    // Auth: the mobile app's token entry point (v2 is self-sufficient, no v1).
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);

        // Password reset by emailed code. Throttled to blunt abuse/enumeration.
        Route::middleware('throttle:6,1')->prefix('password')->group(function () {
            Route::post('forgot', [PasswordResetController::class, 'forgot']);
            Route::post('resend', [PasswordResetController::class, 'resend']);
            Route::post('verify', [PasswordResetController::class, 'verify']);
            Route::post('reset', [PasswordResetController::class, 'reset']);
        });
    });

    // Cancelling a deletion cannot be authenticated — requesting it revoked
    // every token. It verifies the password itself, so it is throttled like the
    // login it effectively is.
    Route::middleware('throttle:6,1')
        ->post('account/deletion/cancel', [AccountDeletionController::class, 'cancel']);

    // Geography (BIM-11.1) — public: an address is picked at registration and
    // checkout, before there is a token. Without these the address book cannot
    // be used at all: POST /addresses requires ids nothing could discover.
    Route::prefix('locations')->group(function () {
        Route::get('countries', [LocationController::class, 'countries']);
        Route::get('governorates', [LocationController::class, 'governorates']);
        Route::get('cities/search', [LocationController::class, 'searchCities']);
        Route::get('cities', [LocationController::class, 'cities']);
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

        // Scheduling/routes: carriers on a route + day, ranked by trust
        // (guarantee coverage + operation rating). Public discovery.
        Route::get('schedules', [TripScheduleController::class, 'search']);
    });

    // Vehicle/cargo classes for the scheduling service (picker + filter). Public.
    Route::get('schedules/vehicle-types', [TripScheduleController::class, 'vehicleTypes']);
    // Country picker for INTERNATIONAL legs only (domestic uses governorates).
    // Kept as a path because the app already calls it, but pointed at the one
    // implementation in LocationController — the same list has no business
    // being built twice.
    Route::get('schedules/countries', [LocationController::class, 'countries']);

    // Classification — the front door. Discovery below REQUIRES a child_id and
    // nothing in v2 returned one, so the app could not browse a single business.
    // root category -> specialty (child_id) -> discovery.
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::get('{category}/specialties', [CategoryController::class, 'specialties'])->whereNumber('category');
    });

    // Customer discovery: specialty (category child) -> service + item types -> businesses.
    Route::prefix('discovery')->group(function () {
        Route::get('filters', [DiscoveryController::class, 'filters']);
        // Attributes axis (options): business-level properties like «تقسيط»,
        // distinct from the offering axis above (services/item types).
        Route::get('attributes', [DiscoveryController::class, 'attributes']);
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

    // Jobs: a business posts a vacancy in any field, a client applies. Public
    // browse + the category tree (only branches that actually have a job).
    Route::prefix('jobs')->group(function () {
        Route::get('/', [JobController::class, 'index']);
        Route::get('categories', [JobController::class, 'categories']);
        // Platform-wide counters. Aggregates only — /jobs/mine/stats is the
        // per-business one and needs auth.
        Route::get('stats', [JobController::class, 'platformStats']);
        Route::get('{post}', [JobController::class, 'show'])->whereNumber('post');
    });

    // Posts: the social feed. Public to browse; personalised when a bearer
    // token is sent (PostController::viewer resolves the sanctum guard by
    // hand, since these routes are outside the auth group).
    Route::prefix('posts')->group(function () {
        Route::get('/', [PostController::class, 'index']);
        Route::get('{post}', [PostController::class, 'show'])->whereNumber('post');
        // Reading comments is public; the public/private rule is applied as a
        // query scope in CommentVisibilityService, not left to the client.
        Route::get('{post}/comments', [CommentController::class, 'index'])->whereNumber('post');
    });

    Route::get('comments/{comment}/replies', [CommentController::class, 'replies'])->whereNumber('comment');

    // Payment gateway server-to-server callback for wallet top-ups. PUBLIC (the
    // gateway calls it, not the app) — security is the signed-payload check.
    // Optional ?gateway= selects the provider (defaults to config).
    Route::post('wallet/topup/callback', [WalletTopupController::class, 'callback']);

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
        // Attributes axis self-service: a business picks the options that
        // describe it, scoped to what its own specialty (child) allows.
        Route::get('profile/options', [ProfileController::class, 'showOptions']);
        Route::match(['put', 'patch'], 'profile/options', [ProfileController::class, 'updateOptions']);

        // Jobs: a business posts one, a client applies. Applicant identities
        // are visible only to the posting business — see JobController.
        Route::post('jobs', [JobController::class, 'store']);
        Route::get('jobs/mine/stats', [JobController::class, 'myStats']);

        // Follow job fields → live push when a vacancy is posted there.
        // Declared before the {post} routes; 'follows' is not numeric so it
        // never collides, but keeping it first makes the intent obvious.
        Route::get('jobs/follows', [JobFollowController::class, 'index']);
        Route::post('jobs/follows', [JobFollowController::class, 'store']);
        Route::delete('jobs/follows/{follow}', [JobFollowController::class, 'destroy'])->whereNumber('follow');

        Route::post('jobs/{post}/apply', [JobController::class, 'apply'])->whereNumber('post');
        Route::get('jobs/{post}/applicants', [JobController::class, 'applicants'])->whereNumber('post');
        Route::post('jobs/{post}/applicants/{apply}/approve', [JobController::class, 'approveApplicant'])->whereNumber('post')->whereNumber('apply');
        Route::post('jobs/{post}/close', [JobController::class, 'close'])->whereNumber('post');

        // Posts: publish, edit and react. `mine` is declared before {post} and
        // {post} is numeric-constrained, so the two never collide.
        // update is POST, not PUT: PHP does not parse multipart bodies on PUT,
        // so an image edit would arrive empty.
        Route::get('posts/mine', [PostController::class, 'mine']);
        // Static before {post}: what this account can link a post to.
        Route::get('posts/subject-options', [PostController::class, 'subjectOptions']);
        Route::post('posts', [PostController::class, 'store']);
        Route::post('posts/{post}', [PostController::class, 'update'])->whereNumber('post');
        Route::delete('posts/{post}', [PostController::class, 'destroy'])->whereNumber('post');
        Route::post('posts/{post}/share', [PostController::class, 'share'])->whereNumber('post');
        Route::post('posts/{post}/react', [PostController::class, 'react'])->whereNumber('post');

        // Comments. v1 had store()/commentReplies() written but never routed,
        // so the API could read comments and never write one.
        Route::post('posts/{post}/comments', [CommentController::class, 'store'])->whereNumber('post');
        Route::post('comments/{comment}/replies', [CommentController::class, 'reply'])->whereNumber('comment');
        Route::match(['put', 'patch'], 'comments/{comment}', [CommentController::class, 'update'])->whereNumber('comment');
        Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->whereNumber('comment');

        // Escrow deposits — READ ONLY on purpose. Creating, releasing and
        // refunding belong to BookingDepositService and DisputeService; v1
        // exposed them raw with no authorization and let anyone move anyone's
        // money. See Api\V2\DepositController.
        Route::get('deposits', [DepositController::class, 'index']);
        Route::get('deposits/{deposit}', [DepositController::class, 'show'])->whereNumber('deposit');

        // Disputes. Opening is the point: the whole mechanism existed with no
        // way for the party who had the grievance to reach it, so the table
        // stayed empty. Ruling stays with the admin — a party deciding their
        // own dispute would be the v1 /deposits hole again.
        Route::get('disputes/reason-codes', [DisputeController::class, 'reasonCodes']);
        Route::get('disputes', [DisputeController::class, 'index']);
        Route::get('disputes/{dispute}', [DisputeController::class, 'show'])->whereNumber('dispute');
        Route::post('bookings/{booking}/disputes', [DisputeController::class, 'storeForBooking'])
            ->whereNumber('booking');

        // Declaring you are engaging with the settlement. Its ABSENCE is what
        // gets recorded when the window expires — a mark the arbitrator reads,
        // never an automatic charge.
        Route::post('disputes/{dispute}/cooperate', [DisputeController::class, 'cooperate'])
            ->whereNumber('dispute');

        // The arbitration room. Opens with the dispute so the settlement window
        // has somewhere to happen; an arbitrator takes a seat in the same
        // thread when the window expires.
        Route::get('disputes/{dispute}/room', [DisputeController::class, 'room'])->whereNumber('dispute');
        Route::post('disputes/{dispute}/room/messages', [DisputeController::class, 'postMessage'])
            ->whereNumber('dispute');

        // Delete my account (BIM-15.1). Eligibility is a read of its own so the
        // app can show what must be finished first, instead of the user finding
        // out by being refused.
        Route::get('account/deletion', [AccountDeletionController::class, 'eligibility']);
        Route::post('account/deletion', [AccountDeletionController::class, 'store']);

        // Wallet: balance/ledger (read) + money movements + PIN.
        Route::prefix('wallet')->group(function () {
            Route::get('/', [WalletController::class, 'show']);
            Route::get('transactions', [WalletController::class, 'transactions']);
            Route::post('deposit', [WalletController::class, 'deposit']);
            Route::post('withdraw', [WalletController::class, 'withdraw']);
            Route::post('transfer', [WalletController::class, 'transfer']);
            Route::get('pin', [WalletController::class, 'pinStatus']);
            Route::post('pin', [WalletController::class, 'setPin']);
            Route::post('pin/verify', [WalletController::class, 'verifyPin']);

            // Real money-in: start a top-up (returns hosted-checkout payload) +
            // poll its status. Crediting happens in the public callback above.
            Route::post('topup', [WalletTopupController::class, 'store']);
            Route::get('topup/{topup}', [WalletTopupController::class, 'show'])->whereNumber('topup');
        });

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

        // Placed orders: the customer's own history + detail + cancel.
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{order}', [OrderController::class, 'show'])->whereNumber('order');
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->whereNumber('order');

        // Placed orders: the business's incoming-order queue + detail + lifecycle.
        // Business-only, gated centrally by the `business` middleware.
        Route::middleware('business')->group(function () {
            Route::get('business/orders', [OrderController::class, 'businessIndex']);
            Route::get('business/orders/{order}', [OrderController::class, 'businessShow'])->whereNumber('order');
            Route::post('business/orders/{order}/reject', [OrderController::class, 'businessReject'])->whereNumber('order');
            // Prep lifecycle: accept (settles BIM fee from the business wallet) →
            // preparing (order becomes visible to drivers) → ready.
            Route::post('business/orders/{order}/accept', [OrderController::class, 'businessAccept'])->whereNumber('order');
            Route::post('business/orders/{order}/preparing', [OrderController::class, 'businessPreparing'])->whereNumber('order');
            Route::post('business/orders/{order}/ready', [OrderController::class, 'businessReady'])->whereNumber('order');
        });

        // Business menu management: sections + items (+ variants/extras).
        Route::prefix('business/menu')->middleware('business')->group(function () {
            Route::get('sections', [BusinessMenuSectionController::class, 'index']);
            Route::post('sections', [BusinessMenuSectionController::class, 'store']);
            Route::match(['put', 'patch'], 'sections/{section}', [BusinessMenuSectionController::class, 'update'])->whereNumber('section');
            Route::delete('sections/{section}', [BusinessMenuSectionController::class, 'destroy'])->whereNumber('section');

            Route::get('items', [BusinessMenuItemController::class, 'index']);
            Route::post('items', [BusinessMenuItemController::class, 'store']);
            Route::get('items/{item}', [BusinessMenuItemController::class, 'show'])->whereNumber('item');
            Route::match(['put', 'patch'], 'items/{item}', [BusinessMenuItemController::class, 'update'])->whereNumber('item');
            Route::delete('items/{item}', [BusinessMenuItemController::class, 'destroy'])->whereNumber('item');

            Route::post('items/{item}/variants', [BusinessMenuItemController::class, 'storeVariant'])->whereNumber('item');
            Route::match(['put', 'patch'], 'items/{item}/variants/{variant}', [BusinessMenuItemController::class, 'updateVariant'])->whereNumber(['item', 'variant']);
            Route::delete('items/{item}/variants/{variant}', [BusinessMenuItemController::class, 'destroyVariant'])->whereNumber(['item', 'variant']);

            Route::post('items/{item}/extras', [BusinessMenuItemController::class, 'storeExtra'])->whereNumber('item');
            Route::match(['put', 'patch'], 'items/{item}/extras/{extra}', [BusinessMenuItemController::class, 'updateExtra'])->whereNumber(['item', 'extra']);
            Route::delete('items/{item}/extras/{extra}', [BusinessMenuItemController::class, 'destroyExtra'])->whereNumber(['item', 'extra']);
        });

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

        // Push notification device tokens (the single live store, user_push_tokens).
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

        // Operation-based rating: objective %'s + subjective star reviews.
        Route::prefix('ratings')->group(function () {
            Route::get('me', [RatingController::class, 'me']);
            Route::get('user/{user}', [RatingController::class, 'show'])->whereNumber('user');
            Route::get('user/{user}/reviews', [RatingController::class, 'reviews'])->whereNumber('user');
            // Star review — gated on a real, completed operation between the parties.
            Route::post('review', [RatingController::class, 'review']);
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

        // Offer-follow matches surface in the unified /notifications center
        // (type=offer, via InAppNotificationService::createFromOfferFollowNotification),
        // so there is no separate offer-notification inbox — only follow CRUD here.
        Route::prefix('offer-follows')->group(function () {
            Route::get('/', [OfferFollowController::class, 'index']);
            Route::post('/', [OfferFollowController::class, 'store']);
            Route::delete('{follow}', [OfferFollowController::class, 'destroy'])->whereNumber('follow');
        });

        // Scheduling/routes: customer reserves capacity on a leg, lists own
        // reservations, cancels. Open to any authenticated user.
        Route::prefix('schedules')->group(function () {
            Route::get('my-reservations', [TripReservationController::class, 'myReservations']);
            Route::post('{schedule}/reserve', [TripReservationController::class, 'reserve'])->whereNumber('schedule');
            Route::post('reservations/{reservation}/cancel', [TripReservationController::class, 'cancel'])->whereNumber('reservation');
        });

        // Scheduling/routes service: a business publishes + manages its own trip
        // legs (freight / passenger / limousine / distribution), incl. backhaul,
        // and handles the reservations that come in against them.
        Route::prefix('business/schedules')->middleware('business')->group(function () {
            Route::get('/', [TripScheduleController::class, 'index']);
            Route::post('/', [TripScheduleController::class, 'store']);

            // Carrier blocks capacity for an off-app deal (direct sale).
            Route::post('{schedule}/block', [TripReservationController::class, 'block'])->whereNumber('schedule');

            // Incoming reservations for the carrier: list + confirm/complete/reject.
            Route::get('reservations', [TripReservationController::class, 'incoming']);
            Route::post('reservations/{reservation}/confirm', [TripReservationController::class, 'confirm'])->whereNumber('reservation');
            Route::post('reservations/{reservation}/complete', [TripReservationController::class, 'complete'])->whereNumber('reservation');
            Route::post('reservations/{reservation}/reject', [TripReservationController::class, 'reject'])->whereNumber('reservation');

            Route::match(['put', 'patch'], '{schedule}', [TripScheduleController::class, 'update'])->whereNumber('schedule');
            Route::delete('{schedule}', [TripScheduleController::class, 'destroy'])->whereNumber('schedule');
        });

        Route::prefix('business/offers')->middleware('business')->group(function () {
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
