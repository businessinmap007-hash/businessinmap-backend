<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
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

        $isParty = (int) $model->user_id === $userId
            || (int) $model->business_id === $userId
            || $model->participants()->where('user_id', $userId)->exists();

        if (! $isParty) {
            abort(403, 'هذا الطلب ليس طلبك.');
        }

        // Expose the customer only to the business side of the conversation.
        if ((int) $model->business_id === $userId) {
            $model->load('user:id,name,phone');
        }

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
