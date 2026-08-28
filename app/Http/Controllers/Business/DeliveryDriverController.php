<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Services\DeliveryDispatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "موصّليّ" — a business's own private delivery-driver roster (restaurant,
 * supermarket, pharmacy, or any other business with the `delivery` service
 * active). A driver is linked by phone — same find-never-mint pattern as
 * business_staff, never a new signup flow — and then uses the exact same
 * self-service accept/pickup/deliver loop everyone else does
 * (Api\V2\DeliveryController), just scoped to this business's own orders.
 *
 * Never hard-deletes a driver row: assigned/picked_up/delivered counters and
 * the delivery_completions ledger stay attributable, so "remove" is really
 * "off duty" (is_active=false) — reversible, like every other roster in
 * this app that carries real history.
 */
class DeliveryDriverController extends Controller
{
    use ResolvesOwnerCatalog;

    public function __construct(private readonly DeliveryDispatchService $delivery)
    {
    }

    public function index(): View
    {
        return view('business.delivery-drivers.index', [
            'drivers' => $this->delivery->businessRoster($this->businessId()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
            'vehicle_label' => ['nullable', 'string', 'max:120'],
        ], [], ['phone' => 'رقم هاتف الموصّل']);

        $this->delivery->linkBusinessDriver($this->businessId(), $data['phone'], [
            'vehicle_label' => $data['vehicle_label'] ?? null,
        ]);

        return back()->with('success', 'تمت إضافة الموصّل إلى فريقك.');
    }

    public function update(Request $request, int $driver): RedirectResponse
    {
        $request->validate(['is_active' => ['required']]);

        $this->delivery->setBusinessDriverActive(
            $this->businessId(),
            $driver,
            $request->boolean('is_active')
        );

        return back()->with('success', 'تم تحديث حالة الموصّل.');
    }
}
