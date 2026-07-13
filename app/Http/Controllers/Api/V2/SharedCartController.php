<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\BusinessCatalogListing;
use App\Models\MenuItem;
use App\Models\Order;
use App\Services\CustomerCartService;
use App\Services\MenuBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    /** Join a shared cart by token. */
    public function join(Request $request, string $token)
    {
        $order = $this->cart->join((int) $request->user()->id, $token);

        return response()->json(['success' => true, 'data' => ['cart' => $this->present($order)]], 201);
    }

    /** View a shared cart (participants + attributed items + per-person breakdown). */
    public function show(Request $request, int $order)
    {
        return response()->json(['success' => true, 'data' => ['cart' => $this->present(
            $this->cart->sharedCartFor((int) $request->user()->id, $order)
        )]]);
    }

    /** Add an offering to the shared cart, attributed to the caller. */
    public function addItem(Request $request, int $order)
    {
        $data = $request->validate([
            'kind' => ['required', 'in:retail,menu'],
            'offering_id' => ['required', 'integer', 'min:1'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:999'],
            'size_id' => ['nullable', 'integer', 'min:1'],
            'extras' => ['nullable', 'array'],
            'extras.*' => ['integer', 'min:1'],
        ], [], ['kind' => 'نوع العرض', 'offering_id' => 'العرض', 'qty' => 'الكمية']);

        $cart = $this->cart->addToShared(
            (int) $request->user()->id,
            $order,
            (string) $data['kind'],
            (int) $data['offering_id'],
            (int) ($data['qty'] ?? 1),
            ['size_id' => $data['size_id'] ?? null, 'extras' => $data['extras'] ?? []]
        );

        return response()->json(['success' => true, 'data' => ['cart' => $this->present($cart)]], 201);
    }

    /** Change a shared-cart line's quantity (adder or host). 0 removes. */
    public function updateItem(Request $request, int $order, int $item)
    {
        $data = $request->validate(['qty' => ['required', 'integer', 'min:0', 'max:999']], [], ['qty' => 'الكمية']);

        $cart = $this->cart->updateSharedLine((int) $request->user()->id, $order, $item, (int) $data['qty']);

        return response()->json(['success' => true, 'data' => ['cart' => $this->present($cart)]]);
    }

    /** Remove a shared-cart line (adder or host). */
    public function removeItem(Request $request, int $order, int $item)
    {
        $cart = $this->cart->removeSharedLine((int) $request->user()->id, $order, $item);

        return response()->json(['success' => true, 'data' => ['cart' => $this->present($cart)]]);
    }

    /** Place the shared cart as a pending order (host only). */
    public function checkout(Request $request, int $order)
    {
        $data = $request->validate([
            'fulfillment_type' => ['nullable', 'in:delivery,pickup,dine_in'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Shared carts are cash-on-arrival; each participant pays their own share.
        $data['payment_method'] = 'cash';

        $placed = $this->cart->checkoutShared((int) $request->user()->id, $order, $data);

        return response()->json([
            'success' => true,
            'data' => ['order' => $this->present($placed->load([
                'items.addedBy:id,name', 'participants.user:id,name', 'business:id,name,logo',
            ]))],
        ], 201);
    }

    /** Leave a shared cart (member only; removes the caller's lines). */
    public function leave(Request $request, int $order)
    {
        $this->cart->leaveShared((int) $request->user()->id, $order);

        return response()->json(['success' => true]);
    }

    /** Serialize a shared cart with attribution + per-participant breakdown. */
    private function present(Order $order): array
    {
        $order->loadMissing('items.addedBy:id,name', 'participants.user:id,name', 'business:id,name,logo');
        $names = $this->displayNames($order);
        $sizeNames = $this->sizeNames($order);

        $items = $order->items->map(fn ($line) => [
            'id' => (int) $line->id,
            'kind' => $line->offering_type === BusinessCatalogListing::class ? 'retail' : 'menu',
            'offering_id' => (int) $line->offering_id,
            'name' => $names[(string) $line->offering_type][(int) $line->offering_id]
                ?? ('#' . ($line->offering_id ?: $line->menu_id)),
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
        $byUser = $order->items->groupBy('added_by_user_id');

        $breakdown = $order->participants->map(function ($p) use ($byUser, $feeRow, $incService, $incTax) {
            $lines = $byUser->get($p->user_id) ?? collect();
            $bill = $this->billing->bill((float) $lines->sum('total_price'), $feeRow, $incService, $incTax);

            return [
                'user_id' => (int) $p->user_id,
                'name' => (string) ($p->user->name ?? ''),
                'role' => (string) $p->role,
                'items_count' => (int) $lines->sum('qty'),
                'items_subtotal' => $bill['items_subtotal'],
                'service_fee' => $bill['service_fee'],
                'service_included' => $bill['service_included'],
                'tax' => $bill['tax'],
                'tax_included' => $bill['tax_included'],
                'total' => $bill['total'],
            ];
        })->values();

        return [
            'id' => (int) $order->id,
            'status' => (string) $order->status,
            'is_shared' => (bool) $order->is_shared,
            'share_token' => $order->share_token,
            'payment_method' => 'cash',
            'business' => $order->business ? [
                'id' => (int) $order->business->id,
                'name' => (string) $order->business->name,
                'logo' => $order->business->logo,
            ] : null,
            'fulfillment_type' => (string) $order->fulfillment_type,
            'participants' => $breakdown,
            'items' => $items,
            'totals' => [
                'items' => (int) $items->sum('qty'),
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

        $names = [MenuItem::class => [], BusinessCatalogListing::class => []];

        if ($menuIds->isNotEmpty()) {
            $names[MenuItem::class] = MenuItem::query()->whereIn('id', $menuIds)
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
