<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * AdminV2 screen for pasting live payment-gateway credentials (Fawry today).
 * Lets an admin go live after signing with the PSP without touching code or the
 * .env file — values are persisted (secrets encrypted) via PaymentSettingsService
 * and picked up by the gateway factory on the next charge.
 */
class PaymentSettingsController extends Controller
{
    public function __construct(private readonly PaymentSettingsService $settings)
    {
    }

    /** GET admin/payment-settings */
    public function edit(): View
    {
        return view('admin-v2.payment-settings.edit', [
            'fawry' => $this->settings->fawryFormState(),
        ]);
    }

    /** PUT admin/payment-settings */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'base_url' => ['nullable', 'string', 'max:255'],
            'merchant_code' => ['nullable', 'string', 'max:120'],
            'security_key' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'max:8'],
            'return_url' => ['nullable', 'string', 'max:255'],
        ]);

        $this->settings->saveFawry($data);

        return redirect()
            ->route('admin.payment-settings.edit')
            ->with('success', 'تم حفظ إعدادات Fawry بنجاح.');
    }
}
