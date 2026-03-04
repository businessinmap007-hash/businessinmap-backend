<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * إنشاء طلب جديد من عناصر الكارت
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'business_id'     => 'required|exists:users,id',
            'payment_method'  => 'required|in:cash,online,wallet',
            'address'         => 'required|string',
            'items'           => 'required|array|min:1',

            // items[]
            'items.*.menu_id'   => 'required|exists:menu_items,id',
            'items.*.size_id'   => 'nullable|exists:menu_item_sizes,id',
            'items.*.qty'       => 'required|integer|min:1',
            'items.*.price'     => 'required|numeric',
            'items.*.addons'    => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors()
            ]);
        }

        // إنشاء الطلب
        $order = Order::create([
            'user_id'        => $request->user()->id,
            'business_id'    => $request->business_id,
            'total'          => 0, // سيتم حسابها لاحقًا
            'status'         => 'pending',
            'payment_method' => $request->payment_method,
            'address'        => $request->address,
        ]);

        $total = 0;

        // حفظ عناصر الطلب
        foreach ($request->items as $item) {
            
            $addons = isset($item['addons']) ? json_encode($item['addons']) : null;

            OrderItem::create([
                'order_id'  => $order->id,
                'menu_id'   => $item['menu_id'],
                'size_id'   => $item['size_id'] ?? null,
                'qty'       => $item['qty'],
                'price'     => $item['price'],
                'addons'    => $addons,
            ]);

            $total += $item['price'] * $item['qty'];
        }

        // تحديث إجمالي الطلب
        $order->update(['total' => $total]);

        return response()->json([
            'status' => 200,
            'message' => 'Order created successfully',
            'order' => $order->load('items')
        ]);
    }

    /**
     * طلبات المستخدم
     */
    public function myOrders(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)
                        ->with('items')
                        ->latest()
                        ->get();

        return response()->json([
            'status' => 200,
            'orders' => $orders
        ]);
    }

    /**
     * طلبات البزنس (التاجر)
     */
    public function businessOrders(Request $request)
    {
        $orders = Order::where('business_id', $request->user()->id)
                        ->with('items')
                        ->latest()
                        ->get();

        return response()->json([
            'status' => 200,
            'orders' => $orders
        ]);
    }

    /**
     * تحديث حالة الطلب
     */
    public function updateStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'status'   => 'required|in:pending,accepted,preparing,on_the_way,completed,canceled'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'errors' => $validator->errors()
            ]);
        }

        $order = Order::find($request->order_id);

        // يتحقق أن البزنس هو صاحب الطلب
        if ($order->business_id != $request->user()->id) {
            return response()->json(['status' => 403, 'message' => 'Unauthorized']);
        }

        $order->update(['status' => $request->status]);

        return response()->json([
            'status' => 200,
            'message' => 'Order status updated',
            'order' => $order
        ]);
    }

    /**
     * تفاصيل طلب
     */
    public function show($id)
    {
        $order = Order::with('items')->findOrFail($id);

        return response()->json([
            'status' => 200,
            'order' => $order
        ]);
    }
}
