<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\MenuItem;
use App\Services\BookingFoodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * "My bookings" for the business owner + the dine-in food flow. Every query is
 * scoped to business_id = auth id; food lines can only reference the owner's
 * own menu items, and prices are always taken from the menu (never posted).
 */
class BookingController extends Controller
{
    public function __construct(protected BookingFoodService $food)
    {
    }

    private function businessId(): int
    {
        return (int) Auth::id();
    }

    private function scopedBooking(int $id): Booking
    {
        return Booking::query()
            ->where('business_id', $this->businessId())
            ->findOrFail($id);
    }

    public function index(Request $request): View
    {
        $status = trim((string) $request->get('status', ''));

        $rows = Booking::query()
            ->with(['service:id,key,name_ar,name_en', 'bookable'])
            ->where('business_id', $this->businessId())
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('business.bookings.index', [
            'rows' => $rows,
            'status' => $status,
        ]);
    }

    public function show(int $id): View
    {
        $booking = $this->scopedBooking($id);
        $booking->load(['service:id,key,name_ar,name_en', 'bookable', 'orders.items']);

        $menuItems = MenuItem::query()
            ->where('business_id', $this->businessId())
            ->where('is_active', 1)
            ->orderByRaw('COALESCE(sort_order, 999999) ASC')
            ->orderBy('id')
            ->get(['id', 'name_ar', 'name_en', 'base_price']);

        $order = $booking->orders->first();

        return view('business.bookings.show', [
            'booking' => $booking,
            'order' => $order,
            'lines' => $order ? $order->items : collect(),
            'menuItems' => $menuItems,
            'invoice' => $this->food->unifiedInvoice($booking),
        ]);
    }

    public function addFood(Request $request, int $id): RedirectResponse
    {
        $booking = $this->scopedBooking($id);

        $data = $request->validate([
            'menu_id' => ['required', 'integer'],
            'qty' => ['required', 'integer', 'min:1', 'max:999'],
        ], [], ['menu_id' => 'الصنف', 'qty' => 'الكمية']);

        // The item must belong to this owner; its price is authoritative.
        $menu = MenuItem::query()
            ->where('business_id', $this->businessId())
            ->where('id', (int) $data['menu_id'])
            ->first();

        if (! $menu) {
            return back()->withErrors(['menu_id' => 'هذا الصنف غير متاح في منيوك.']);
        }

        $this->food->addLine($booking, (int) $menu->id, (int) $data['qty'], (float) $menu->base_price);

        return back()->with('success', 'تمت إضافة الصنف إلى الحجز.');
    }

    public function removeFood(Request $request, int $id): RedirectResponse
    {
        $booking = $this->scopedBooking($id);

        $data = $request->validate([
            'item_id' => ['required', 'integer'],
        ]);

        $this->food->removeLine($booking, (int) $data['item_id']);

        return back()->with('success', 'تم حذف الصنف من الحجز.');
    }
}
