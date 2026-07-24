<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\MerchantAccountRequest;
use App\Services\Payments\MerchantAccountRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * AdminV2 review queue for businesses applying for a Fawry merchant sub-account.
 * Approving provisions the merchant's credentials (code + key) and marks the
 * request approved in one step; rejecting records a reason. MONEY-gated.
 */
class MerchantAccountRequestController extends Controller
{
    public function __construct(private readonly MerchantAccountRequestService $requests)
    {
    }

    /** GET admin/merchant-account-requests */
    public function index(Request $request): View
    {
        $status = trim((string) $request->get('status', MerchantAccountRequest::STATUS_PENDING));

        $rows = MerchantAccountRequest::query()
            ->with('business:id,name,phone')
            ->when(in_array($status, [
                MerchantAccountRequest::STATUS_PENDING,
                MerchantAccountRequest::STATUS_APPROVED,
                MerchantAccountRequest::STATUS_REJECTED,
            ], true), fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin-v2.merchant-account-requests.index', [
            'rows' => $rows,
            'status' => $status,
        ]);
    }

    /** POST admin/merchant-account-requests/{request}/approve — provision + approve. */
    public function approve(Request $request, MerchantAccountRequest $merchantAccountRequest): RedirectResponse
    {
        $data = $request->validate([
            'merchant_code' => ['required', 'string', 'max:120'],
            'security_key' => ['required', 'string', 'max:255'],
            'decision_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->requests->approve(
            $merchantAccountRequest,
            $data['merchant_code'],
            $data['security_key'],
            (int) $request->user()->id,
            $data['decision_note'] ?? null,
        );

        return redirect()
            ->route('admin.merchant-account-requests.index')
            ->with('success', __('تم اعتماد الطلب وتفعيل حساب التاجر.'));
    }

    /** POST admin/merchant-account-requests/{request}/reject */
    public function reject(Request $request, MerchantAccountRequest $merchantAccountRequest): RedirectResponse
    {
        $data = $request->validate([
            'decision_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->requests->reject($merchantAccountRequest, $data['decision_note'] ?? null, (int) $request->user()->id);

        return redirect()
            ->route('admin.merchant-account-requests.index')
            ->with('success', __('تم رفض الطلب.'));
    }
}
