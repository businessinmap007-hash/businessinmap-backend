<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Models\Courier;
use App\Models\ServiceFee;
use App\Models\User;
use App\Models\ServiceOrderRejection;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DeliveryController extends Controller
{
    public function __construct()
    {
        $language = request()->headers->get('lang') ?: 'ar';
        app()->setLocale($language);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Ensure current user is a Courier (business shipping)
     */
    protected function ensureCourierUser(Request $request): Courier
    {
        $user = $request->user();

        if (!$user || $user->type !== 'business' || (int)$user->category_id !== 5) {
            abort(response()->json([
                'status'  => 403,
                'message' => 'Only Shipping & Delivery business can perform this action.',
            ], 403));
        }

       $courier = Courier::firstOrCreate(
            ['user_id' => $user->id],
            [
                'is_active' => 1,
                'location_lat' => null,
                'location_lng' => null,
                'accepted_count' => 0,
                'delivered_count' => 0,
                'cancelled_count' => 0,
                'total_ops' => 0,
            ]
        );

        if (!$courier->is_active) {
            abort(response()->json([
                'status'  => 403,
                'message' => 'Courier is not active.',
            ], 403));
        }

        return $courier;
    }

    /**
     * Get delivery platform fee (can be 0)
     */
    protected function getDeliveryFee(): float
    {
        $fee = ServiceFee::where('code', 'delivery_platform_fee')
            ->where('is_active', 1)
            ->first();

        return $fee ? (float)$fee->amount : 0.0;
    }

    /**
     * Validate business_id is a real store/restaurant (business AND not shipping category=5)
     */
    protected function validateStoreBusiness(int $businessId): void
    {
        $business = User::find($businessId);

        if (!$business || $business->type !== 'business') {
            abort(response()->json([
                'status'  => 422,
                'message' => 'business_id must be a business account (store/restaurant).',
            ], 422));
        }

        if ((int)$business->category_id === 5) {
            abort(response()->json([
                'status'  => 422,
                'message' => 'business_id cannot be a Shipping & Delivery business.',
            ], 422));
        }
    }

    /**
     * Soft warning: if courier cancels >= 3 times today
     */
    protected function maybeSendCourierCancelWarning(int $courierUserId): void
    {
        $today = Carbon::today();

        // Count cancellations TODAY by courier (cancelled_by_driver only)
        $count = DeliveryOrder::where('courier_id', $courierUserId)
            ->where('status', 'cancelled_by_driver')
            ->whereDate('updated_at', $today)
            ->count();

        if ($count >= 3) {
            if (function_exists('send_notification')) {
                send_notification(
                    $courierUserId,
                    "⚠️ تحذير",
                    "courier_cancel_warning",
                    [
                        "message_ar" => "⚠️ تكرار إلغاء الطلبات قد يؤدي لاحقًا إلى إيقاف حسابك.",
                        "message_en" => "⚠️ Repeated cancellations may later lead to account suspension.",
                        "today_cancel_count" => $count,
                    ]
                );
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 1) Create delivery order (user OR business requester) - must select a store
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'business_id'     => 'required|exists:users,id', // store/restaurant
            'pickup_address'  => 'required|string',
            'pickup_lat'      => 'required|numeric',
            'pickup_lng'      => 'required|numeric',
            'dropoff_address' => 'required|string',
            'dropoff_lat'     => 'required|numeric',
            'dropoff_lng'     => 'required|numeric',
            'delivery_type'   => 'nullable|string|max:191',
            'weight'          => 'nullable|string|max:191',
            'price_estimated' => 'nullable|numeric|min:0',
            'price_final'     => 'nullable|numeric|min:0',
            'payment_method'  => 'required|in:cash,online,wallet',
            'notes'           => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        $this->validateStoreBusiness((int)$request->business_id);

        $user = $request->user();

        $order = DeliveryOrder::create([
            'user_id'         => $user->id,
            'business_id'     => $request->business_id,

            'pickup_address'  => $request->pickup_address,
            'pickup_lat'      => $request->pickup_lat,
            'pickup_lng'      => $request->pickup_lng,

            'dropoff_address' => $request->dropoff_address,
            'dropoff_lat'     => $request->dropoff_lat,
            'dropoff_lng'     => $request->dropoff_lng,

            'delivery_type'   => $request->delivery_type,
            'weight'          => $request->weight,

            'price_estimated' => $request->price_estimated,
            'price_final'     => $request->price_final,
            'payment_method'  => $request->payment_method,

            // legacy field in your table
            'price'           => $request->price_estimated ?? $request->price_final,

            'notes'           => $request->notes,
            'status'          => 'pending',
        ]);

        if (function_exists('send_notification')) {
            send_notification(
                $order->business_id,
                "طلب دليفري جديد",
                "delivery_new",
                [
                    "delivery_order_id" => $order->id,
                    "from_user_id"      => $order->user_id,
                ]
            );
        }

        return response()->json([
            'status'  => 200,
            'message' => 'Delivery order created successfully',
            'data'    => $order,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 2) My orders (requester)
    |--------------------------------------------------------------------------
    */
    public function myOrders(Request $request)
    {
        $orders = DeliveryOrder::where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'status'  => 200,
            'message' => 'My delivery orders',
            'data'    => $orders,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 3) Store/restaurant business orders
    |--------------------------------------------------------------------------
    */
    public function businessOrders(Request $request)
    {
        $orders = DeliveryOrder::where('business_id', $request->user()->id)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'status'  => 200,
            'message' => 'Business delivery orders',
            'data'    => $orders,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 4) Courier orders (kept name driverOrders for frontend compatibility)
    |--------------------------------------------------------------------------
    */
    public function driverOrders(Request $request)
    {
        $this->ensureCourierUser($request);

        $orders = DeliveryOrder::where('courier_id', $request->user()->id)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'status'  => 200,
            'message' => 'Courier delivery orders',
            'data'    => $orders,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | C) Available orders for courier (pending + no courier assigned)
    | - exclude rejected orders from service_order_rejections (target_type=delivery_order)
    |--------------------------------------------------------------------------
    */
    public function availableOrders(Request $request)
    {
        $this->ensureCourierUser($request);

        $lat = $request->query('lat');
        $lng = $request->query('lng');
        $radiusKm = $request->query('radius_km', 10);

        $courierUserId = (int)$request->user()->id;

        $q = DeliveryOrder::query()
            ->where('status', 'pending')
            ->whereNull('courier_id')
            ->whereNotIn('id', function ($sub) use ($courierUserId) {
                $sub->select('target_id')
                    ->from('service_order_rejections')
                    ->where('provider_id', $courierUserId)
                    ->where('target_type', 'delivery_order');
            })
            ->with(['user', 'business'])
            ->orderByDesc('id');

        // optional location filter
        if (is_numeric($lat) && is_numeric($lng)) {
            $lat = (float)$lat;
            $lng = (float)$lng;
            $radiusKm = (float)$radiusKm;

            $haversine = "(6371 * acos(
                cos(radians(?)) * cos(radians(pickup_lat)) *
                cos(radians(pickup_lng) - radians(?)) +
                sin(radians(?)) * sin(radians(pickup_lat))
            ))";

            $q->select('*')
                ->selectRaw("$haversine AS distance_km", [$lat, $lng, $lat])
                ->having('distance_km', '<=', $radiusKm)
                ->orderBy('distance_km', 'asc');
        }

        $orders = $q->paginate(20);

        return response()->json([
            'status'  => 200,
            'message' => 'Available delivery orders',
            'data'    => $orders,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 5) Show order + courier profile
    |--------------------------------------------------------------------------
    */
    public function show($id)
    {
        $order = DeliveryOrder::with(['user', 'business', 'courier'])->findOrFail($id);

        $courierProfile = null;
        if ($order->courier_id) {
            $c = Courier::where('user_id', $order->courier_id)->first();
            if ($c) {
                $courierProfile = [
                    'is_active'     => (bool)$c->is_active,
                    'location_lat'  => $c->location_lat,
                    'location_lng'  => $c->location_lng,
                    'accepted_count'  => $c->accepted_count ?? null,
                    'delivered_count' => $c->delivered_count ?? null,
                    'cancelled_count' => $c->cancelled_count ?? null,
                    'total_ops'       => $c->total_ops ?? null,
                ];
            }
        }

        return response()->json([
            'status'  => 200,
            'message' => 'Delivery order details',
            'data'    => $order,
            'courier_profile' => $courierProfile,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 6) Courier accepts order
    | - must have enough wallet balance for fee
    | - HOLD fee
    |--------------------------------------------------------------------------
    */
    public function accept(Request $request, $id, WalletService $walletService)
    {
        $courierProfile = $this->ensureCourierUser($request);
        $courierUserId  = (int)$request->user()->id;
        $fee            = $this->getDeliveryFee();

        return DB::transaction(function () use ($request, $id, $walletService, $courierProfile, $courierUserId, $fee) {

            $order = DeliveryOrder::where('id', $id)->lockForUpdate()->firstOrFail();

            if ($order->status !== 'pending' || $order->courier_id !== null) {
                return response()->json([
                    'status'  => 400,
                    'message' => 'Order is not available for accepting',
                ], 400);
            }

            if ($fee > 0) {
                $wallet = $walletService->getOrCreateWallet($courierUserId);

                if ((float)$wallet->balance < (float)$fee) {
                    return response()->json([
                        'status'  => 422,
                        'message' => 'Insufficient wallet balance to accept this delivery task.',
                        'meta'    => [
                            'required_fee'   => (float)$fee,
                            'wallet_balance' => (float)$wallet->balance,
                        ],
                    ], 422);
                }

                $walletService->hold(
                    $courierUserId,
                    $fee,
                    'delivery_order',
                    (string)$order->id,
                    'Hold delivery platform fee',
                    'delivery:hold:' . $order->id,
                    ['fee_code' => 'delivery_platform_fee']
                );
            }

            $order->update([
                'courier_id' => $courierUserId,
                'status'     => 'accepted',
            ]);

            $courierProfile->increment('accepted_count');
            $courierProfile->increment('total_ops');

            if (function_exists('send_notification')) {
                send_notification(
                    $order->user_id,
                    "تم قبول طلب الدليفري",
                    "delivery_accepted",
                    [
                        "delivery_order_id" => $order->id,
                        "courier_id"        => $order->courier_id,
                    ]
                );
            }

            return response()->json([
                'status'  => 200,
                'message' => 'Order accepted successfully',
                'data'    => $order,
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | 7) Update status (courier only for movement/delivered)
    | - delivered => CAPTURE locked fee
    |--------------------------------------------------------------------------
    */
    public function updateStatus(Request $request, $id, WalletService $walletService)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,accepted,on_the_way,delivering,delivered,cancelled_by_user,cancelled_by_business,cancelled_by_driver',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        $order = DeliveryOrder::findOrFail($id);
        $newStatus = $request->status;

        if (in_array($newStatus, ['on_the_way', 'delivering', 'delivered'], true)) {
            $courierProfile = $this->ensureCourierUser($request);

            if ((int)$order->courier_id !== (int)$request->user()->id) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Only assigned courier can update this status.',
                ], 403);
            }

            if ($newStatus === 'delivered') {
                if ($order->status === 'delivered') {
                    return response()->json([
                        'status'  => 400,
                        'message' => 'Order already delivered.',
                    ], 400);
                }

                $fee = $this->getDeliveryFee();

                if ($fee > 0) {
                    $walletService->captureLocked(
                        (int)$request->user()->id,
                        $fee,
                        'delivery_order',
                        (string)$order->id,
                        'Capture delivery platform fee',
                        'delivery:capture:' . $order->id,
                        ['fee_code' => 'delivery_platform_fee']
                    );
                }

                $order->update(['status' => 'delivered']);
                $courierProfile->increment('delivered_count');
            } else {
                $order->update(['status' => $newStatus]);
            }
        } else {
            $order->update(['status' => $newStatus]);
        }

        if (function_exists('send_notification')) {
            foreach (array_unique([$order->user_id, $order->business_id, $order->courier_id]) as $target) {
                if ($target) {
                    send_notification(
                        $target,
                        "تم تحديث حالة الدليفري إلى {$newStatus}",
                        "delivery_status",
                        [
                            "delivery_order_id" => $order->id,
                            "status"            => $newStatus,
                        ]
                    );
                }
            }
        }

        return response()->json([
            'status'  => 200,
            'message' => 'Order status updated',
            'data'    => $order,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 8) Cancel order
    | - cancelled_by_driver => RELEASE or CAPTURE penalty (B policy remains as you set)
    | - Soft warning if courier cancels >= 3 times today
    |--------------------------------------------------------------------------
    */
    public function cancel(Request $request, $id, WalletService $walletService)
    {
        $order = DeliveryOrder::findOrFail($id);
        $user  = $request->user();

        $oldStatus = $order->status;

        if ($user->id == $order->user_id) {
            $status = 'cancelled_by_user';
        } elseif ($user->id == $order->business_id) {
            $status = 'cancelled_by_business';
        } elseif ($user->id == $order->courier_id) {
            $this->ensureCourierUser($request);
            $status = 'cancelled_by_driver';
        } else {
            return response()->json([
                'status'  => 403,
                'message' => 'Unauthorized',
            ], 403);
        }

        $fee = $this->getDeliveryFee();

        if ($order->courier_id && $fee > 0) {

            $courierId = (int)$order->courier_id;

            if ($status === 'cancelled_by_driver') {

                // policy B: penalty only if started
                if (in_array($oldStatus, ['on_the_way', 'delivering'], true)) {
                    $walletService->captureLocked(
                        $courierId,
                        $fee,
                        'delivery_order',
                        (string)$order->id,
                        'Penalty: courier cancelled after start',
                        'delivery:penalty:' . $order->id,
                        ['fee_code' => 'delivery_platform_fee', 'reason' => 'courier_cancel_after_start']
                    );
                } else {
                    $walletService->release(
                        $courierId,
                        $fee,
                        'delivery_order',
                        (string)$order->id,
                        'Release: courier cancelled before start',
                        'delivery:release:' . $order->id,
                        ['fee_code' => 'delivery_platform_fee', 'reason' => 'courier_cancel_before_start']
                    );
                }

                // increment cancelled_count ONLY if started (كما كنت تريد "لا يزيد")
                $courierProfile = Courier::where('user_id', $courierId)->first();
                if ($courierProfile && in_array($oldStatus, ['on_the_way', 'delivering'], true)) {
                    $courierProfile->increment('cancelled_count');
                }
            } else {
                // cancelled_by_user / cancelled_by_business => release hold
                $walletService->release(
                    (int)$order->courier_id,
                    $fee,
                    'delivery_order',
                    (string)$order->id,
                    'Release: cancelled by user/business',
                    'delivery:release:' . $order->id,
                    ['fee_code' => 'delivery_platform_fee', 'reason' => $status]
                );
            }
        }

        $order->update(['status' => $status]);

        // Soft warning ONLY for courier cancellations (and based on daily count)
        if ($status === 'cancelled_by_driver') {
            $this->maybeSendCourierCancelWarning((int)$user->id);
        }

        if (function_exists('send_notification')) {
            foreach ([$order->user_id, $order->business_id, $order->courier_id] as $target) {
                if ($target && $target != $user->id) {
                    send_notification(
                        $target,
                        "تم إلغاء طلب الدليفري",
                        "delivery_cancelled",
                        [
                            "delivery_order_id" => $order->id,
                            "cancelled_by"      => $user->id,
                            "status"            => $status,
                        ]
                    );
                }
            }
        }

        return response()->json([
            'status'  => 200,
            'message' => 'Order cancelled successfully',
            'data'    => $order,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Reject (hide order for this courier) - uses service_order_rejections
    |--------------------------------------------------------------------------
    */
    public function reject(Request $request, $id)
    {
        $this->ensureCourierUser($request);

        $order = DeliveryOrder::findOrFail($id);

        if ($order->status !== 'pending' || $order->courier_id !== null) {
            return response()->json([
                'status'  => 400,
                'message' => 'Order cannot be rejected in its current state.',
            ], 400);
        }

        $data = $request->validate([
            'reason' => 'nullable|string|max:191',
        ]);

        ServiceOrderRejection::updateOrCreate(
            [
                'provider_id' => (int)$request->user()->id,
                'target_type' => 'delivery_order',
                'target_id'   => (int)$order->id,
            ],
            [
                'reason' => $data['reason'] ?? null,
            ]
        );

        return response()->json([
            'status'  => 200,
            'message' => 'Order rejected.',
        ]);
    }
}
