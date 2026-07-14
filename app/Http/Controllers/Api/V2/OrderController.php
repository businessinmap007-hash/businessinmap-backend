<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\OrderResource;
use App\Models\AppNotification;
use App\Models\Order;
use App\Services\Notifications\NotificationDispatcherService;
use App\Services\OrderFeeSettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * v2 placed-order surfaces (replaces the legacy Api\V1 OrderController). Two
 * audiences over the same Order rows the v2 cart/checkout/delivery flows
 * already produce:
 *   - the customer's own order history + detail
 *   - the business's incoming-order queue + detail
 * Draft carts (status=cart) are never returned here.
 */
final class OrderController extends Controller
{
    /** Statuses a placed order can be filtered by. */
    private const PLACED_STATUSES = ['pending', 'completed', 'cancelled'];

    public function __construct(
        private readonly NotificationDispatcherService $notifications,
        private readonly OrderFeeSettlementService $feeSettlement,
        private readonly \App\Services\Ratings\RatingService $ratingService,
    ) {
    }

    // ─────────────────────────── Customer ───────────────────────────

    /** GET /api/v2/orders — the authenticated customer's placed orders. */
    public function index(Request $request)
    {
        $data = $this->filters($request);

        $orders = Order::query()
            ->where('user_id', (int) $request->user()->id)
            ->where('status', '!=', 'cart')
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($data['fulfillment_type'] ?? null, fn ($q, $t) => $q->where('fulfillment_type', $t))
            ->with('business:id,name,logo')
            ->withCount('items')
            ->latest('id')
            ->paginate($data['per_page'] ?? 20)
            ->withQueryString();

        return OrderResource::collection($orders)->additional(['success' => true]);
    }

    /** GET /api/v2/orders/{order} — detail for an order the user is party to. */
    public function show(Request $request, int $order)
    {
        $userId = (int) $request->user()->id;

        $model = Order::query()
            ->where('status', '!=', 'cart')
            ->with(['business:id,name,logo', 'items.menuItem:id,name_ar,name_en'])
            ->findOrFail($order);

        // Party-only, via OrderPolicy (throws 403 for non-parties).
        $this->authorize('view', $model);

        // Expose the customer only to the business side of the conversation.
        if ((int) $model->business_id === $userId) {
            $model->load('user:id,name,phone');
        }

        return (new OrderResource($model))->additional(['success' => true]);
    }

    /** POST /api/v2/orders/{order}/cancel — customer cancels their pending order. */
    public function cancel(Request $request, int $order)
    {
        $userId = (int) $request->user()->id;
        $reason = $this->reason($request);

        $model = $this->cancelPendingOrder(
            fn () => Order::query()->where('user_id', $userId)->lockForUpdate()->find($order),
            $reason
        );

        // Tell the restaurant the customer cancelled.
        $this->notifyCancellation($model, (int) $model->business_id, $userId, $reason, [
            'body_ar' => 'ألغى العميل الطلب رقم #' . $model->id . '.',
            'body_en' => 'The customer cancelled order #' . $model->id . '.',
        ]);

        return (new OrderResource($model))->additional(['success' => true]);
    }

    // ─────────────────────────── Business ───────────────────────────

    /** GET /api/v2/business/orders — the business's incoming-order queue. */
    public function businessIndex(Request $request)
    {
        $data = $this->filters($request);

        $orders = Order::query()
            ->where('business_id', (int) $request->user()->id)
            ->whereNull('booking_id')
            ->where('status', '!=', 'cart')
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($data['fulfillment_type'] ?? null, fn ($q, $t) => $q->where('fulfillment_type', $t))
            ->with('user:id,name,phone')
            ->withCount('items')
            ->latest('id')
            ->paginate($data['per_page'] ?? 20)
            ->withQueryString();

        return OrderResource::collection($orders)->additional(['success' => true]);
    }

    /** GET /api/v2/business/orders/{order} — detail for one of the business's orders. */
    public function businessShow(Request $request, int $order)
    {
        $model = Order::query()
            ->where('business_id', (int) $request->user()->id)
            ->whereNull('booking_id')
            ->where('status', '!=', 'cart')
            ->with(['user:id,name,phone', 'items.menuItem:id,name_ar,name_en'])
            ->findOrFail($order);

        return (new OrderResource($model))->additional(['success' => true]);
    }

    /** POST /api/v2/business/orders/{order}/reject — business rejects a pending order. */
    public function businessReject(Request $request, int $order)
    {
        $businessId = (int) $request->user()->id;
        $reason = $this->reason($request);

        $model = $this->cancelPendingOrder(
            fn () => Order::query()
                ->where('business_id', $businessId)
                ->whereNull('booking_id')
                ->lockForUpdate()
                ->find($order),
            $reason
        );

        // Tell the customer the restaurant rejected their order.
        $this->notifyCancellation($model, (int) $model->user_id, $businessId, $reason, [
            'body_ar' => 'اعتذر المطعم عن تنفيذ طلبك رقم #' . $model->id . '.',
            'body_en' => 'The restaurant could not fulfil your order #' . $model->id . '.',
        ]);

        return (new OrderResource($model))->additional(['success' => true]);
    }

    /**
     * POST /api/v2/business/orders/{order}/accept — business accepts a pending
     * order. Settles BIM's platform service fee against the business wallet
     * (blocks with 402 if the wallet can't cover it), then moves prep_status to
     * `accepted`. One-way: after this the order can no longer be cancelled/rejected.
     */
    public function businessAccept(Request $request, int $order)
    {
        $businessId = (int) $request->user()->id;

        $model = DB::transaction(function () use ($businessId, $order) {
            /** @var Order|null $m */
            $m = Order::query()
                ->where('business_id', $businessId)
                ->whereNull('booking_id')
                ->lockForUpdate()
                ->find($order);

            if (! $m) {
                abort(404, 'الطلب غير موجود.');
            }
            if ((string) $m->status !== 'pending' || $m->prep_status !== null) {
                abort(409, 'لا يمكن قبول هذا الطلب في حالته الحالية.');
            }

            // Collect BIM's fee from the business wallet (may block on balance).
            $this->feeSettlement->settleForOrder($m);

            $m->prep_status = Order::PREP_ACCEPTED;
            $m->save();

            return $m;
        });

        $this->notifyCustomer($model, 'menu_order_accepted', $businessId, [
            'body_ar' => 'قبِل المطعم طلبك رقم #' . $model->id . ' وسيبدأ التحضير.',
            'body_en' => 'The restaurant accepted your order #' . $model->id . '.',
        ]);

        return (new OrderResource($model))->additional(['success' => true]);
    }

    /** POST /api/v2/business/orders/{order}/preparing — accepted → preparing. */
    public function businessPreparing(Request $request, int $order)
    {
        $model = $this->transitionPrep($request, $order, Order::PREP_ACCEPTED, Order::PREP_PREPARING);

        $this->notifyCustomer($model, 'menu_order_preparing', (int) $request->user()->id, [
            'body_ar' => 'طلبك رقم #' . $model->id . ' قيد التحضير الآن.',
            'body_en' => 'Your order #' . $model->id . ' is now being prepared.',
        ]);

        return (new OrderResource($model))->additional(['success' => true]);
    }

    /** POST /api/v2/business/orders/{order}/ready — preparing → ready. */
    public function businessReady(Request $request, int $order)
    {
        $model = $this->transitionPrep($request, $order, Order::PREP_PREPARING, Order::PREP_READY);

        $this->notifyCustomer($model, 'menu_order_ready', (int) $request->user()->id, [
            'body_ar' => 'طلبك رقم #' . $model->id . ' جاهز.',
            'body_en' => 'Your order #' . $model->id . ' is ready.',
        ]);

        return (new OrderResource($model))->additional(['success' => true]);
    }

    /** Advance a business's order from one prep_status to the next under a lock. */
    private function transitionPrep(Request $request, int $order, string $from, string $to): Order
    {
        $businessId = (int) $request->user()->id;

        return DB::transaction(function () use ($businessId, $order, $from, $to) {
            /** @var Order|null $m */
            $m = Order::query()
                ->where('business_id', $businessId)
                ->whereNull('booking_id')
                ->lockForUpdate()
                ->find($order);

            if (! $m) {
                abort(404, 'الطلب غير موجود.');
            }
            if ((string) $m->status !== 'pending' || (string) $m->prep_status !== $from) {
                abort(409, 'لا يمكن نقل الطلب إلى هذه الحالة الآن.');
            }

            $m->prep_status = $to;
            $m->save();

            return $m;
        });
    }

    /**
     * Flip a pending order to cancelled under a row lock. `$finder` returns the
     * scoped, locked order (or null → 404). Refuses if it is not pending, already
     * accepted (prep_status set → the fee is committed), or a driver is already
     * involved (409). No refund: menu orders take no wallet hold / escrow at
     * checkout, and cancellation is only allowed before acceptance.
     */
    private function cancelPendingOrder(callable $finder, ?string $reason): Order
    {
        $model = DB::transaction(function () use ($finder, $reason) {
            /** @var Order|null $model */
            $model = $finder();
            if (! $model) {
                abort(404, 'الطلب غير موجود.');
            }
            if ((string) $model->status !== 'pending') {
                abort(409, 'لا يمكن إلغاء هذا الطلب في حالته الحالية.');
            }
            if ($model->prep_status !== null) {
                abort(409, 'لا يمكن الإلغاء بعد قبول الطلب وبدء التحضير.');
            }
            if ($model->delivery_driver_id) {
                abort(409, 'لا يمكن الإلغاء بعد إسناد موصّل للطلب.');
            }

            $model->status = 'cancelled';
            if ($reason !== null && $reason !== '') {
                $model->notes = trim((string) $model->notes . "\n[إلغاء] " . $reason);
            }
            $model->save();

            return $model;
        });

        // Operation rating: a cancelled order counts against both parties.
        $this->ratingService->recordForBothParties(
            businessUserId: (int) $model->business_id,
            clientUserId: (int) $model->user_id,
            outcome: \App\Models\RatingOutcomeEvent::OUTCOME_CANCELLED,
            operationType: \App\Models\RatingOutcomeEvent::OP_ORDER,
            operationId: (int) $model->id,
        );

        return $model;
    }

    private function reason(Request $request): ?string
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        return $data['reason'] ?? null;
    }

    /** Best-effort prep-transition notification to the order's customer. */
    private function notifyCustomer(Order $order, string $event, int $actorId, array $bodies): void
    {
        $customerId = (int) $order->user_id;
        if ($customerId <= 0) {
            return;
        }

        try {
            $this->notifications->dispatch($event, $customerId, array_merge([
                'type' => AppNotification::TYPE_OFFER,
                'actor_id' => $actorId,
                'notifiable_type' => Order::class,
                'notifiable_id' => (int) $order->id,
                'source_id' => (int) $order->id,
                'meta' => ['order_id' => (int) $order->id, 'prep_status' => $order->prep_status],
            ], $bodies));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Best-effort cancellation notification through the full pipeline. */
    private function notifyCancellation(Order $order, int $notifyUserId, int $actorId, ?string $reason, array $bodies): void
    {
        if ($notifyUserId <= 0) {
            return;
        }

        try {
            $this->notifications->dispatch('menu_order_cancelled', $notifyUserId, array_merge([
                'type' => AppNotification::TYPE_OFFER,
                'actor_id' => $actorId,
                'notifiable_type' => Order::class,
                'notifiable_id' => (int) $order->id,
                'source_id' => (int) $order->id,
                'meta' => ['order_id' => (int) $order->id, 'reason' => $reason],
            ], $bodies));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** @return array<string,mixed> */
    private function filters(Request $request): array
    {
        return $request->validate([
            'status' => ['nullable', Rule::in(self::PLACED_STATUSES)],
            'fulfillment_type' => ['nullable', Rule::in(Order::FULFILLMENT_TYPES)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
    }
}
