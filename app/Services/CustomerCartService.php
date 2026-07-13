<?php

namespace App\Services;

use App\Models\BusinessCatalogListing;
use App\Models\MenuItem;
use App\Models\MenuItemExtra;
use App\Models\MenuItemVariant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderParticipant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The customer's cart, unified over the offering layer (Phase 3d). A cart IS a
 * draft Order (status='cart') and its lines are the same polymorphic
 * `order_items` used by real orders, so checkout is just a status flip.
 *
 * Two shapes share the same rails:
 *  - Personal cart: one per (user, business); the owner is orders.user_id and
 *    lines carry added_by_user_id = null.
 *  - Shared (group) cart: the host owns the Order; friends join via a share
 *    token and each adds lines attributed by added_by_user_id. The host pays one
 *    invoice at checkout. See order_participants + the 2026_07_15 migration.
 *
 * Prices (incl. menu variants/extras) are always resolved server-side.
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

    public function __construct(
        protected MenuOrderService $orders,
        protected MenuBillingService $billing,
    ) {
    }

    // ─────────────────────────── Personal cart ───────────────────────────

    /**
     * Add an offering to the customer's personal cart. $options may carry menu
     * customisation: ['size_id' => int, 'extras' => [id, ...]].
     */
    public function addItem(int $userId, string $kind, int $offeringId, int $qty, array $options = []): Order
    {
        $qty = max(1, $qty);
        $resolved = $this->resolveOffering($kind, $offeringId, $options);
        $businessId = $resolved[0];

        return DB::transaction(function () use ($userId, $businessId, $offeringId, $resolved, $qty) {
            $cart = $this->draftFor($userId, $businessId);
            $this->mergeOrCreateLine($cart, $offeringId, $resolved, $qty, null);
            $this->orders->recalc($cart);

            return $cart->refresh();
        });
    }

    /** Change a personal-cart line's quantity. qty<=0 removes the line. */
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

    /** Remove a line from the personal cart. */
    public function removeItem(int $userId, int $itemId): Order
    {
        $line = $this->scopedItem($userId, $itemId);
        $cart = $line->order;

        $line->delete();
        $this->orders->recalc($cart);

        return $cart->refresh();
    }

    /** All of the customer's own carts (one per business), with items loaded. */
    public function carts(int $userId): Collection
    {
        return Order::query()
            ->where('user_id', $userId)
            ->where('status', self::STATUS_CART)
            ->with(['items', 'business:id,name,logo'])
            ->orderByDesc('id')
            ->get();
    }

    /** One business's personal cart for the customer, or null if empty. */
    public function cartFor(int $userId, int $businessId): ?Order
    {
        return Order::query()
            ->where('user_id', $userId)
            ->where('business_id', $businessId)
            ->where('status', self::STATUS_CART)
            ->with(['items', 'business:id,name,logo'])
            ->first();
    }

    /** Turn a business's personal cart into a pending order. */
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

            $this->placeOrder($cart, $data);

            return $cart->refresh();
        });
    }

    // ─────────────────────────── Shared cart ───────────────────────────

    /**
     * Open the host's cart for a business as a shared cart, returning it with a
     * share token. Idempotent — re-sharing keeps the same token.
     */
    public function share(int $hostId, int $businessId): Order
    {
        return DB::transaction(function () use ($hostId, $businessId) {
            $cart = $this->draftFor($hostId, $businessId);

            if (! $cart->share_token) {
                $cart->share_token = Str::random(40);
            }
            $cart->is_shared = true;
            $cart->save();

            OrderParticipant::firstOrCreate(
                ['order_id' => $cart->id, 'user_id' => $hostId],
                ['role' => OrderParticipant::ROLE_HOST]
            );

            return $cart->fresh(['participants.user:id,name', 'business:id,name,logo']);
        });
    }

    /** Join a shared cart by its token (must be an open, shared cart). */
    public function join(int $userId, string $token): Order
    {
        $cart = Order::query()
            ->where('share_token', $token)
            ->where('is_shared', 1)
            ->where('status', self::STATUS_CART)
            ->firstOrFail();

        OrderParticipant::firstOrCreate(
            ['order_id' => $cart->id, 'user_id' => $userId],
            ['role' => OrderParticipant::ROLE_MEMBER]
        );

        return $this->sharedCartFor($userId, (int) $cart->id);
    }

    /** Add an offering to a shared cart, attributed to the adder. */
    public function addToShared(int $userId, int $orderId, string $kind, int $offeringId, int $qty, array $options = []): Order
    {
        $this->participantOrFail($userId, $orderId);
        $qty = max(1, $qty);
        $resolved = $this->resolveOffering($kind, $offeringId, $options);

        DB::transaction(function () use ($orderId, $offeringId, $resolved, $qty, $userId) {
            $cart = Order::query()
                ->where('status', self::STATUS_CART)
                ->where('is_shared', 1)
                ->lockForUpdate()
                ->findOrFail($orderId);

            $this->mergeOrCreateLine($cart, $offeringId, $resolved, $qty, $userId);
            $this->orders->recalc($cart);
        });

        return $this->sharedCartFor($userId, $orderId);
    }

    /** Change a shared-cart line's quantity (adder or host only). qty<=0 removes. */
    public function updateSharedLine(int $userId, int $orderId, int $itemId, int $qty): Order
    {
        $line = $this->editableSharedLine($userId, $orderId, $itemId);
        $cart = $line->order;

        if ($qty <= 0) {
            $line->delete();
        } else {
            $this->setQty($line, $qty);
        }

        $this->orders->recalc($cart);

        return $this->sharedCartFor($userId, $orderId);
    }

    /** Remove a shared-cart line (adder or host only). */
    public function removeSharedLine(int $userId, int $orderId, int $itemId): Order
    {
        $line = $this->editableSharedLine($userId, $orderId, $itemId);
        $cart = $line->order;

        $line->delete();
        $this->orders->recalc($cart);

        return $this->sharedCartFor($userId, $orderId);
    }

    /** Place a shared cart as a pending order — host only, one invoice. */
    public function checkoutShared(int $hostId, int $orderId, array $data): Order
    {
        $participant = $this->participantOrFail($hostId, $orderId);

        if (! $participant->isHost()) {
            abort(403, 'المضيف فقط يمكنه إتمام الطلب.');
        }

        return DB::transaction(function () use ($orderId, $data) {
            $cart = Order::query()
                ->where('status', self::STATUS_CART)
                ->where('is_shared', 1)
                ->lockForUpdate()
                ->findOrFail($orderId);

            if ($cart->items()->count() === 0) {
                throw ValidationException::withMessages(['cart' => 'السلة فارغة.']);
            }

            $this->placeOrder($cart, $data);

            return $cart->refresh();
        });
    }

    /** A member leaves a shared cart — their lines are removed. Host can't leave. */
    public function leaveShared(int $userId, int $orderId): void
    {
        $participant = $this->participantOrFail($userId, $orderId);

        if ($participant->isHost()) {
            abort(422, 'المضيف لا يمكنه المغادرة.');
        }

        DB::transaction(function () use ($userId, $orderId, $participant) {
            OrderItem::query()->where('order_id', $orderId)->where('added_by_user_id', $userId)->delete();
            $participant->delete();

            $cart = Order::find($orderId);
            if ($cart) {
                $this->orders->recalc($cart);
            }
        });
    }

    /** Load a shared cart the user participates in (403 otherwise). */
    public function sharedCartFor(int $userId, int $orderId): Order
    {
        $this->participantOrFail($userId, $orderId);

        return Order::query()
            ->where('is_shared', 1)
            ->with([
                'items.addedBy:id,name',
                'participants.user:id,name',
                'business:id,name,logo',
            ])
            ->findOrFail($orderId);
    }

    // ─────────────────────────── Internals ───────────────────────────

    /**
     * Apply fulfilment/payment fields, flip a draft cart to pending, and persist
     * the menu service fee + tax on the order (final_total includes them).
     */
    private function placeOrder(Order $cart, array $data): void
    {
        $cart->fulfillment_type = $data['fulfillment_type'] ?? $cart->fulfillment_type ?: Order::FULFILLMENT_DELIVERY;
        $cart->address = (string) ($data['address'] ?? $cart->address ?? '');
        $cart->notes = $data['notes'] ?? $cart->notes;
        $cart->payment_method = (string) ($data['payment_method'] ?? $cart->payment_method ?: 'cash');
        $cart->status = self::STATUS_PENDING;
        $cart->save();

        // total = raw food; then fold in the service fee + tax (+ retail, - discount).
        $this->orders->recalc($cart);

        $bill = $this->billing->orderBill($cart);
        $delivery = round((float) $cart->delivery_fee, 2);
        $discount = round((float) $cart->discount, 2);

        $cart->service_fee = $bill['service_fee'];
        $cart->tax = $bill['tax'];
        $cart->final_total = round($bill['menu_payable'] + $bill['retail_subtotal'] + $delivery - $discount, 2);
        $cart->save();
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
     * Merge into an existing identical line (same offering + size + extras +
     * adder) or create a new one on the given cart.
     */
    private function mergeOrCreateLine(Order $cart, int $offeringId, array $resolved, int $qty, ?int $addedBy): void
    {
        [$businessId, $offeringType, $price, $menuId, $sizeId, $addons] = $resolved;

        if ((int) $cart->business_id !== (int) $businessId) {
            throw ValidationException::withMessages(['offering_id' => 'هذا العرض لا يخص نشاط هذه السلة.']);
        }

        $signature = $this->lineSignature($sizeId, $addons, $addedBy);

        $line = $cart->items()
            ->where('offering_type', $offeringType)
            ->where('offering_id', $offeringId)
            ->when($addedBy !== null, fn ($q) => $q->where('added_by_user_id', $addedBy))
            ->when($addedBy === null, fn ($q) => $q->whereNull('added_by_user_id'))
            ->get()
            ->first(fn (OrderItem $l) => $this->lineSignature($l->size_id, $l->addons, $l->added_by_user_id) === $signature);

        if ($line) {
            $this->setQty($line, (int) $line->qty + $qty);
        } else {
            $this->orders->addOffering($cart, $offeringType, $offeringId, $qty, $price, $menuId, $sizeId, $addons, $addedBy);
        }
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

        $addons = $this->resolveExtras($menu->id, $options['extras'] ?? []);
        foreach ($addons as $addon) {
            $unit += (float) $addon['price'] * (int) $addon['qty'];
        }

        return [(int) $menu->business_id, $type, round($unit, 2), (int) $menu->id, $sizeId, $addons ?: null];
    }

    /**
     * Normalise + validate the extras selection into
     * [['id','name','price','qty'], ...]. Each extra must belong to the item.
     */
    private function resolveExtras(int $menuItemId, $extras): array
    {
        if (! is_array($extras) || empty($extras)) {
            return [];
        }

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
     * A stable signature for a line's identity: size_id + sorted (extra id, qty)
     * pairs + the adder. Including the adder keeps two people's identical items
     * on separate, attributable lines while merging one person's repeats.
     */
    private function lineSignature(?int $sizeId, $addons, ?int $addedBy = null): string
    {
        $pairs = [];
        foreach ((is_array($addons) ? $addons : []) as $a) {
            $pairs[] = [(int) ($a['id'] ?? 0), (int) ($a['qty'] ?? 1)];
        }
        sort($pairs);

        return md5(json_encode(['s' => (int) $sizeId, 'e' => $pairs, 'u' => (int) $addedBy]));
    }

    /** A personal-cart line owned by the customer (never a placed order). */
    private function scopedItem(int $userId, int $itemId): OrderItem
    {
        return OrderItem::query()
            ->whereKey($itemId)
            ->whereHas('order', fn ($q) => $q
                ->where('user_id', $userId)
                ->where('status', self::STATUS_CART))
            ->firstOrFail();
    }

    /** The user's participant row on a shared cart, or 403. */
    private function participantOrFail(int $userId, int $orderId): OrderParticipant
    {
        $participant = OrderParticipant::query()
            ->where('order_id', $orderId)
            ->where('user_id', $userId)
            ->first();

        if (! $participant) {
            abort(403, 'لست مشاركاً في هذه السلة.');
        }

        return $participant;
    }

    /** A shared-cart line the user may edit (its adder, or the host). */
    private function editableSharedLine(int $userId, int $orderId, int $itemId): OrderItem
    {
        $participant = $this->participantOrFail($userId, $orderId);

        $line = OrderItem::query()->where('order_id', $orderId)->find($itemId);
        if (! $line) {
            abort(404, 'السطر غير موجود.');
        }

        if (! $participant->isHost() && (int) $line->added_by_user_id !== $userId) {
            abort(403, 'لا يمكنك تعديل طلب مشارك آخر.');
        }

        return $line;
    }

    private function setQty(OrderItem $line, int $qty): void
    {
        $qty = max(1, $qty);
        $line->qty = $qty;
        $line->total_price = round((float) $line->price * $qty, 2);
        $line->save();
    }
}
