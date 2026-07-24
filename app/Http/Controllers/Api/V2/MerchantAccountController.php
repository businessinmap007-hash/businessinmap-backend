<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\Payments\MerchantAccountRequestService;
use Illuminate\Http\Request;

/**
 * The business owner's side of the Fawry merchant sub-account: see whether they
 * have one (and any pending application), and apply for one. Provisioning is done
 * by an admin (AdminV2\MerchantAccountRequestController).
 */
final class MerchantAccountController extends Controller
{
    public function __construct(private readonly MerchantAccountRequestService $requests)
    {
    }

    /** GET /api/v2/merchant-account — the caller's merchant-account status. */
    public function status(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->requests->statusFor($request->user()),
        ]);
    }

    /** POST /api/v2/merchant-account/request — apply for a merchant sub-account. */
    public function apply(Request $request)
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $req = $this->requests->submit($request->user(), $data['note'] ?? null);

        return response()->json([
            'success' => true,
            'message' => __('تم إرسال طلب الحصول على حساب merchant. سيراجعه الفريق.'),
            'data' => ['request_id' => (int) $req->id, 'status' => $req->status],
        ], 201);
    }
}
