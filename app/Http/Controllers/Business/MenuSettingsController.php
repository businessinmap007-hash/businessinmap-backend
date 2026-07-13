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

        return view('business.menu-settings.edit', [
            'row' => $row,
            'defaultTaxRate' => (float) config('bim.menu_tax_rate_percent', 14),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // Empty → NULL → fall back to the global tax rate.
            'tax_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], [], ['tax_rate_percent' => 'نسبة الضريبة']);

        $rate = $request->filled('tax_rate_percent') ? round((float) $data['tax_rate_percent'], 2) : null;

        BusinessMenuSetting::updateOrCreate(
            ['business_id' => $this->businessId()],
            [
                'prices_include_service' => (int) $request->boolean('prices_include_service'),
                'prices_include_tax' => (int) $request->boolean('prices_include_tax'),
                'tax_rate_percent' => $rate,
            ]
        );

        return back()->with('success', 'تم حفظ إعدادات المنيو بنجاح.');
    }
}
