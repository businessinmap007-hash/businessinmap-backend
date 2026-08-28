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

    /** The active driver row for a user, or 403. */
    public function driverOrFail(int $userId): DeliveryDriver
    {
        $driver = DeliveryDriver::query()->where('user_id', $userId)->first();
        if (! $driver) {
            abort(403, __('لست مسجّلاً كموصّل.'));
        }

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
     * @return \Illuminate\Support\Collection<int,array>
     */
    public function businessRoster(int $businessId): \Illuminate\Support\Collection
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
            ->get(['id', 'delivery_driver_id', 'delivery_stage', 'address', 'final_total'])
            ->groupBy('delivery_driver_id');

        $deliveredToday = DeliveryCompletion::query()
            ->whereIn('delivery_driver_id', $driverIds)
            ->whereDate('completed_at', now()->toDateString())
            ->selectRaw('delivery_driver_id, COUNT(*) as c')
            ->groupBy('delivery_driver_id')
            ->pluck('c', 'delivery_driver_id');

        return $drivers->map(function (DeliveryDriver $driver) use ($activeOrders, $deliveredToday) {
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
                'active_orders' => $orders->map(fn ($o) => [
                    'order_id' => (int) $o->id,
                    // assigned = تم الاستلام من المطعم لم يبدأ بعد، picked_up = في الطريق
                    'stage' => (string) $o->delivery_stage,
                    'address' => (string) $o->address,
                    'final_total' => (float) $o->final_total,
                ])->values()->all(),
            ];
        })->values();
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

            $order->status = self::STATUS_COMPLETED;
            $order->delivery_stage = self::STAGE_DELIVERED;
            $order->handover_confirmed_at = now();
            $order->delivery_token = null; // consume
            $order->save();

            if ($driver) {
                $driver->increment('delivered_count');

                // The success ledger — one row per delivered order, counted for
                // both the restaurant (business_id) and the driver.
                DeliveryCompletion::firstOrCreate(
                    ['order_id' => $order->id],
                    [
                        'business_id' => (int) $order->business_id,
                        'delivery_driver_id' => (int) $driver->id,
                        'driver_user_id' => (int) $driver->user_id,
                        'completed_at' => now(),
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

        $this->notifyBusiness($order, 'menu_order_completed', $byUserId, [
            'body_ar' => 'اكتمل توصيل طلبك رقم #' . $order->id . ' بنجاح.',
            'body_en' => 'Your order #' . $order->id . ' was delivered successfully.',
        ]);

        return $order;
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
                'meta' => ['order_id' => (int) $order->id, 'delivery_driver_id' => (int) $order->delivery_driver_id],
            ], $data));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
