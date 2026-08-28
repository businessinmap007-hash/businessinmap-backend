<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Services\Guarantees\TrustedPartnerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "شركاء موثوقون" — the business's own standing vouch list (2026-08-28,
 * Phase 2 of the order-deposit feature). Vouching for a repeat customer
 * (by phone — an existing account, never a new signup) waives THIS
 * business's own deposit_required_above check for that customer's future
 * orders, as long as they still hold an active guarantee of their own.
 * See TrustedPartnerService for the full rule.
 */
class TrustedPartnerController extends Controller
{
    use ResolvesOwnerCatalog;

    public function __construct(private readonly TrustedPartnerService $trustedPartners)
    {
    }

    public function index(): View
    {
        return view('business.trusted-partners.index', [
            'partners' => $this->trustedPartners->roster($this->businessId()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
        ], [], ['phone' => 'رقم الهاتف']);

        $this->trustedPartners->vouch($this->businessId(), $data['phone']);

        return back()->with('success', 'تم توثيق الشريك بنجاح.');
    }

    public function update(Request $request, int $partner): RedirectResponse
    {
        $request->validate(['is_active' => ['required']]);

        $this->trustedPartners->setActive($this->businessId(), $partner, $request->boolean('is_active'));

        return back()->with('success', 'تم تحديث حالة الشريك.');
    }
}
