<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\DeliveryCompletion;
use App\Models\DeliveryDriver;
use App\Models\Order;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
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
            abort(403, 'لست مسجّلاً كموصّل.');
        }

        return $driver;
    }

    /** Ready delivery orders not yet taken by a driver. */
    public function availableOrders(int $limit = 50)
    {
        return Order::query()
            ->where('fulfillment_type', Order::FULFILLMENT_DELIVERY)
            ->where('status', self::STATUS_PENDING)
            ->whereNull('delivery_driver_id')
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
            abort(403, 'حسابك كموصّل غير مفعّل.');
        }

        $order = DB::transaction(function () use ($driver, $orderId) {
            $order = Order::query()->lockForUpdate()->find($orderId);

            if (! $order || (string) $order->fulfillment_type !== Order::FULFILLMENT_DELIVERY) {
                abort(404, 'طلب التوصيل غير موجود.');
            }
            if ((string) $order->status !== self::STATUS_PENDING || $order->delivery_driver_id) {
                abort(409, 'هذا الطلب غير متاح للاستلام.');
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
            abort(403, 'لست صاحب هذا الطلب.');
        }
        if ((string) $order->delivery_stage !== self::STAGE_ASSIGNED) {
            throw ValidationException::withMessages(['order' => 'الطلب غير جاهز لتسليمه للموصّل.']);
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
                abort(404, 'رمز الاستلام غير صالح أو تم استخدامه.');
            }

            $driver = $order->deliveryDriver;
            if (! $driver || (int) $driver->user_id !== $byUserId) {
                abort(403, 'هذا الطلب غير مُسنَد إليك.');
            }
            if ((string) $order->delivery_stage !== self::STAGE_ASSIGNED) {
                abort(409, 'لا يمكن تأكيد الاستلام في هذه المرحلة.');
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
            abort(403, 'هذا الطلب غير مُسنَد إليك.');
        }
        if ((string) $order->delivery_stage !== self::STAGE_PICKED_UP) {
            throw ValidationException::withMessages(['order' => 'لم يتم استلام الطلب من المطعم بعد.']);
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
                abort(404, 'رمز التسليم غير صالح أو تم استخدامه.');
            }
            if ((int) $order->user_id !== $byUserId) {
                abort(403, 'هذا الطلب ليس طلبك.');
            }
            if ((string) $order->delivery_stage !== self::STAGE_PICKED_UP) {
                abort(409, 'لا يمكن تأكيد التسليم في هذه المرحلة.');
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
