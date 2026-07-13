<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderHandoverService;
use Illuminate\Http\Request;

/**
 * Order-handover confirmation QR (BIM-13.5). One party issues the ready order's
 * one-time token (to display as a QR); the other scans it to confirm handover,
 * flipping the order to completed. See OrderHandoverService.
 */
final class OrderHandoverController extends Controller
{
    public function __construct(private readonly OrderHandoverService $handover)
    {
    }

    /** POST /api/v2/orders/{order}/handover/issue — issue/return the token. */
    public function issue(Request $request, int $order)
    {
        $model = Order::query()->findOrFail($order);
        $token = $this->handover->issueFor($model, (int) $request->user()->id);

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => (int) $model->id,
                'handover_token' => $token,
                'scan_path' => '/h/' . $token,
            ],
        ]);
    }

    /** POST /api/v2/handover/{token}/confirm — confirm the handover by token. */
    public function confirm(Request $request, string $token)
    {
        $order = $this->handover->confirm($token, (int) $request->user()->id);

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => (int) $order->id,
                'status' => (string) $order->status,
                'handover_confirmed_at' => optional($order->handover_confirmed_at)->toIso8601String(),
            ],
        ]);
    }
}
