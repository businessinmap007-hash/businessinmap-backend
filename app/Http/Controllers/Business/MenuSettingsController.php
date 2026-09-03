<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\BusinessMenuSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * "Menu settings" for the business owner — whether menu prices already include
 * the service fee / tax (so they are not added on top). Scoped to the owner.
 */
class MenuSettingsController extends Controller
{
    private function businessId(): int
    {
        return (int) Auth::id();
    }

    public function edit(): View
    {
        $row = BusinessMenuSetting::query()->firstOrNew(['business_id' => $this->businessId()]);

        return view('business.menu-settings.edit', ['row' => $row]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // Empty → NULL → no tax at all. There is no platform-wide default
            // a business is silently opted into (owner rule, 2026-09-03) —
            // tax only applies once a business explicitly sets its own rate.
            'tax_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            // Empty → NULL → no minimum enforced (unchanged behaviour).
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            // Empty → NULL → no default; the shelf-fill screen still requires
            // a manual price when neither is set.
            'default_margin_percent' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            // Empty → NULL → never require one. Checked at checkout against
            // the customer's own guarantee/wallet cover (CustomerCartService::assessDeposit).
            'deposit_required_above' => ['nullable', 'numeric', 'min:0'],
        ], [], [
            'tax_rate_percent' => 'نسبة الضريبة',
            'min_order_amount' => 'حد أدنى للطلب',
            'default_margin_percent' => 'هامش الربح الافتراضي',
            'deposit_required_above' => 'حد يستوجب ضمانًا',
        ]);

        $rate = $request->filled('tax_rate_percent') ? round((float) $data['tax_rate_percent'], 2) : null;
        $minOrder = $request->filled('min_order_amount') ? round((float) $data['min_order_amount'], 2) : null;
        $margin = $request->filled('default_margin_percent') ? round((float) $data['default_margin_percent'], 2) : null;
        $depositAbove = $request->filled('deposit_required_above') ? round((float) $data['deposit_required_above'], 2) : null;

        BusinessMenuSetting::updateOrCreate(
            ['business_id' => $this->businessId()],
            [
                'prices_include_service' => (int) $request->boolean('prices_include_service'),
                'prices_include_tax' => (int) $request->boolean('prices_include_tax'),
                'tax_rate_percent' => $rate,
                'min_order_amount' => $minOrder,
                'default_margin_percent' => $margin,
                'deposit_required_above' => $depositAbove,
            ]
        );

        return back()->with('success', 'تم حفظ إعدادات المنيو بنجاح.');
    }
}
