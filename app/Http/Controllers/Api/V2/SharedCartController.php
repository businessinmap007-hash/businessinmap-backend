<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\BusinessCatalogListing;
use App\Models\MenuBundle;
use App\Models\MenuItem;
use App\Models\Order;
use App\Services\CustomerCartService;
use App\Services\MenuBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Shared (group) cart — friends join the host's cart via a share token and each
 * adds their own lines. Payment is cash on arrival: every participant sees their
 * OWN bill (their items + their share of the service fee + tax, on their order
 * only). Every endpoint is scoped to a participant of the cart. See
 * CustomerCartService + MenuBillingService.
 */
final class SharedCartController extends Controller
{
    public function __construct(
        private readonly CustomerCartService $cart,
        private readonly MenuBillingService $billing,
    ) {
    }

    /** Open the caller's cart for a business as a shared cart. */
    public function share(Request $request, int $business)
    {
        $order = $this->cart->share((int) $request->user()->id, $business);

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => (int) $order->id,
                'share_token' => (string) $order->share_token,
                'share_path' => '/api/v2/cart/join/' . $order->share_token,
            ],
        ], 201);
    }

    /**
     * Invite a friend to the shared cart by phone or email — an
     * already-registered account only. Sends a notification carrying the
     * join token; never adds them as a participant directly.
     */
    public function invite(Request $request, int $order)
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:190'],
        ], [], ['identifier' => __('رقم الهاتف أو الإيميل')]);

        $friend = $this->cart->inviteToShared((int) $request->user()->id, $order, $data['identifier']);

        return response()->json([
            'success' => true,
            'data' => ['user' => ['id' => (int) $friend->id, 'name' => (string) $friend->name]],
        ]);
    }

    /**
     * Host-only: invites members of one of the host's own contact groups at
     * once — every member, or only the ones picked in `member_ids` (user
     * ids) when the caller sent a selection instead of "everyone".
     */
    public function inviteGroup(Request $request, int $order, int $group)
    {
        $data = $request->validate([
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'min:1'],
        ]);

        $invited = $this->cart->inviteGroupToShared(
            (int) $request->user()->id, $order, $group, $data['member_ids'] ?? null
        );

        return response()->json([
            'success' => true,
            'data' => ['invited' => collect($invited)->map(fn ($u) => ['id' => (int) $u->id, 'name' => (string) $u->name])->values()],
        ]);
    }

    /** Join a shared cart by token. */
    public function join(Request $request, string $token)
    {
        $userId = (int) $request->user()->id;
        $order = $this->cart->join($userId, $token);

        return response()->json(['success' => true, 'data' => ['cart' => $this->present($order, $userId)]], 201);
    }

    /** View a shared cart (participants + attributed items + per-person breakdown). */
    public function show(Request $request, int $order)
    {
        $userId = (int) $request->user()->id;

        return response()->json(['success' => true, 'data' => ['cart' => $this->present(
            $this->cart->sharedCartFor($userId, $order), $userId
        )]]);
    }

    /** Add an offering to the shared cart, attributed to the caller. */
    public function addItem(Request $request, int $order)
    {
        $data = $request->validate([
            'kind' => ['required', 'in:retail,menu,bundle'],
            'offering_id' => ['required', 'integer', 'min:1'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:999'],
            'size_id' => ['nullable', 'integer', 'min:1'],
            'extras' => ['nullable', 'array'],
            'extras.*' => ['integer', 'min:1'],
        ], [], ['kind' => __('نوع العرض'), 'offering_id' => __('العرض'), 'qty' => __('الكمية')]);

        $userId = (int) $request->user()->id;
        $cart = $this->cart->addToShared(
            $userId,
            $order,
            (string) $data['kind'],
            (int) $data['offering_id'],
            (int) ($data['qty'] ?? 1),
            ['size_id' => $data['size_id'] ?? null, 'extras' => $data['extras'] ?? []]
        );

        return response()->json(['success' => true, 'data' => ['cart' => $this->present($cart, $userId)]], 201);
    }

    /** Change a shared-cart line's quantity (adder or host). 0 removes. */
    public function updateItem(Request $request, int $order, int $item)
    {
        $data = $request->validate(['qty' => ['required', 'integer', 'min:0', 'max:999']], [], ['qty' => __('الكمية')]);

        $userId = (int) $request->user()->id;
        $cart = $this->cart->updateSharedLine($userId, $order, $item, (int) $data['qty']);

        return response()->json(['success' => true, 'data' => ['cart' => $this->present($cart, $userId)]]);
    }

    /** Remove a shared-cart line (adder or host). */
    public function removeItem(Request $request, int $order, int $item)
    {
        $userId = (int) $request->user()->id;
        $cart = $this->cart->removeSharedLine($userId, $order, $item);

        return response()->json(['success' => true, 'data' => ['cart' => $this->present($cart, $userId)]]);
    }

    /** Place the shared cart as a pending order (host only). */
    public function checkout(Request $request, int $order)
    {
        $data = $request->validate([
            'fulfillment_type' => ['nullable', 'in:delivery,pickup,dine_in'],
            'address_id' => ['nullable', 'integer'],
            'address' => ['nullable', 'string', 'max:500'],
            // A one-off GPS delivery pin (both required together). Ignored when
            // address_id is given. Resolved to a city line server-side.
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'notes' => ['nullable', 'string', 'max:1000'],
            // "لو الصنف نفذ، تحب نعمل إيه؟" — the host decides for the whole
            // shared order, same as any other checkout.
            'out_of_stock_policy' => ['nullable', Rule::in(Order::OUT_OF_STOCK_POLICIES)],
        ]);

        // Shared carts are cash-on-arrival; each participant pays their own share.
        $data['payment_method'] = 'cash';

        $userId = (int) $request->user()->id;
        $placed = $this->cart->checkoutShared($userId, $order, $data);

        return response()->json([
            'success' => true,
            'data' => ['order' => $this->present($placed->load([
                'items.addedBy:id,name', 'participants.user:id,name', 'business:id,name,logo',
            ]), $userId)],
        ], 201);
    }

    /** Leave a shared cart (member only; removes the caller's lines). */
    public function leave(Request $request, int $order)
    {
        $this->cart->leaveShared((int) $request->user()->id, $order);

        return response()->json(['success' => true]);
    }

    /** Cancel (discard) the shared cart — host only; members are notified. */
    public function cancel(Request $request, int $order)
    {
        $this->cart->cancelShared((int) $request->user()->id, $order);

        return response()->json(['success' => true]);
    }

    /** Serialize a shared cart with attribution + per-participant breakdown. */
    private function present(Order $order, ?int $viewerId = null): array
    {
        $order->loadMissing('items.addedBy:id,name', 'participants.user:id,name', 'business:id,name,logo');
        $names = $this->displayNames($order);
        $sizeNames = $this->sizeNames($order);

        $items = $order->items->map(fn ($line) => [
            'id' => (int) $line->id,
            'kind' => match ($line->offering_type) {
                BusinessCatalogListing::class => 'retail',
                MenuBundle::class => 'bundle',
                default => 'menu',
            },
            'offering_id' => (int) $line->offering_id,
            // the label frozen when the line was added wins: it says what the
            // customer picked («غرفة نوم — مودرن»), not what the item is called today
            'name' => $line->offering_label
                ?: ($names[(string) $line->offering_type][(int) $line->offering_id]
                    ?? ('#' . ($line->offering_id ?: $line->menu_id))),
            'options' => [
                'size' => $line->size_id ? ($sizeNames[(int) $line->size_id] ?? null) : null,
                'extras' => collect(is_array($line->addons) ? $line->addons : [])
                    ->map(fn ($a) => (string) ($a['name'] ?? ''))->filter()->values()->all(),
            ],
            'added_by' => [
                'id' => (int) $line->added_by_user_id,
                'name' => (string) ($line->addedBy->name ?? ''),
            ],
            'qty' => (int) $line->qty,
            'price' => (float) $line->price,
            'total_price' => (float) $line->total_price,
        ])->values();

        // Per-participant bill: their items + their share of the service fee +
        // tax, on their own order only (cash on arrival). Whether the fee/tax are
        // added on top or already included in the price is the owner's setting.
        $businessId = (int) $order->business_id;
        $feeRow = $this->billing->feeRowForBusiness($businessId);
        [$incService, $incTax] = $this->billing->inclusiveFlagsForBusiness($businessId);
        $taxRate = $this->billing->taxRatePercentForBusiness($businessId);
        $byUser = $order->items->groupBy('added_by_user_id');

        $breakdown = $order->participants->map(function ($p) use ($byUser, $feeRow, $incService, $incTax, $taxRate) {
            $lines = $byUser->get($p->user_id) ?? collect();
            $bill = $this->billing->bill(
                (float) $lines->sum('total_price'),
                $feeRow,
                $incService,
                $incTax,
                $taxRate,
                $this->billing->clientConsents((int) $p->user_id),
            );

            return [
                'user_id' => (int) $p->user_id,
                'name' => (string) ($p->user->name ?? ''),
                'role' => (string) $p->role,
                // Distinct product lines, not summed quantities — see
                // CartController::presentCart's same fix for why.
                'items_count' => $lines->count(),
                'items_subtotal' => $bill['items_subtotal'],
                'service_fee' => $bill['service_fee'],
                'service_included' => $bill['service_included'],
                'tax' => $bill['tax'],
                'tax_included' => $bill['tax_included'],
                'total' => $bill['total'],
            ];
        })->values();

        // Who is looking: lets a client show host-only actions (checkout/cancel)
        // vs member actions (leave) and know which lines it may edit.
        $viewer = null;
        if ($viewerId !== null) {
            $mine = $order->participants->firstWhere('user_id', $viewerId);
            $viewer = [
                'user_id' => $viewerId,
                'role' => $mine ? (string) $mine->role : null,
                'is_host' => $mine ? $mine->role === 'host' : false,
            ];
        }

        return [
            'id' => (int) $order->id,
            'status' => (string) $order->status,
            'is_shared' => (bool) $order->is_shared,
            'share_token' => $order->share_token,
            'payment_method' => 'cash',
            'viewer' => $viewer,
            'business' => $order->business ? [
                'id' => (int) $order->business->id,
                'name' => (string) $order->business->name,
                'logo' => $order->business->logo,
            ] : null,
            'fulfillment_type' => (string) $order->fulfillment_type,
            'address' => $order->address !== '' ? (string) $order->address : null,
            'delivery_address_id' => $order->delivery_address_id !== null ? (int) $order->delivery_address_id : null,
            'delivery_lat' => $order->delivery_lat !== null ? (float) $order->delivery_lat : null,
            'delivery_lng' => $order->delivery_lng !== null ? (float) $order->delivery_lng : null,
            'participants' => $breakdown,
            'items' => $items,
            'totals' => [
                // Distinct product lines, not summed quantities — see
                // CartController::presentCart's same fix for why.
                'items' => $items->count(),
                'items_subtotal' => round((float) $breakdown->sum('items_subtotal'), 2),
                'service_fee' => round((float) $breakdown->sum('service_fee'), 2),
                'tax' => round((float) $breakdown->sum('tax'), 2),
                'grand_total' => round((float) $breakdown->sum('total'), 2),
            ],
        ];
    }

    /** [offering_type => [offering_id => name]] for the order's lines. */
    private function displayNames(Order $order): array
    {
        $menuIds = $order->items->where('offering_type', MenuItem::class)->pluck('offering_id')->filter()->unique();
        $listingIds = $order->items->where('offering_type', BusinessCatalogListing::class)->pluck('offering_id')->filter()->unique();
        $bundleIds = $order->items->where('offering_type', MenuBundle::class)->pluck('offering_id')->filter()->unique();

        $names = [MenuItem::class => [], BusinessCatalogListing::class => [], MenuBundle::class => []];

        if ($menuIds->isNotEmpty()) {
            $names[MenuItem::class] = MenuItem::query()->whereIn('id', $menuIds)
                ->pluck('name_ar', 'id')->map(fn ($n) => (string) $n)->all();
        }

        if ($bundleIds->isNotEmpty()) {
            $names[MenuBundle::class] = MenuBundle::query()->whereIn('id', $bundleIds)
                ->pluck('name_ar', 'id')->map(fn ($n) => (string) $n)->all();
        }

        if ($listingIds->isNotEmpty()) {
            $names[BusinessCatalogListing::class] = DB::table('business_catalog_listings as l')
                ->join('catalog_products as p', 'p.id', '=', 'l.catalog_product_id')
                ->whereIn('l.id', $listingIds)
                ->pluck('p.name_ar', 'l.id')->map(fn ($n) => (string) $n)->all();
        }

        return $names;
    }

    /** [size_id => variant name] for menu lines carrying a size. */
    private function sizeNames(Order $order): array
    {
        $sizeIds = $order->items->pluck('size_id')->filter()->unique();

        if ($sizeIds->isEmpty()) {
            return [];
        }

        return DB::table('menu_item_variants')->whereIn('id', $sizeIds)
            ->pluck('name_ar', 'id')->map(fn ($n) => (string) $n)->all();
    }
}
