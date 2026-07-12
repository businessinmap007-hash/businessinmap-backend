<?php

namespace App\Services;

use App\Models\BusinessCatalogListing;
use App\Models\MenuItem;
use App\Models\MenuItemExtra;
use App\Models\MenuItemVariant;
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
 *
 * Menu items may carry a chosen variant (size_id) and extras (addons). Price is
 * always resolved server-side from the offering + its variant/extras — never
 * trusted from the client. Two lines of the same item with different
 * variant/extras stay distinct; identical selections merge (quantities add).
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
     * Add an offering to the customer's cart. $options may carry menu
     * customisation: ['size_id' => int, 'extras' => [id, ...] | [['id'=>,'qty'=>], ...]].
     * Adding an identical selection again increments its quantity.
     */
    public function addItem(int $userId, string $kind, int $offeringId, int $qty, array $options = []): Order
    {
        $qty = max(1, $qty);
        [$businessId, $offeringType, $price, $menuId, $sizeId, $addons] =
            $this->resolveOffering($kind, $offeringId, $options);

        return DB::transaction(function () use ($userId, $businessId, $offeringType, $offeringId, $price, $menuId, $sizeId, $addons, $qty) {
            $cart = $this->draftFor($userId, $businessId);

            $signature = $this->lineSignature($sizeId, $addons);

            $line = $cart->items()
                ->where('offering_type', $offeringType)
                ->where('offering_id', $offeringId)
                ->get()
                ->first(fn (OrderItem $l) => $this->lineSignature($l->size_id, $l->addons) === $signature);

            if ($line) {
                $this->setQty($line, (int) $line->qty + $qty);
            } else {
                $this->orders->addOffering($cart, $offeringType, $offeringId, $qty, $price, $menuId, $sizeId, $addons);
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

    /**
     * Resolve [business_id, offering_type, unit_price, menu_id, size_id, addons]
     * from a kind + id (+ menu customisation options).
     */
    private function resolveOffering(string $kind, int $offeringId, array $options): array
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

            return [(int) $listing->business_id, $type, (float) $listing->price, null, null, null];
        }

        $menu = MenuItem::query()->where('is_active', 1)->find($offeringId);
        if (! $menu) {
            throw ValidationException::withMessages(['offering_id' => 'الصنف غير متاح.']);
        }

        $base = (float) $menu->base_price;
        $unit = $base;
        $sizeId = null;

        // Variant (size) — must belong to the item and be active.
        if (! empty($options['size_id'])) {
            $variant = MenuItemVariant::query()
                ->where('menu_item_id', $menu->id)
                ->where('is_active', 1)
                ->find((int) $options['size_id']);

            if (! $variant) {
                throw ValidationException::withMessages(['size_id' => 'الحجم المختار غير متاح.']);
            }

            $unit = $variant->resolvePrice($base);
            $sizeId = (int) $variant->id;
        }

        // Extras (add-ons) — each must belong to the item and be active.
        $addons = $this->resolveExtras($menu->id, $options['extras'] ?? []);
        foreach ($addons as $addon) {
            $unit += (float) $addon['price'] * (int) $addon['qty'];
        }

        return [
            (int) $menu->business_id,
            $type,
            round($unit, 2),
            (int) $menu->id,
            $sizeId,
            $addons ?: null,
        ];
    }

    /**
     * Normalise + validate the extras selection into
     * [['id','name','price','qty'], ...]. Accepts a list of ids or of
     * ['id'=>, 'qty'=>] pairs. Each extra must belong to the item and be active.
     */
    private function resolveExtras(int $menuItemId, $extras): array
    {
        if (! is_array($extras) || empty($extras)) {
            return [];
        }

        // qty per requested extra id (default 1).
        $wanted = [];
        foreach ($extras as $e) {
            if (is_array($e)) {
                $id = (int) ($e['id'] ?? 0);
                $qty = max(1, (int) ($e['qty'] ?? 1));
            } else {
                $id = (int) $e;
                $qty = 1;
            }
            if ($id > 0) {
                $wanted[$id] = ($wanted[$id] ?? 0) + $qty;
            }
        }

        if (empty($wanted)) {
            return [];
        }

        $rows = MenuItemExtra::query()
            ->where('menu_item_id', $menuItemId)
            ->where('is_active', 1)
            ->whereIn('id', array_keys($wanted))
            ->get();

        if ($rows->count() !== count($wanted)) {
            throw ValidationException::withMessages(['extras' => 'إحدى الإضافات المختارة غير متاحة.']);
        }

        // Stable order (by id) so the line signature is deterministic.
        return $rows->sortBy('id')->map(function (MenuItemExtra $x) use ($wanted) {
            $qty = min((int) $wanted[$x->id], (int) ($x->max_qty ?: 1));

            return [
                'id' => (int) $x->id,
                'name' => (string) ($x->name_ar ?: $x->name_en ?: ('Extra #' . $x->id)),
                'price' => round((float) $x->price, 2),
                'qty' => max(1, $qty),
            ];
        })->values()->all();
    }

    /**
     * A stable signature for a line's customisation, so identical selections
     * merge and different ones stay distinct. Built from size_id + the sorted
     * (extra id, qty) pairs only — names/prices are derived, not part of identity.
     */
    private function lineSignature(?int $sizeId, $addons): string
    {
        $pairs = [];
        foreach ((is_array($addons) ? $addons : []) as $a) {
            $pairs[] = [(int) ($a['id'] ?? 0), (int) ($a['qty'] ?? 1)];
        }
        sort($pairs);

        return md5(json_encode(['s' => (int) $sizeId, 'e' => $pairs]));
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
