<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\BusinessTable;
use App\Services\CustomerCartService;
use Illuminate\Http\Request;

/**
 * Restaurant-table QR (BIM-13.3). Scanning a table's permanent token joins the
 * table's open shared cart, or opens a new one (first scanner = host). Returns
 * the order id + share token so the client can render/manage via the existing
 * shared-cart surface (GET cart/shared/{order} or the /cart/join/{token} page).
 */
final class TableController extends Controller
{
    public function __construct(private readonly CustomerCartService $cart)
    {
    }

    /** POST /api/v2/table/{token}/scan */
    public function scan(Request $request, string $token)
    {
        $table = BusinessTable::query()
            ->where('token', $token)
            ->where('is_active', 1)
            ->first();

        if (! $table) {
            return response()->json(['success' => false, 'message' => __('الطاولة غير موجودة أو غير مفعّلة.')], 404);
        }

        $order = $this->cart->joinOrCreateForTable((int) $request->user()->id, $table);

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => (int) $order->id,
                'share_token' => (string) $order->share_token,
                'table' => ['id' => (int) $table->id, 'label' => (string) $table->label],
            ],
        ], 201);
    }
}
