<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\DeliveryDispatchService;
use Illuminate\Http\Request;

/**
 * The connected delivery loop (v2). A driver registers, accepts a ready delivery
 * order, confirms pickup from the restaurant (stage 1), and the customer confirms
 * receipt (stage 2). See DeliveryDispatchService.
 */
final class DeliveryController extends Controller
{
    public function __construct(private readonly DeliveryDispatchService $delivery)
    {
    }

    /** POST /api/v2/delivery/register */
    public function register(Request $request)
    {
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:40'],
            'vehicle_label' => ['nullable', 'string', 'max:120'],
        ]);

        $driver = $this->delivery->registerDriver((int) $request->user()->id, $data);

        return response()->json(['success' => true, 'data' => $this->driverPayload($driver)], 201);
    }

    /** POST /api/v2/delivery/availability */
    public function availability(Request $request)
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $driver = $this->delivery->setAvailability((int) $request->user()->id, (bool) $data['is_active']);

        return response()->json(['success' => true, 'data' => $this->driverPayload($driver)]);
    }

    /** GET /api/v2/delivery/available-orders */
    public function available(Request $request)
    {
        $this->delivery->driverOrFail((int) $request->user()->id);

        $orders = $this->delivery->availableOrders()->map(fn ($o) => [
            'order_id' => (int) $o->id,
            'business' => $o->business ? ['id' => (int) $o->business->id, 'name' => (string) $o->business->name] : null,
            'address' => (string) $o->address,
            'final_total' => (float) $o->final_total,
            'delivery_fee' => (float) $o->delivery_fee,
        ]);

        return response()->json(['success' => true, 'data' => ['orders' => $orders]]);
    }

    /** POST /api/v2/delivery/orders/{order}/accept */
    public function accept(Request $request, int $order)
    {
        $model = $this->delivery->acceptOrder((int) $request->user()->id, $order);

        return response()->json(['success' => true, 'data' => [
            'order_id' => (int) $model->id,
            'delivery_stage' => (string) $model->delivery_stage,
        ]], 201);
    }

    /** POST /api/v2/delivery/orders/{order}/pickup-token — restaurant issues stage-1 token. */
    public function issuePickupToken(Request $request, int $order)
    {
        $model = Order::query()->findOrFail($order);
        $token = $this->delivery->issuePickupToken($model, (int) $request->user()->id);

        return response()->json(['success' => true, 'data' => [
            'order_id' => (int) $model->id,
            'pickup_token' => $token,
            'scan_path' => '/dp/' . $token,
        ]]);
    }

    /** POST /api/v2/delivery/orders/{order}/delivery-token — driver issues stage-2 token. */
    public function issueDeliveryToken(Request $request, int $order)
    {
        $model = $this->delivery->issueDeliveryToken($order, (int) $request->user()->id);

        return response()->json(['success' => true, 'data' => [
            'order_id' => (int) $model->id,
            'delivery_token' => (string) $model->delivery_token,
            'scan_path' => '/dd/' . $model->delivery_token,
        ]]);
    }

    /** POST /api/v2/delivery/pickup/{token}/confirm — driver confirms pickup. */
    public function confirmPickup(Request $request, string $token)
    {
        $order = $this->delivery->confirmPickup($token, (int) $request->user()->id);

        return response()->json(['success' => true, 'data' => [
            'order_id' => (int) $order->id,
            'delivery_stage' => (string) $order->delivery_stage,
        ]]);
    }

    /** POST /api/v2/delivery/deliver/{token}/confirm — customer confirms receipt. */
    public function confirmDelivery(Request $request, string $token)
    {
        $order = $this->delivery->confirmDelivery($token, (int) $request->user()->id);

        return response()->json(['success' => true, 'data' => [
            'order_id' => (int) $order->id,
            'status' => (string) $order->status,
            'delivery_stage' => (string) $order->delivery_stage,
        ]]);
    }

    private function driverPayload($driver): array
    {
        return [
            'driver_id' => (int) $driver->id,
            'is_active' => (bool) $driver->is_active,
            'assigned_count' => (int) $driver->assigned_count,
            'picked_up_count' => (int) $driver->picked_up_count,
            'delivered_count' => (int) $driver->delivered_count,
        ];
    }
}
