<?php

namespace App\Services;

use App\Models\BusinessCatalogListing;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The customer's cart, unified over the offering layer (Phase 3d). A cart IS a
 * draft Order (status='cart') — one per business — and its lines are the same
 * polymorphic `order_items` used by real orders, so checkout is just a status
 * flip and no data is copied between tables. Goods offerings only (retail
 * catalog listings + menu items); bespoke booking stays on the booking rails.
 */
class CustomerCartService
{
    public const STATUS_CART = 'cart';
    public const STATUS_PENDING = 'pending';

    /** Cart item "kind" → the offering model it resolves to. */
    private const KINDS = [
        'retail' => BusinessCatalogListing::class,
        'menu' => MenuItem::class,
    ];

    public function __construct(protected MenuOrderService $orders)
    {
    }

    /**
     * Add an offering to the customer's cart. Business and price are resolved
     * server-side from the offering — never trusted from the client. Adding the
     * same offering again increments its quantity.
     */
    public function addItem(int $userId, string $kind, int $offeringId, int $qty): Order
    {
        $qty = max(1, $qty);
        [$businessId, $offeringType, $price, $menuId] = $this->resolveOffering($kind, $offeringId);

        return DB::transaction(function () use ($userId, $businessId, $offeringType, $offeringId, $price, $menuId, $qty) {
            $cart = $this->draftFor($userId, $businessId);

            $line = $cart->items()
                ->where('offering_type', $offeringType)
                ->where('offering_id', $offeringId)
                ->first();

            if ($line) {
                $this->setQty($line, (int) $line->qty + $qty);
            } else {
                $this->orders->addOffering($cart, $offeringType, $offeringId, $qty, $price, $menuId);
            }

            $this->orders->recalc($cart);

            return $cart->refresh();
        });
    }

    /** Change a line's quantity. qty<=0 removes the line. */
    public function updateItemQty(int $userId, int $itemId, int $qty): Order
    {
        $line = $this->scopedItem($userId, $itemId);
        $cart = $line->order;

        if ($qty <= 0) {
            $line->delete();
        } else {
            $this->setQty($line, $qty);
        }

        $this->orders->recalc($cart);

        return $cart->refresh();
    }

    /** Remove a line from the cart. */
    public function removeItem(int $userId, int $itemId): Order
    {
        $line = $this->scopedItem($userId, $itemId);
        $cart = $line->order;

        $line->delete();
        $this->orders->recalc($cart);

        return $cart->refresh();
    }

    /** All of the customer's carts (one per business), with items loaded. */
    public function carts(int $userId): Collection
    {
        return Order::query()
            ->where('user_id', $userId)
            ->where('status', self::STATUS_CART)
            ->with(['items', 'business:id,name,logo'])
            ->orderByDesc('id')
            ->get();
    }

    /** One business's cart for the customer, or null if empty. */
    public function cartFor(int $userId, int $businessId): ?Order
    {
        return Order::query()
            ->where('user_id', $userId)
            ->where('business_id', $businessId)
            ->where('status', self::STATUS_CART)
            ->with(['items', 'business:id,name,logo'])
            ->first();
    }

    /**
     * Turn a business's cart into a real (pending) order. The cart must have
     * items. Returns the placed order.
     */
    public function checkout(int $userId, int $businessId, array $data): Order
    {
        return DB::transaction(function () use ($userId, $businessId, $data) {
            $cart = Order::query()
                ->where('user_id', $userId)
                ->where('business_id', $businessId)
                ->where('status', self::STATUS_CART)
                ->lockForUpdate()
                ->first();

            if (! $cart || $cart->items()->count() === 0) {
                throw ValidationException::withMessages(['cart' => 'السلة فارغة.']);
            }

            $cart->fulfillment_type = $data['fulfillment_type'] ?? $cart->fulfillment_type ?: Order::FULFILLMENT_DELIVERY;
            $cart->address = (string) ($data['address'] ?? $cart->address ?? '');
            $cart->notes = $data['notes'] ?? $cart->notes;
            $cart->payment_method = (string) ($data['payment_method'] ?? $cart->payment_method ?: 'cash');
            $cart->status = self::STATUS_PENDING;
            $cart->save();

            $this->orders->recalc($cart);

            return $cart->refresh();
        });
    }

    /** Find-or-create the customer's draft order for a business. */
    private function draftFor(int $userId, int $businessId): Order
    {
        return Order::firstOrCreate(
            [
                'user_id' => $userId,
                'business_id' => $businessId,
                'status' => self::STATUS_CART,
            ],
            [
                'fulfillment_type' => Order::FULFILLMENT_DELIVERY,
                'booking_id' => null,
                'total' => 0,
                'discount' => 0,
                'delivery_fee' => 0,
                'final_total' => 0,
                'payment_method' => 'cash',
                'address' => '',
            ]
        );
    }

    /** Resolve [business_id, offering_type, price, menu_id] from a kind + id. */
    private function resolveOffering(string $kind, int $offeringId): array
    {
        $type = self::KINDS[$kind] ?? null;

        if ($type === null) {
            throw ValidationException::withMessages(['kind' => 'نوع العرض غير معروف.']);
        }

        if ($type === BusinessCatalogListing::class) {
            $listing = BusinessCatalogListing::query()->where('is_active', 1)->find($offeringId);
            if (! $listing) {
                throw ValidationException::withMessages(['offering_id' => 'المنتج غير متاح.']);
            }

            return [(int) $listing->business_id, $type, (float) $listing->price, null];
        }

        $menu = MenuItem::query()->where('is_active', 1)->find($offeringId);
        if (! $menu) {
            throw ValidationException::withMessages(['offering_id' => 'الصنف غير متاح.']);
        }

        return [(int) $menu->business_id, $type, (float) $menu->base_price, (int) $menu->id];
    }

    /** A cart line owned by the customer (only within a cart, never a placed order). */
    private function scopedItem(int $userId, int $itemId): OrderItem
    {
        return OrderItem::query()
            ->whereKey($itemId)
            ->whereHas('order', fn ($q) => $q
                ->where('user_id', $userId)
                ->where('status', self::STATUS_CART))
            ->firstOrFail();
    }

    private function setQty(OrderItem $line, int $qty): void
    {
        $qty = max(1, $qty);
        $line->qty = $qty;
        $line->total_price = round((float) $line->price * $qty, 2);
        $line->save();
    }
}
