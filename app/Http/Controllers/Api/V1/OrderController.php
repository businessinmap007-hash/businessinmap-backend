<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\MenuCart; // لو اسم موديل الكارت مختلف عدله
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    public function __construct()
    {
        $language = request()->headers->get('lang') ?: 'ar';
        app()->setLocale($language);
    }

    /**
     * إنشاء طلب جديد من MenuCart
     */
    public function storeFromMenuCart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'business_id'    => 'required|exists:users,id',
            'payment_method' => 'required|in:cash,online,wallet',
            'delivery_type'  => 'required|in:delivery,pickup',
            'address'        => 'nullable|string',
            'latitude'       => 'nullable|numeric',
            'longitude'      => 'nullable|numeric',
            'notes'          => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        // نجيب عناصر الكارت الخاصة بالمستخدم + البزنس
        $cartItems = MenuCart::where('user_id', $user->id)
            ->where('business_id', $request->business_id)
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'status'  => 400,
                'message' => 'Cart is empty',
            ], 400);
        }

        DB::beginTransaction();

        try {
            $total = 0;

            foreach ($cartItems as $item) {
                $total += ($item->price * $item->qty);
            }

            // إنشاء الطلب
            $order = Order::create([
                'user_id'        => $user->id,
                'business_id'    => $request->business_id,
                'total_price'    => $total,
                'status'         => 'pending',
                'payment_method' => $request->payment_method,
                'delivery_type'  => $request->delivery_type,
                'address'        => $request->address,
                'latitude'       => $request->latitude,
                'longitude'      => $request->longitude,
                'notes'          => $request->notes,
            ]);

            // إضافة العناصر الخاصة بالطلب
            foreach ($cartItems as $item) {
                OrderItem::create([
                    'order_id'     => $order->id,
                    'menu_item_id' => $item->menu_item_id,   // عدّل الاسم لو مختلف
                    'qty'          => $item->qty,
                    'price'        => $item->price,
                    'size'         => $item->size ?? null,
                    'extras'       => $item->extras ?? null,
                    'notes'        => $item->notes ?? null,
                ]);
            }

            // تفريغ الكارت بعد عمل الطلب
            MenuCart::where('user_id', $user->id)
                ->where('business_id', $request->business_id)
                ->delete();

            // إشعار لصاحب البزنس
            if (function_exists('send_notification')) {
                send_notification(
                    $request->business_id,
                    "لديك طلب جديد من " . $user->name,
                    "new_order",
                    [
                        "order_id"    => $order->id,
                        "user_id"     => $user->id,
                        "total_price" => $order->total_price,
                    ]
                );
            }

            DB::commit();

            return response()->json([
                'status'  => 200,
                'message' => 'Order created successfully',
                'data'    => $order->load('items'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status'  => 500,
                'message' => 'Error while creating order',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * طلبات المستخدم (Client/Business كـ customer)
     */
    public function myOrders(Request $request)
    {
        $orders = Order::with('items')
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 200,
            'data'   => $orders,
        ]);
    }

    /**
     * طلبات البزنس (كمقدم خدمة)
     */
    public function businessOrders(Request $request)
    {
        $orders = Order::with('items', 'user')
            ->where('business_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 200,
            'data'   => $orders,
        ]);
    }

    /**
     * تفاصيل طلب واحد
     */
    public function show(Request $request, $id)
    {
        $order = Order::with('items', 'user', 'business')->findOrFail($id);

        // تأمين: المستخدم له علاقة بالطلب
        if ($order->user_id != $request->user()->id &&
            $order->business_id != $request->user()->id) {

            return response()->json([
                'status'  => 403,
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'status' => 200,
            'data'   => $order,
        ]);
    }

    /**
     * تحديث حالة الطلب (من البزنس)
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,confirmed,preparing,on_way,delivered,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'errors'  => $validator->errors(),
            ], 422);
        }

        $order = Order::findOrFail($id);

        // فقط صاحب البزنس يقدر يغيّر الحالة
        if ($order->business_id != $request->user()->id) {
            return response()->json([
                'status'  => 403,
                'message' => 'Unauthorized',
            ], 403);
        }

        $order->update(['status' => $request->status]);

        // إشعار للعميل بتحديث الحالة
        if (function_exists('send_notification')) {
            send_notification(
                $order->user_id,
                "تم تحديث حالة طلبك إلى " . $request->status,
                "order_status",
                [
                    "order_id" => $order->id,
                    "status"   => $order->status,
                ]
            );
        }

        return response()->json([
            'status'  => 200,
            'message' => 'Status updated',
            'data'    => $order,
        ]);
    }
}
