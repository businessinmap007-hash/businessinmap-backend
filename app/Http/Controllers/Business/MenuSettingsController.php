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
        BusinessMenuSetting::updateOrCreate(
            ['business_id' => $this->businessId()],
            [
                'prices_include_service' => (int) $request->boolean('prices_include_service'),
                'prices_include_tax' => (int) $request->boolean('prices_include_tax'),
            ]
        );

        return back()->with('success', 'تم حفظ إعدادات المنيو بنجاح.');
    }
}
