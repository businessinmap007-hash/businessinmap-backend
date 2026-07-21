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
 * The customer's cart (Phase 3d). A cart is a draft Order per business; lines
 * are polymorphic order_items over the offering layer. Goods offerings only
 * (retail catalog listings + menu items). All endpoints are scoped to the
 * authenticated customer.
 *
 * Menu (food) lines carry a service fee + tax (via MenuBillingService, honouring
 * the owner's inclusive-price settings); retail lines are billed at their plain
 * price. The billing is computed at presentation time.
 */
final class CartController extends Controller
{
    public function __construct(
        private readonly CustomerCartService $cart,
        private readonly MenuBillingService $billing,
    ) {
    }

    /** All the customer's carts, one block per business. */
    public function index(Request $request)
    {
        $carts = $this->cart->carts((int) $request->user()->id)
            ->map(fn (Order $order) => $this->presentCart($order))
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'carts' => $carts,
                'totals' => [
                    'businesses' => $carts->count(),
                    'items' => $carts->sum(fn ($c) => $c['items_count']),
                    // Payable across all carts, incl. menu service fee + tax.
                    'grand_total' => round($carts->sum(fn ($c) => $c['final_total']), 2),
                ],
            ],
        ]);
    }

    /** Add an offering (retail listing or menu item) to the cart. */
    public function addItem(Request $request)
    {
        $data = $request->validate([
            'kind' => ['required', 'in:retail,menu'],
            'offering_id' => ['required', 'integer', 'min:1'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:999'],
            'size_id' => ['nullable', 'integer', 'min:1'],
            'extras' => ['nullable', 'array'],
            'extras.*' => ['integer', 'min:1'],
        ], [], ['kind' => __('نوع العرض'), 'offering_id' => __('العرض'), 'qty' => __('الكمية')]);

        $order = $this->cart->addItem(
            (int) $request->user()->id,
            (string) $data['kind'],
            (int) $data['offering_id'],
            (int) ($data['qty'] ?? 1),
            [
                'size_id' => $data['size_id'] ?? null,
                'extras' => $data['extras'] ?? [],
            ]
        );

        return response()->json(['success' => true, 'data' => ['cart' => $this->presentCart($order)]], 201);
    }

    /** Change a line's quantity (0 removes it). */
    public function updateItem(Request $request, int $item)
    {
        $data = $request->validate([
            'qty' => ['required', 'integer', 'min:0', 'max:999'],
        ], [], ['qty' => __('الكمية')]);

        $order = $this->cart->updateItemQty((int) $request->user()->id, $item, (int) $data['qty']);

        return response()->json(['success' => true, 'data' => ['cart' => $this->presentCart($order)]]);
    }

    /** Remove a line from the cart. */
    public function removeItem(Request $request, int $item)
    {
        $order = $this->cart->removeItem((int) $request->user()->id, $item);

        return response()->json(['success' => true, 'data' => ['cart' => $this->presentCart($order)]]);
    }

    /** Place a business's cart as a pending order. */
    public function checkout(Request $request, int $business)
    {
        $data = $request->validate([
            'fulfillment_type' => ['nullable', 'in:delivery,pickup,dine_in'],
            'address_id' => ['nullable', 'integer'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['nullable', 'string', 'max:50'],
        ]);

        $order = $this->cart->checkout((int) $request->user()->id, $business, $data);

        return response()->json([
            'success' => true,
            'data' => ['order' => $this->presentCart($order->loadMissing('business:id,name,logo'))],
        ], 201);
    }

    /** Serialize a cart/order with resolved line names and totals. */
    private function presentCart(Order $order): array
    {
        $order->loadMissing('items', 'business:id,name,logo');
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
            'qty' => (int) $line->qty,
            'price' => (float) $line->price,
            'total_price' => (float) $line->total_price,
        ])->values();

        // Menu (food) lines get the service fee + tax; retail lines are plain.
        // orderBill groups by biller so this matches the value persisted at
        // checkout (see CustomerCartService::placeOrder).
        $bill = $this->billing->orderBill($order);
        $deliveryFee = round((float) $order->delivery_fee, 2);
        $discount = round((float) $order->discount, 2);
        $finalTotal = round($bill['menu_payable'] + $bill['retail_subtotal'] + $deliveryFee - $discount, 2);

        return [
            'id' => (int) $order->id,
            'status' => (string) $order->status,
            'business' => $order->business ? [
                'id' => (int) $order->business->id,
                'name' => (string) $order->business->name,
                'logo' => $order->business->logo,
            ] : null,
            'fulfillment_type' => (string) $order->fulfillment_type,
            'address' => $order->address !== '' ? (string) $order->address : null,
            'delivery_address_id' => $order->delivery_address_id !== null ? (int) $order->delivery_address_id : null,
            'items' => $items,
            'items_count' => $items->sum('qty'),
            'bill' => [
                'menu_subtotal' => $bill['menu_subtotal'],
                'retail_subtotal' => $bill['retail_subtotal'],
                'service_fee' => $bill['service_fee'],
                'service_included' => $bill['service_included'],
                'tax' => $bill['tax'],
                'tax_included' => $bill['tax_included'],
                'delivery_fee' => $deliveryFee,
                'discount' => $discount,
            ],
            'total' => round($bill['menu_subtotal'] + $bill['retail_subtotal'], 2),
            'delivery_fee' => $deliveryFee,
            'discount' => $discount,
            'final_total' => $finalTotal,
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

    /** [size_id => variant name] for the order's menu lines that carry a size. */
    private function sizeNames(Order $order): array
    {
        $sizeIds = $order->items->pluck('size_id')->filter()->unique();

        if ($sizeIds->isEmpty()) {
            return [];
        }

        return DB::table('menu_item_variants')
            ->whereIn('id', $sizeIds)
            ->pluck('name_ar', 'id')
            ->map(fn ($n) => (string) $n)
            ->all();
    }
}
