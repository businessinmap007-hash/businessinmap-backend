<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\DeliveryCompletion;
use App\Models\DeliveryDriver;
use App\Models\Order;
use App\Models\RatingOutcomeEvent;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
use App\Services\Ratings\RatingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The connected delivery loop. A driver accepts a ready delivery order, scans the
 * restaurant's pickup QR (stage 1 → picked_up), then the customer scans the
 * driver's delivery QR (stage 2 → completed). Final delivery notifies the
 * restaurant and writes a delivery_completions ledger row — the recorded success
 * for BOTH the restaurant and the driver. QR = a link encoding a one-time token;
 * authz stays here.
 */
class DeliveryDispatchService
{
    public const STAGE_ASSIGNED = 'assigned';
    public const STAGE_PICKED_UP = 'picked_up';
    public const STAGE_DELIVERED = 'delivered';

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';

    public function __construct(
        protected NotificationDispatcherService $notifications,
        protected RatingService $ratingService,
    ) {
    }

    // ─────────────────────────── Driver identity ───────────────────────────

    /** Register the user as a delivery driver (idempotent), or update details. */
    public function registerDriver(int $userId, array $data = []): DeliveryDriver
    {
        return DeliveryDriver::updateOrCreate(
            ['user_id' => $userId],
            [
                'is_active' => true,
                'phone' => $data['phone'] ?? null,
                'vehicle_label' => $data['vehicle_label'] ?? null,
            ]
        );
    }

    public function setAvailability(int $userId, bool $active): DeliveryDriver
    {
        $driver = $this->driverOrFail($userId);
        $driver->update(['is_active' => $active]);

        return $driver;
    }

    /**
     * A freelance driver's own flat delivery rate - only ever used as a
     * fallback for an order whose business never set its own
     * delivery_fee_amount (see acceptOrder()). A business-linked driver may
     * set this too, but it's simply unused while they carry that business's
     * own orders.
     */
    public function setOwnDeliveryFee(int $userId, ?float $amount): DeliveryDriver
    {
        $driver = $this->driverOrFail($userId);
        $driver->update(['delivery_fee_amount' => $amount]);

        return $driver;
    }

    /**
     * The driver row for a user, or null -- never creates or mutates one.
     * The dashboard's own "am I already a driver, and on/off duty?" check
     * must stay read-only: register() reactivates on purpose (updateOrCreate
     * sets is_active=true), which would silently undo a business's own
     * deactivation of this same driver if a plain status check called it.
     */
    public function myStatus(int $userId): ?DeliveryDriver
    {
        return DeliveryDriver::query()->where('user_id', $userId)->first();
    }

    /** The active driver row for a user, or 403. */
    public function driverOrFail(int $userId): DeliveryDriver
    {
        $driver = DeliveryDriver::query()->where('user_id', $userId)->first();
        if (! $driver) {
            abort(403, __('لست مسجّلاً كموصّل.'));
        }

        return $driver;
    }

    /**
     * The driver's own app calls this every 30-60s WHILE it is carrying an
     * active order — not a constant background ping, and not enforced here
     * either way (a stale ping just makes distanceKmTo()/hasFreshLocation()
     * return null, never a stale number presented as current).
     */
    public function pingLocation(int $userId, float $lat, float $lng): DeliveryDriver
    {
        $driver = $this->driverOrFail($userId);

        $driver->update([
            'last_lat' => $lat,
            'last_lng' => $lng,
            'location_updated_at' => now(),
        ]);

        return $driver;
    }

    // ─────────────────────────── Business-owned fleet ───────────────────────────

    /**
     * A business links an EXISTING user (by phone) as its own private driver —
     * same find-never-mint pattern as business_staff, so this needs no new
     * signup flow. The driver then uses the exact same self-service loop
     * (register is a no-op for them; accept/pickup/deliver all work as-is),
     * just scoped to this business's own orders (see acceptOrder/availableOrders).
     *
     * A user already privately driving for a DIFFERENT business is refused —
     * one business's roster action must never silently poach another's driver.
     * Already this business's own driver, or currently freelance (business_id
     * null), is fine and just (re)links/reactivates.
     */
    public function linkBusinessDriver(int $businessId, string $lookupPhone, array $data = []): DeliveryDriver
    {
        $user = User::query()->where('phone', trim($lookupPhone))->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'phone' => __('لم يُعثر على مستخدم بهذا الهاتف.'),
            ]);
        }

        if ((int) $user->id === $businessId) {
            throw ValidationException::withMessages([
                'phone' => __('لا يمكنك تعيين نفسك موصّلاً.'),
            ]);
        }

        $existing = DeliveryDriver::query()->where('user_id', $user->id)->first();

        if ($existing && $existing->business_id && (int) $existing->business_id !== $businessId) {
            throw ValidationException::withMessages([
                'phone' => __('هذا المستخدم مسجَّل بالفعل كموصّل لنشاط آخر.'),
            ]);
        }

        return DeliveryDriver::updateOrCreate(
            ['user_id' => $user->id],
            [
                'business_id' => $businessId,
                'is_active' => true,
                'phone' => $data['phone'] ?? $existing->phone ?? $user->phone,
                'vehicle_label' => $data['vehicle_label'] ?? $existing->vehicle_label ?? null,
            ]
        );
    }

    /** The business toggles ITS OWN driver on/off duty — never a hard delete (see below). */
    public function setBusinessDriverActive(int $businessId, int $driverId, bool $active): DeliveryDriver
    {
        $driver = DeliveryDriver::query()
            ->where('id', $driverId)
            ->where('business_id', $businessId)
            ->first();

        if (! $driver) {
            abort(404, __('هذا الموصّل لا يخص نشاطك.'));
        }

        $driver->update(['is_active' => $active]);

        return $driver;
    }

    /**
     * The business's own driver roster with each one's LIVE workload — busy
     * means an order still points at them unfinished, not a flag anyone set.
     * A driver carrying more than one active order at once is the "route":
     * acceptOrder() never blocked a second accept, it just was never surfaced.
     *
     * $restaurantLat/$restaurantLng (the business's own location, e.g.
     * users.latitude/longitude) enable "how far is he from picking up" —
     * omit them and that figure is simply absent, not wrong.
     *
     * @return \Illuminate\Support\Collection<int,array>
     */
    public function businessRoster(int $businessId, ?float $restaurantLat = null, ?float $restaurantLng = null): \Illuminate\Support\Collection
    {
        $drivers = DeliveryDriver::query()
            ->where('business_id', $businessId)
            ->with('user:id,name,phone')
            ->orderByDesc('is_active')
            ->orderBy('id')
            ->get();

        if ($drivers->isEmpty()) {
            return collect();
        }

        $driverIds = $drivers->pluck('id');

        $activeOrders = Order::query()
            ->whereIn('delivery_driver_id', $driverIds)
            ->whereIn('delivery_stage', [self::STAGE_ASSIGNED, self::STAGE_PICKED_UP])
            ->orderBy('id')
            ->get(['id', 'delivery_driver_id', 'delivery_stage', 'address', 'final_total', 'delivery_lat', 'delivery_lng', 'delivery_address_id'])
            ->groupBy('delivery_driver_id');

        $deliveredToday = DeliveryCompletion::query()
            ->whereIn('delivery_driver_id', $driverIds)
            ->whereDate('completed_at', now()->toDateString())
            ->selectRaw('delivery_driver_id, COUNT(*) as c')
            ->groupBy('delivery_driver_id')
            ->pluck('c', 'delivery_driver_id');

        return $drivers->map(function (DeliveryDriver $driver) use ($activeOrders, $deliveredToday, $restaurantLat, $restaurantLng) {
            $orders = $activeOrders->get($driver->id, collect());

            return [
                'id' => (int) $driver->id,
                'user_id' => (int) $driver->user_id,
                'name' => optional($driver->user)->name,
                'phone' => $driver->phone ?: optional($driver->user)->phone,
                'vehicle_label' => $driver->vehicle_label,
                'is_active' => (bool) $driver->is_active,
                'busy' => $orders->isNotEmpty(),
                'active_order_count' => $orders->count(),
                'delivered_today' => (int) ($deliveredToday[$driver->id] ?? 0),
                'delivered_count' => (int) $driver->delivered_count,
                'fast_delivery_count' => (int) $driver->fast_delivery_count,
                'location_available' => $driver->hasFreshLocation(),
                // Where this driver actually is RIGHT NOW, regardless of
                // whether they're already carrying something — the "nearest
                // on the map" sort the merchant's assignment screen wants.
                'distance_km' => $driver->distanceKmTo($restaurantLat, $restaurantLng),
                'active_orders' => $orders->map(function (Order $o) use ($driver, $restaurantLat, $restaurantLng) {
                    [$customerLat, $customerLng] = $o->customerLatLng() ?? [null, null];

                    return [
                        'order_id' => (int) $o->id,
                        // assigned = تم الاستلام من المطعم لم يبدأ بعد، picked_up = في الطريق
                        'stage' => (string) $o->delivery_stage,
                        'address' => (string) $o->address,
                        'final_total' => (float) $o->final_total,
                        // Meaningful only before pickup — once picked up the
                        // driver is heading away from the restaurant, not toward it.
                        'distance_to_restaurant_km' => $o->delivery_stage === self::STAGE_ASSIGNED
                            ? $driver->distanceKmTo($restaurantLat, $restaurantLng)
                            : null,
                        'distance_to_customer_km' => $driver->distanceKmTo($customerLat, $customerLng),
                    ];
                })->values()->all(),
            ];
        })->values();
    }

    /**
     * Freelance drivers (business_id null, on duty, a fresh ping) within
     * $radiusKm of the business's own location — visibility only: the
     * business cannot assign one directly, a freelance driver still self-selects
     * from the job board. Answers "how many are actually around me right now".
     *
     * @return \Illuminate\Support\Collection<int,array>
     */
    public function nearbyFreelanceDrivers(float $originLat, float $originLng, float $radiusKm, int $limit = 50): \Illuminate\Support\Collection
    {
        return DeliveryDriver::query()
            ->whereNull('business_id')
            ->where('is_active', true)
            ->whereNotNull('last_lat')
            ->whereNotNull('last_lng')
            ->where('location_updated_at', '>=', now()->subMinutes(DeliveryDriver::LOCATION_STALE_AFTER_MINUTES))
            ->with('user:id,name,phone')
            ->get()
            ->map(fn (DeliveryDriver $driver) => [
                'driver' => $driver,
                'distance_km' => DeliveryDriver::haversineKm($originLat, $originLng, (float) $driver->last_lat, (float) $driver->last_lng),
            ])
            ->filter(fn ($row) => $row['distance_km'] <= $radiusKm)
            ->sortBy('distance_km')
            ->take($limit)
            ->map(fn ($row) => [
                'user_id' => (int) $row['driver']->user_id,
                'name' => optional($row['driver']->user)->name,
                'vehicle_label' => $row['driver']->vehicle_label,
                'distance_km' => round($row['distance_km'], 1),
            ])
            ->values();
    }

    /**
     * Delivery orders open for a driver to take: accepted by the business and at
     * least into preparation (so a driver can get ready while the food is made),
     * still pending and unassigned.
     *
     * A driver whose own `delivery_drivers.business_id` is set (a business's own
     * private driver, not the freelance pool) only ever sees THAT business's
     * orders — see driverOrFail()'s caller in DeliveryController::available().
     */
    public function availableOrders(int $limit = 50, ?int $businessId = null)
    {
        return Order::query()
            ->where('fulfillment_type', Order::FULFILLMENT_DELIVERY)
            ->where('status', self::STATUS_PENDING)
            ->whereIn('prep_status', [Order::PREP_PREPARING, Order::PREP_READY])
            ->whereNull('delivery_driver_id')
            ->when($businessId, fn ($q) => $q->where('business_id', $businessId))
            ->with('business:id,name,logo')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    // ─────────────────────────── Assignment ───────────────────────────

    /** A driver takes a ready, unassigned delivery order. */
    public function acceptOrder(int $userId, int $orderId): Order
    {
        $driver = $this->driverOrFail($userId);
        if (! $driver->is_active) {
            abort(403, __('حسابك كموصّل غير مفعّل.'));
        }

        $order = DB::transaction(function () use ($driver, $orderId) {
            $order = Order::query()->lockForUpdate()->find($orderId);

            if (! $order || (string) $order->fulfillment_type !== Order::FULFILLMENT_DELIVERY) {
                abort(404, __('طلب التوصيل غير موجود.'));
            }
            if ((string) $order->status !== self::STATUS_PENDING || $order->delivery_driver_id) {
                abort(409, __('هذا الطلب غير متاح للاستلام.'));
            }
            // A business's own private driver may only ever carry that
            // business's orders — the picker in the app already filters this
            // (availableOrders()), this is the guard against a stale/crafted id.
            if ($driver->business_id && (int) $driver->business_id !== (int) $order->business_id) {
                abort(403, __('هذا الطلب لا يخص نشاطك.'));
            }

            $order->delivery_driver_id = $driver->id;
            $order->delivery_stage = self::STAGE_ASSIGNED;

            // Fallback only: the business's own delivery_fee_amount (applied
            // at checkout, CustomerCartService::placeOrder) always wins when
            // set. A freelance driver's own rate only ever fills in a
            // delivery_fee the business never configured - never overrides
            // one the customer already saw and agreed to at checkout.
            if (
                $driver->business_id === null
                && $driver->delivery_fee_amount !== null
                && (float) $order->delivery_fee <= 0
            ) {
                $order->delivery_fee = (float) $driver->delivery_fee_amount;
                $order->final_total = round((float) $order->final_total + (float) $driver->delivery_fee_amount, 2);
            }

            $order->save();

            $driver->increment('assigned_count');

            return $order;
        });

        $this->notifyBusiness($order, 'delivery_assigned', $userId, [
            'body_ar' => 'قبِل موصّل توصيل طلبك رقم #' . $order->id . '.',
            'body_en' => 'A driver accepted delivery of your order #' . $order->id . '.',
        ]);

        return $order;
    }

    /**
     * The merchant hands a ready delivery order directly to one of ITS OWN
     * roster drivers -- the counterpart to acceptOrder() (driver-initiated,
     * from the open pool). A freelance driver (business_id null) can never
     * be assigned this way; they still only ever self-select, per
     * nearbyFreelanceDrivers()'s own docblock.
     */
    public function assignDriver(int $businessId, int $orderId, int $driverId): Order
    {
        $order = DB::transaction(function () use ($businessId, $orderId, $driverId) {
            $order = Order::query()->lockForUpdate()->find($orderId);

            if (! $order || (int) $order->business_id !== $businessId) {
                abort(404, __('طلب التوصيل غير موجود.'));
            }
            if ((string) $order->fulfillment_type !== Order::FULFILLMENT_DELIVERY) {
                throw ValidationException::withMessages(['order' => __('هذا الطلب ليس طلب توصيل.')]);
            }
            if ((string) $order->status !== self::STATUS_PENDING || $order->delivery_driver_id) {
                abort(409, __('هذا الطلب غير متاح للإسناد.'));
            }

            $driver = DeliveryDriver::query()
                ->where('id', $driverId)
                ->where('business_id', $businessId)
                ->first();

            if (! $driver) {
                abort(404, __('هذا الموصّل لا يخص نشاطك.'));
            }
            if (! $driver->is_active) {
                throw ValidationException::withMessages(['driver_id' => __('هذا الموصّل غير مفعّل حاليًا.')]);
            }

            $order->delivery_driver_id = $driver->id;
            $order->delivery_stage = self::STAGE_ASSIGNED;
            $order->save();

            $driver->increment('assigned_count');

            return $order;
        });

        $this->notifyDriver($order, 'delivery_task_assigned', $businessId, [
            'body_ar' => 'تم إسناد طلب جديد رقم #' . $order->id . ' إليك.',
            'body_en' => 'A new delivery order #' . $order->id . ' was assigned to you.',
        ]);

        return $order;
    }

    /**
     * A driver's own currently in-progress deliveries (assigned or picked
     * up) -- full Order models, ready for OrderResource, so the driver's app
     * shows the whole invoice/address/customer without a second round trip.
     */
    public function myActiveOrders(int $userId)
    {
        $driver = $this->driverOrFail($userId);

        // A delivered order stays listed until the driver has confirmed they
        // collected its delivery fee - otherwise there is no screen left to
        // do that from (confirmPaymentReceived).
        return Order::query()
            ->where('delivery_driver_id', $driver->id)
            ->where(function ($q) {
                $q->whereIn('delivery_stage', [self::STAGE_ASSIGNED, self::STAGE_PICKED_UP])
                    ->orWhere(function ($q) {
                        $q->where('delivery_stage', self::STAGE_DELIVERED)
                            ->where('delivery_fee', '>', 0)
                            ->whereNull('driver_payment_confirmed_at');
                    });
            })
            ->with(['business:id,name,logo', 'user:id,name,phone', 'items.menuItem:id,name_ar,name_en'])
            ->orderBy('id')
            ->get();
    }

    // ─────────────────────────── Stage 1: pickup ───────────────────────────

    /** The restaurant issues the one-time pickup token (shown to the driver). */
    public function issuePickupToken(Order $order, int $businessUserId): string
    {
        if ((int) $order->business_id !== $businessUserId) {
            abort(403, __('لست صاحب هذا الطلب.'));
        }
        if ((string) $order->delivery_stage !== self::STAGE_ASSIGNED) {
            throw ValidationException::withMessages(['order' => __('الطلب غير جاهز لتسليمه للموصّل.')]);
        }

        if (! $order->pickup_token) {
            $order->pickup_token = Str::random(48);
            $order->save();
        }

        return (string) $order->pickup_token;
    }

    /** The assigned driver scans the restaurant's pickup QR → picked_up. */
    public function confirmPickup(string $token, int $byUserId): Order
    {
        return DB::transaction(function () use ($token, $byUserId) {
            $order = Order::query()->where('pickup_token', $token)->lockForUpdate()->first();
            if (! $order) {
                abort(404, __('رمز الاستلام غير صالح أو تم استخدامه.'));
            }

            $driver = $order->deliveryDriver;
            if (! $driver || (int) $driver->user_id !== $byUserId) {
                abort(403, __('هذا الطلب غير مُسنَد إليك.'));
            }
            if ((string) $order->delivery_stage !== self::STAGE_ASSIGNED) {
                abort(409, __('لا يمكن تأكيد الاستلام في هذه المرحلة.'));
            }

            $order->delivery_stage = self::STAGE_PICKED_UP;
            $order->pickup_token = null; // consume
            $order->save();

            $driver->increment('picked_up_count');

            return $order;
        });
    }

    // ─────────────────────────── Stage 2: delivery ───────────────────────────

    /** The assigned driver issues the one-time delivery token (shown to the customer). */
    public function issueDeliveryToken(int $orderId, int $driverUserId): Order
    {
        $order = Order::query()->findOrFail($orderId);

        $driver = $order->deliveryDriver;
        if (! $driver || (int) $driver->user_id !== $driverUserId) {
            abort(403, __('هذا الطلب غير مُسنَد إليك.'));
        }
        if ((string) $order->delivery_stage !== self::STAGE_PICKED_UP) {
            throw ValidationException::withMessages(['order' => __('لم يتم استلام الطلب من المطعم بعد.')]);
        }

        if (! $order->delivery_token) {
            $order->delivery_token = Str::random(48);
            $order->save();
        }

        return $order;
    }

    /**
     * The assigned driver tells the customer roughly when to expect the
     * order — either a plain "in N minutes" or a specific clock time, the
     * driver's own call. One or the other, never both; whichever arrives is
     * normalised to a single timestamp before it reaches the customer.
     */
    public function notifyEta(int $orderId, int $driverUserId, ?int $etaMinutes, ?string $etaAt): Order
    {
        $order = Order::query()->find($orderId);
        if (! $order || (string) $order->fulfillment_type !== Order::FULFILLMENT_DELIVERY) {
            abort(404, __('طلب التوصيل غير موجود.'));
        }

        $driver = $order->deliveryDriver;
        if (! $driver || (int) $driver->user_id !== $driverUserId) {
            abort(403, __('هذا الطلب غير مُسنَد إليك.'));
        }
        if (! in_array((string) $order->delivery_stage, [self::STAGE_ASSIGNED, self::STAGE_PICKED_UP], true)) {
            throw ValidationException::withMessages(['order' => __('لا يمكن تحديث موعد التوصيل في هذه المرحلة.')]);
        }

        $eta = $etaAt ? Carbon::parse($etaAt) : now()->addMinutes((int) $etaMinutes);
        $time = $eta->translatedFormat('h:i A');

        // Persisted on the order itself, not just in the notification's own
        // JSON meta - confirmDelivery() reads this back to decide the
        // on-time badge. A later call simply overwrites it with whatever
        // the driver says now (the customer only ever sees the latest one).
        $order->delivery_eta_at = $eta;
        $order->save();

        $this->notifyOrderCustomer($order, 'delivery_eta_updated', $driverUserId, [
            'body_ar' => 'موصّلك فى الطريق، من المتوقع وصول طلبك رقم #' . $order->id . ' الساعة ' . $time . '.',
            'body_en' => 'Your driver is on the way — order #' . $order->id . ' is expected around ' . $time . '.',
            'meta' => ['order_id' => (int) $order->id, 'eta_at' => $eta->toIso8601String()],
        ]);

        return $order;
    }

    /**
     * The customer scans the driver's delivery QR → completed. Notifies the
     * restaurant and records the success for both the restaurant and the driver.
     */
    public function confirmDelivery(string $token, int $byUserId): Order
    {
        $order = DB::transaction(function () use ($token, $byUserId) {
            $order = Order::query()->where('delivery_token', $token)->lockForUpdate()->first();
            if (! $order) {
                abort(404, __('رمز التسليم غير صالح أو تم استخدامه.'));
            }
            if ((int) $order->user_id !== $byUserId) {
                abort(403, __('هذا الطلب ليس طلبك.'));
            }
            if ((string) $order->delivery_stage !== self::STAGE_PICKED_UP) {
                abort(409, __('لا يمكن تأكيد التسليم في هذه المرحلة.'));
            }

            $driver = $order->deliveryDriver;
            $completedAt = now();

            // Only ever claimed against a real promise: no delivery_eta_at
            // means the driver never sent one, so this stays null rather
            // than defaulting to true or false.
            $onTime = $order->delivery_eta_at ? $completedAt->lessThanOrEqualTo($order->delivery_eta_at) : null;

            $order->status = self::STATUS_COMPLETED;
            $order->delivery_stage = self::STAGE_DELIVERED;
            $order->handover_confirmed_at = $completedAt;
            $order->delivery_token = null; // consume
            $order->save();

            if ($driver) {
                $driver->increment('delivered_count');
                if ($onTime === true) {
                    $driver->increment('fast_delivery_count');
                }

                // The success ledger — one row per delivered order, counted for
                // both the restaurant (business_id) and the driver. Business-
                // owned or freelance makes no difference here - same row shape,
                // same counters, same "توصيل سريع" eligibility either way.
                DeliveryCompletion::firstOrCreate(
                    ['order_id' => $order->id],
                    [
                        'business_id' => (int) $order->business_id,
                        'delivery_driver_id' => (int) $driver->id,
                        'driver_user_id' => (int) $driver->user_id,
                        'completed_at' => $completedAt,
                        'on_time' => $onTime,
                    ]
                );
            }

            return $order;
        });

        // Operation rating: a delivered order is a success for both the
        // restaurant and the customer.
        $this->ratingService->recordForBothParties(
            businessUserId: (int) $order->business_id,
            clientUserId: (int) $order->user_id,
            outcome: RatingOutcomeEvent::OUTCOME_SUCCESS,
            operationType: RatingOutcomeEvent::OP_ORDER,
            operationId: (int) $order->id,
        );

        $businessName = optional($order->business)->name;

        $this->notifyBusiness($order, 'menu_order_completed', $byUserId, [
            'body_ar' => 'تم توصيل الطلب رقم #' . $order->id . ' للعميل بنجاح.',
            'body_en' => 'Order #' . $order->id . ' was delivered to the customer successfully.',
        ]);

        $this->notifyOrderCustomer($order, 'menu_order_completed', (int) $order->business_id, [
            'body_ar' => 'تم توصيل طلبك رقم #' . $order->id . ($businessName ? ' من ' . $businessName : '') . ' بنجاح.',
            'body_en' => 'Your order #' . $order->id . ($businessName ? ' from ' . $businessName : '') . ' was delivered successfully.',
        ]);

        return $order;
    }

    /**
     * The driver confirms they collected the delivery_fee in cash from the
     * customer — the driver's own leg only, separate from the order amount
     * the merchant confirms (OrderController::businessConfirmPayment).
     * Whoever this money belongs to (this business's own driver, or a
     * freelancer keeping it themselves) is exactly who is attesting to it.
     * Gated on the order actually being delivered - before that there is no
     * cash in hand yet to confirm.
     */
    public function confirmPaymentReceived(int $orderId, int $driverUserId): Order
    {
        $order = Order::query()->find($orderId);
        if (! $order || (string) $order->fulfillment_type !== Order::FULFILLMENT_DELIVERY) {
            abort(404, __('طلب التوصيل غير موجود.'));
        }

        $driver = $order->deliveryDriver;
        if (! $driver || (int) $driver->user_id !== $driverUserId) {
            abort(403, __('هذا الطلب غير مُسنَد إليك.'));
        }
        if ((string) $order->delivery_stage !== self::STAGE_DELIVERED) {
            abort(409, __('لا يمكن تأكيد استلام رسوم التوصيل قبل تسليم الطلب.'));
        }
        if ($order->driver_payment_confirmed_at) {
            abort(409, __('سبق تأكيد استلام رسوم التوصيل.'));
        }

        $order->driver_payment_confirmed_at = now();
        $order->save();
        $order->settlePaymentsIfComplete();

        return $order;
    }

    /** How many drivers one newly-available order pings, at most. */
    private const AVAILABLE_NOTIFY_LIMIT = 50;

    /**
     * A delivery order just became takeable (the business started preparing
     * it): ping the drivers who could take it - the business's own active
     * drivers plus the active freelance pool - nearest first when their
     * position is fresh, capped so one order never fans out to everyone.
     * Best-effort; a failed push must never break the business's action.
     */
    public function notifyDriversOrderAvailable(Order $order): void
    {
        if ((string) $order->fulfillment_type !== Order::FULFILLMENT_DELIVERY || $order->delivery_driver_id) {
            return;
        }

        try {
            $business = User::query()->find($order->business_id);
            $lat = $business && $business->latitude !== null ? (float) $business->latitude : null;
            $lng = $business && $business->longitude !== null ? (float) $business->longitude : null;

            $drivers = DeliveryDriver::query()
                ->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('business_id')->orWhere('business_id', (int) $order->business_id))
                ->get()
                ->sortBy(function (DeliveryDriver $d) use ($lat, $lng) {
                    $fresh = $lat !== null && $lng !== null && $d->last_lat !== null && $d->last_lng !== null
                        && $d->location_updated_at && $d->location_updated_at->gt(now()->subMinutes(DeliveryDriver::LOCATION_STALE_AFTER_MINUTES));

                    return $fresh
                        ? DeliveryDriver::haversineKm($lat, $lng, (float) $d->last_lat, (float) $d->last_lng)
                        : PHP_INT_MAX;
                })
                ->take(self::AVAILABLE_NOTIFY_LIMIT);

            $businessName = optional($business)->name;
            foreach ($drivers as $driver) {
                if ((int) $driver->user_id === (int) $order->business_id) {
                    continue;
                }
                $this->notifications->dispatch('delivery_order_available', (int) $driver->user_id, [
                    'type' => AppNotification::TYPE_SYSTEM,
                    'actor_id' => (int) $order->business_id,
                    'body_ar' => 'طلب توصيل جديد متاح' . ($businessName ? ' من ' . $businessName : '') . ' — رسوم التوصيل ' . (float) $order->delivery_fee . '.',
                    'body_en' => 'A new delivery order is available' . ($businessName ? ' from ' . $businessName : '') . ' - delivery fee ' . (float) $order->delivery_fee . '.',
                    'action_type' => 'open_available_orders',
                    'notifiable_type' => Order::class,
                    'notifiable_id' => (int) $order->id,
                    'source_id' => (int) $order->id,
                    'meta' => ['order_id' => (int) $order->id, 'business_id' => (int) $order->business_id],
                ]);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Notify the order's assigned driver through the full pipeline. Best-effort. */
    private function notifyDriver(Order $order, string $eventKey, int $actorId, array $data): void
    {
        $driverUserId = optional($order->deliveryDriver)->user_id;
        if (! $driverUserId) {
            return;
        }

        try {
            $this->notifications->dispatch($eventKey, (int) $driverUserId, array_merge([
                'type' => AppNotification::TYPE_SYSTEM,
                'actor_id' => $actorId,
                'notifiable_type' => Order::class,
                'notifiable_id' => (int) $order->id,
                'source_id' => (int) $order->id,
                // A driver is a mobile-app user, never a business-panel
                // operator -- see NotificationDispatcherService::dispatch()'s
                // doc on skip_realtime for why this matters.
                'skip_realtime' => true,
                'meta' => ['order_id' => (int) $order->id],
            ], $data));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Notify the order's restaurant through the full pipeline. Best-effort. */
    private function notifyBusiness(Order $order, string $eventKey, int $actorId, array $data): void
    {
        $businessId = (int) $order->business_id;
        if ($businessId <= 0) {
            return;
        }

        try {
            $this->notifications->dispatch($eventKey, $businessId, array_merge([
                'type' => AppNotification::TYPE_SYSTEM,
                'actor_id' => $actorId,
                'notifiable_type' => Order::class,
                'notifiable_id' => (int) $order->id,
                'source_id' => (int) $order->id,
                'action_type' => 'open_business_order',
                'action_url' => '/business/orders/' . $order->id,
                'meta' => ['order_id' => (int) $order->id, 'delivery_driver_id' => (int) $order->delivery_driver_id],
            ], $data));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Notify the order's customer(s) through the full pipeline. Best-effort. */
    private function notifyOrderCustomer(Order $order, string $eventKey, int $actorId, array $data): void
    {
        $ids = [(int) $order->user_id];
        if ($order->is_shared) {
            $ids = array_merge($ids, $order->participants()->pluck('user_id')->all());
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($id) => $id > 0)));

        foreach ($ids as $recipientId) {
            try {
                $this->notifications->dispatch($eventKey, $recipientId, array_merge([
                    'type' => AppNotification::TYPE_SYSTEM,
                    'actor_id' => $actorId,
                    'notifiable_type' => Order::class,
                    'notifiable_id' => (int) $order->id,
                    'source_id' => (int) $order->id,
                    'action_type' => 'open_customer_order',
                    'action_url' => '/orders/' . $order->id,
                    'skip_realtime' => true,
                    'meta' => ['order_id' => (int) $order->id, 'business_id' => (int) $order->business_id],
                ], $data));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
