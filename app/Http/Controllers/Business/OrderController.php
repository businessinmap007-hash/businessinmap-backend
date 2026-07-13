<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\BusinessCatalogListing;
use App\Models\MenuItem;
use App\Models\Order;
use App\Services\MenuOrderService;
use App\Services\OrderHandoverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Standalone menu orders (delivery / pickup) for the business owner — orders
 * with no booking. Scoped to business_id = auth id; food prices always come
 * from the owner's own menu items.
 */
class OrderController extends Controller
{
    public function __construct(
        protected MenuOrderService $orders,
        protected OrderHandoverService $handover,
    ) {
    }

    private function businessId(): int
    {
        return (int) Auth::id();
    }

    private function scopedOrder(int $id): Order
    {
        return Order::query()
            ->where('business_id', $this->businessId())
            ->whereNull('booking_id')
            ->findOrFail($id);
    }

    public function index(Request $request): View
    {
        $type = trim((string) $request->get('fulfillment_type', ''));

        $rows = Order::query()
            ->where('business_id', $this->businessId())
            ->whereNull('booking_id')
            ->when($type !== '', fn ($query) => $query->where('fulfillment_type', $type))
            ->withCount('items')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('business.orders.index', [
            'rows' => $rows,
            'type' => $type,
        ]);
    }

    public function create(): View
    {
        return view('business.orders.create', [
            'row' => new Order([
                'fulfillment_type' => Order::FULFILLMENT_DELIVERY,
                'status' => 'pending',
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        $order = Order::create($data + [
            'business_id' => $this->businessId(),
            'user_id' => $this->businessId(),
            'booking_id' => null,
            'total' => 0,
            'discount' => 0,
            'final_total' => 0,
            'status' => 'pending',
        ]);

        return redirect()
            ->route('business.orders.show', $order->id)
            ->with('success', 'تم إنشاء الطلب. أضف الأصناف الآن.');
    }

    public function show(int $id): View
    {
        $order = $this->scopedOrder($id);
        $order->load('items');

        $menuItems = MenuItem::query()
            ->where('business_id', $this->businessId())
            ->where('is_active', 1)
            ->orderByRaw('COALESCE(sort_order, 999999) ASC')
            ->orderBy('id')
            ->get(['id', 'name_ar', 'name_en', 'base_price']);

        $listings = DB::table('business_catalog_listings as l')
            ->join('catalog_products as p', 'p.id', '=', 'l.catalog_product_id')
            ->where('l.business_id', $this->businessId())
            ->where('l.is_active', 1)
            ->orderByDesc('l.id')
            ->get(['l.id', 'l.price', 'p.name_ar as product_name']);

        // Resolve a display name per line from its offering (menu item or listing).
        $menuNames = $menuItems->pluck('name_ar', 'id');
        $listingNames = $listings->pluck('product_name', 'id');

        foreach ($order->items as $line) {
            $line->display_name = match ((string) $line->offering_type) {
                MenuItem::class => $menuNames[$line->offering_id] ?? ($line->menuItem?->name_ar ?: '#' . $line->menu_id),
                BusinessCatalogListing::class => $listingNames[$line->offering_id] ?? ('منتج #' . $line->offering_id),
                default => $line->menuItem?->name_ar ?: ('#' . $line->menu_id),
            };
        }

        // A ready (pending) order shows a one-time handover QR the staff presents
        // to the customer, who scans it to confirm receipt (BIM-13.5).
        $handoverToken = (string) $order->status === OrderHandoverService::STATUS_PENDING
            ? $this->handover->issueFor($order, $this->businessId())
            : null;

        return view('business.orders.show', [
            'order' => $order,
            'lines' => $order->items,
            'menuItems' => $menuItems,
            'listings' => $listings,
            'handoverToken' => $handoverToken,
        ]);
    }

    public function addProduct(Request $request, int $id): RedirectResponse
    {
        $order = $this->scopedOrder($id);

        $data = $request->validate([
            'listing_id' => ['required', 'integer'],
            'qty' => ['required', 'integer', 'min:1', 'max:999'],
        ], [], ['listing_id' => 'المنتج', 'qty' => 'الكمية']);

        $listing = BusinessCatalogListing::query()
            ->where('business_id', $this->businessId())
            ->where('is_active', 1)
            ->find((int) $data['listing_id']);

        if (! $listing) {
            return back()->withErrors(['listing_id' => 'هذا المنتج غير متاح في منتجاتك.']);
        }

        $this->orders->addOffering(
            $order,
            BusinessCatalogListing::class,
            (int) $listing->id,
            (int) $data['qty'],
            (float) $listing->price
        );

        return back()->with('success', 'تمت إضافة المنتج للطلب.');
    }

    public function addFood(Request $request, int $id): RedirectResponse
    {
        $order = $this->scopedOrder($id);

        $data = $request->validate([
            'menu_id' => ['required', 'integer'],
            'qty' => ['required', 'integer', 'min:1', 'max:999'],
        ], [], ['menu_id' => 'الصنف', 'qty' => 'الكمية']);

        $menu = MenuItem::query()
            ->where('business_id', $this->businessId())
            ->where('id', (int) $data['menu_id'])
            ->first();

        if (! $menu) {
            return back()->withErrors(['menu_id' => 'هذا الصنف غير متاح في منيوك.']);
        }

        $this->orders->addLine($order, (int) $menu->id, (int) $data['qty'], (float) $menu->base_price);

        return back()->with('success', 'تمت إضافة الصنف.');
    }

    public function removeFood(Request $request, int $id): RedirectResponse
    {
        $order = $this->scopedOrder($id);

        $data = $request->validate(['item_id' => ['required', 'integer']]);

        $this->orders->removeLine($order, (int) $data['item_id']);

        return back()->with('success', 'تم حذف الصنف.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->scopedOrder($id)->delete();

        return redirect()
            ->route('business.orders.index')
            ->with('success', 'تم حذف الطلب.');
    }

    protected function validateData(Request $request): array
    {
        $data = $request->validate([
            'fulfillment_type' => ['required', 'in:delivery,pickup'],
            'address' => ['nullable', 'string', 'max:500'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'fulfillment_type' => 'نوع التنفيذ',
            'address' => 'العنوان',
            'delivery_fee' => 'رسوم التوصيل',
        ]);

        $type = (string) $data['fulfillment_type'];
        $isDelivery = $type === Order::FULFILLMENT_DELIVERY;

        return [
            'fulfillment_type' => $type,
            'address' => $isDelivery ? trim((string) ($data['address'] ?? '')) : '',
            'delivery_fee' => $isDelivery ? round((float) ($data['delivery_fee'] ?? 0), 2) : 0,
            'payment_method' => '',
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
        ];
    }
}
