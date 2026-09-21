<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\OrderResource;
use App\Models\Order;
use App\Services\DeliveryDispatchService;
use App\Support\BusinessContext;
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

    /**
     * GET /api/v2/delivery/me -- read-only: `null` data means "not a driver
     * yet", never registers or reactivates one (see myStatus()'s own note).
     */
    public function me(Request $request)
    {
        $driver = $this->delivery->myStatus((int) $request->user()->id);

        return response()->json(['success' => true, 'data' => $driver ? $this->driverPayload($driver) : null]);
    }

    /** POST /api/v2/delivery/register */
    public function register(Request $request)
    {
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:40'],
            'vehicle_label' => ['nullable', 'string', 'max:120'],
        ]);

        if (! $request->user()->isShippingCarrier()) {
            abort(403, __('التسجيل كموصّل متاح لحسابات «شحن وتوصيل» فقط.'));
        }

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

    /**
     * POST /api/v2/delivery/location — the driver's app calls this every
     * 30-60s WHILE it is carrying an active order. Not enforced server-side
     * (an idle ping is harmless), so no separate "am I on a job" check here.
     */
    public function pingLocation(Request $request)
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $driver = $this->delivery->pingLocation((int) $request->user()->id, (float) $data['lat'], (float) $data['lng']);

        return response()->json(['success' => true, 'data' => $this->driverPayload($driver)]);
    }

    /** GET /api/v2/delivery/available-orders */
    public function available(Request $request)
    {
        $driver = $this->delivery->driverOrFail((int) $request->user()->id);

        // A business's own private driver only ever sees that business's own
        // orders; a freelance driver (business_id null) keeps the full pool.
        // The driver's own position (sent by the app) turns the list into an
        // "available near you" one: distance to the pickup point, nearest
        // first. Without it the list is just oldest-first.
        $lat = $request->filled('lat') ? (float) $request->query('lat') : null;
        $lng = $request->filled('lng') ? (float) $request->query('lng') : null;

        $orders = $this->delivery
            ->availableOrders(50, $driver->business_id ? (int) $driver->business_id : null)
            ->map(function ($o) use ($lat, $lng) {
                $b = $o->business;
                $distance = ($lat !== null && $lng !== null && $b && $b->latitude !== null && $b->longitude !== null)
                    ? round(\App\Models\DeliveryDriver::haversineKm($lat, $lng, (float) $b->latitude, (float) $b->longitude), 1)
                    : null;

                return [
                    'order_id' => (int) $o->id,
                    'business' => $b ? ['id' => (int) $b->id, 'name' => (string) $b->name] : null,
                    'address' => (string) $o->address,
                    'final_total' => (float) $o->final_total,
                    'delivery_fee' => (float) $o->delivery_fee,
                    'distance_km' => $distance,
                    'needs_quote' => (string) $o->delivery_fee_status === \App\Models\Order::FEE_AWAITING_QUOTE,
                ];
            })
            ->sortBy(fn ($row) => $row['distance_km'] ?? PHP_INT_MAX)
            ->values();

        return response()->json(['success' => true, 'data' => ['orders' => $orders]]);
    }

    /** POST /api/v2/delivery/orders/{order}/accept */
    public function accept(Request $request, int $order)
    {
        $data = $request->validate(['fee_amount' => ['nullable', 'numeric', 'min:0', 'max:99999.99']]);

        $model = $this->delivery->acceptOrder(
            (int) $request->user()->id,
            $order,
            isset($data['fee_amount']) ? (float) $data['fee_amount'] : null,
        );

        return response()->json(['success' => true, 'data' => [
            'order_id' => (int) $model->id,
            'delivery_stage' => (string) $model->delivery_stage,
        ]], 201);
    }

    /**
     * PATCH /api/v2/delivery/delivery-fee — a freelance driver's own flat
     * rate (fallback only, see DeliveryDispatchService::setOwnDeliveryFee).
     * Any registered driver may call this, business-linked or not - it's
     * simply unused while they carry their own business's orders.
     */
    public function updateOwnDeliveryFee(Request $request)
    {
        $data = $request->validate(['delivery_fee_amount' => ['nullable', 'numeric', 'min:0', 'max:99999.99']]);

        $driver = $this->delivery->setOwnDeliveryFee((int) $request->user()->id, $data['delivery_fee_amount'] ?? null);

        return response()->json(['success' => true, 'data' => ['delivery_fee_amount' => $driver->delivery_fee_amount]]);
    }

    /**
     * GET /api/v2/business/delivery-drivers — the merchant's own roster,
     * with live workload and (when the business has a saved location) each
     * driver's current distance, for the "choose who delivers this" screen
     * that opens right after marking a delivery order ready.
     */
    public function roster(Request $request)
    {
        $business = BusinessContext::business($request);
        $lat = $business->latitude !== null ? (float) $business->latitude : null;
        $lng = $business->longitude !== null ? (float) $business->longitude : null;
        $radiusKm = max(1, min(50, (float) $request->get('radius_km', 5)));

        $roster = $this->delivery->businessRoster((int) $business->id, $lat, $lng);

        // Visibility only, same as the web panel's own "موصّليّ" screen: if
        // this business's own roster is all busy or off duty, seeing that
        // freelancers exist nearby is the answer to "can I still get this
        // delivered" - the business can never assign one directly
        // (nearbyFreelanceDrivers()'s own doc explains why), only leave the
        // order unassigned for one of them to self-accept from the open pool.
        $nearbyFreelancers = ($lat !== null && $lng !== null)
            ? $this->delivery->nearbyFreelanceDrivers($lat, $lng, $radiusKm)
            : collect();

        return response()->json([
            'success' => true,
            'data' => ['drivers' => $roster, 'nearby_freelancers' => $nearbyFreelancers],
        ]);
    }

    /**
     * PATCH /api/v2/business/delivery-drivers/{driver} — off duty / on duty
     * for one of this business's own linked drivers. Never a hard delete:
     * their assigned/picked_up/delivered counters and the
     * delivery_completions ledger stay attributable, matching the web
     * panel's own "موصّليّ" screen (Business\DeliveryDriverController::update).
     */
    public function updateDriver(Request $request, int $driver)
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);

        $row = $this->delivery->setBusinessDriverActive(BusinessContext::id($request), $driver, (bool) $data['is_active']);

        return response()->json(['success' => true, 'data' => ['id' => (int) $row->id, 'is_active' => (bool) $row->is_active]]);
    }

    /**
     * GET /api/v2/business/delivery-settings — the business's own flat
     * delivery charge (applied automatically at checkout when the customer
     * chooses delivery, see CustomerCartService::placeOrder). Owner-only.
     */
    public function deliverySettings(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => ['delivery_fee_amount' => $request->user()->delivery_fee_amount],
        ]);
    }

    /** PATCH /api/v2/business/delivery-settings — owner-only. */
    public function updateDeliverySettings(Request $request)
    {
        $data = $request->validate(['delivery_fee_amount' => ['nullable', 'numeric', 'min:0', 'max:99999.99']]);

        $business = $request->user();
        $business->delivery_fee_amount = $data['delivery_fee_amount'] ?? null;
        $business->save();

        return response()->json([
            'success' => true,
            'data' => ['delivery_fee_amount' => $business->delivery_fee_amount],
        ]);
    }

    /** POST /api/v2/business/orders/{order}/assign-driver */
    public function assignDriver(Request $request, int $order)
    {
        $data = $request->validate(['driver_id' => ['required', 'integer']]);

        $model = $this->delivery->assignDriver(BusinessContext::id($request), $order, (int) $data['driver_id']);

        return response()->json(['success' => true, 'data' => [
            'order_id' => (int) $model->id,
            'delivery_stage' => (string) $model->delivery_stage,
        ]]);
    }

    /**
     * GET /api/v2/delivery/my-orders — the driver's own active deliveries,
     * full invoice + customer address/phone/location included so the
     * driver's app never has to make a second call before setting off.
     */
    public function myOrders(Request $request)
    {
        $orders = $this->delivery->myActiveOrders((int) $request->user()->id);

        return OrderResource::collection($orders)->additional(['success' => true]);
    }

    /**
     * POST /api/v2/delivery/orders/{order}/eta — the assigned driver tells
     * the customer roughly when to expect the order. Exactly one of
     * eta_minutes (from right now) or eta_at (a specific clock time) — never
     * both, and the validation below enforces that.
     */
    public function notifyEta(Request $request, int $order)
    {
        $data = $request->validate([
            'eta_minutes' => ['required_without:eta_at', 'prohibits:eta_at', 'nullable', 'integer', 'min:1', 'max:240'],
            'eta_at' => ['required_without:eta_minutes', 'prohibits:eta_minutes', 'nullable', 'date'],
        ]);

        $model = $this->delivery->notifyEta(
            $order,
            (int) $request->user()->id,
            isset($data['eta_minutes']) ? (int) $data['eta_minutes'] : null,
            $data['eta_at'] ?? null,
        );

        return response()->json(['success' => true, 'data' => ['order_id' => (int) $model->id]]);
    }

    /**
     * POST /api/v2/delivery/orders/{order}/confirm-payment — the assigned
     * driver confirms they collected the delivery_fee in cash from the
     * customer (their own leg only - see OrderController::businessConfirmPayment
     * for the order-amount leg the merchant confirms separately).
     */
    public function confirmPayment(Request $request, int $order)
    {
        $model = $this->delivery->confirmPaymentReceived($order, (int) $request->user()->id);

        return response()->json(['success' => true, 'data' => [
            'order_id' => (int) $model->id,
            'driver_payment_confirmed_at' => optional($model->driver_payment_confirmed_at)->toIso8601String(),
        ]]);
    }

    /**
     * POST /api/v2/business/orders/{order}/pickup-token - same stage-1 token,
     * for the owner or a delegated staff member acting for the business.
     */
    public function businessPickupToken(Request $request, int $order)
    {
        $model = Order::query()->findOrFail($order);
        $token = $this->delivery->issuePickupToken($model, BusinessContext::id($request));

        return response()->json(['success' => true, 'data' => [
            'order_id' => (int) $model->id,
            'pickup_token' => $token,
            'scan_path' => '/dp/' . $token,
        ]]);
    }

    /** POST /api/v2/delivery/orders/{order}/fee-proposal - the assigned courier prices an out-of-city order. */
    public function proposeFee(Request $request, int $order)
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0', 'max:99999.99']]);

        $model = $this->delivery->proposeFee((int) $request->user()->id, $order, (float) $data['amount']);

        return response()->json(['success' => true, 'data' => [
            'order_id' => (int) $model->id,
            'delivery_fee_status' => (string) $model->delivery_fee_status,
            'delivery_fee_proposed' => $model->delivery_fee_proposed,
        ]]);
    }

    /** POST /api/v2/orders/{order}/delivery-fee/accept - the customer agrees to the courier's price. */
    public function acceptFee(Request $request, int $order)
    {
        return $this->answerFee($request, $order, true);
    }

    /** POST /api/v2/orders/{order}/delivery-fee/decline - the courier is released, the order is re-offered. */
    public function declineFee(Request $request, int $order)
    {
        return $this->answerFee($request, $order, false);
    }

    private function answerFee(Request $request, int $order, bool $accept)
    {
        $model = $this->delivery->respondToFeeProposal((int) $request->user()->id, $order, $accept);

        return response()->json(['success' => true, 'data' => [
            'order_id' => (int) $model->id,
            'delivery_fee_status' => (string) $model->delivery_fee_status,
            'delivery_fee' => (float) $model->delivery_fee,
            'final_total' => (float) $model->final_total,
        ]]);
    }

    /** POST /api/v2/business/orders/{order}/delivery-fee/recommendation */
    public function recommendFee(Request $request, int $order)
    {
        $data = $request->validate([
            'recommendation' => ['required', 'in:suitable,not_suitable'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $model = $this->delivery->recommendFee(BusinessContext::id($request), $order, $data['recommendation'], $data['note'] ?? null);

        return response()->json(['success' => true, 'data' => [
            'order_id' => (int) $model->id,
            'recommendation' => $model->delivery_fee_recommendation,
        ]]);
    }

    /** POST /api/v2/business/orders/{order}/pickup-token/reset - a fresh pickup code; the old one dies. */
    public function resetPickupToken(Request $request, int $order)
    {
        $model = Order::query()->findOrFail($order);
        $token = $this->delivery->resetPickupToken($model, BusinessContext::id($request));

        return response()->json(['success' => true, 'data' => [
            'order_id' => (int) $model->id,
            'pickup_token' => $token,
            'scan_path' => '/dp/' . $token,
        ]]);
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
            'fast_delivery_count' => (int) $driver->fast_delivery_count,
            'delivery_fee_amount' => $driver->delivery_fee_amount,
            // Set when a business linked this driver to its own team (they
            // work for it, and see the job under "أعمالي"); null = independent.
            'business_id' => $driver->business_id ? (int) $driver->business_id : null,
            'business_name' => $driver->business_id ? optional(\App\Models\User::query()->find($driver->business_id))->name : null,
        ];
    }
}
