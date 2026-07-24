<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\MerchantPaymentAccount;
use App\Models\User;
use App\Services\Payments\MerchantPaymentAccountService;
use App\Services\Payments\PaymentSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * AdminV2 screen for Fawry sub-account routing: a global on/off toggle, plus the
 * per-merchant credentials (the admin writes each merchant's Fawry sub-account
 * code + security key). When on, a customer's payment for a configured merchant
 * is billed to that merchant's Fawry account instead of the platform's.
 *
 * MONEY-gated (route middleware) — like the platform payment-settings screen,
 * these values redirect real money.
 */
class MerchantPaymentAccountController extends Controller
{
    public function __construct(
        private readonly MerchantPaymentAccountService $accounts,
        private readonly PaymentSettingsService $settings,
    ) {
    }

    /** GET admin/merchant-payment-accounts */
    public function index(): View
    {
        $rows = MerchantPaymentAccount::query()
            ->with('business:id,name,phone')
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get();

        return view('admin-v2.merchant-payment-accounts.index', [
            'enabled' => $this->settings->subMerchantEnabled(),
            'accounts' => $rows,
        ]);
    }

    /** PUT admin/merchant-payment-accounts/toggle — flip the global feature. */
    public function toggle(Request $request): RedirectResponse
    {
        $this->settings->setSubMerchantEnabled($request->boolean('enabled'));

        return redirect()
            ->route('admin.merchant-payment-accounts.index')
            ->with('success', __('تم تحديث حالة خدمة الحسابات الفرعية.'));
    }

    /** POST admin/merchant-payment-accounts — save one merchant's sub-account. */
    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'business_id' => ['required', 'integer', 'exists:users,id'],
            'merchant_code' => ['required', 'string', 'max:120'],
            'security_key' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $business = User::query()->findOrFail((int) $data['business_id']);
        if (! $business->isBusiness()) {
            return redirect()
                ->route('admin.merchant-payment-accounts.index')
                ->withErrors(['business_id' => __('الحساب المحدّد ليس حساب تاجر.')]);
        }

        $this->accounts->save(
            (int) $data['business_id'],
            $data['merchant_code'],
            $data['security_key'] ?? null,
            $request->boolean('is_active'),
        );

        return redirect()
            ->route('admin.merchant-payment-accounts.index')
            ->with('success', __('تم حفظ حساب التاجر الفرعي.'));
    }
}
