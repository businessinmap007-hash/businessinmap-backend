<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RideController extends Controller
{
    /**
     * إنشاء طلب رحلة من العميل
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pickup_address'  => 'required|string',
            'pickup_lat'      => 'required',
            'pickup_lng'      => 'required',
            'dropoff_address' => 'required|string',
            'dropoff_lat'     => 'required',
            'dropoff_lng'     => 'required',
            'payment_method'  => 'required|in:cash,online,wallet',
            'car_id'          => 'nullable|exists:cars,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'errors' => $validator->errors()]);
        }

        // من المفترض أن يكون user من نوع client أو business
        if (!in_array($request->user()->account_type, ['client', 'business'])) {
            return response()->json(['status' => 403, 'message' => 'Only clients/business can request rides']);
        }

        $ride = Ride::create([
            'user_id'        => $request->user()->id,
            'pickup_address' => $request->pickup_address,
            'pickup_lat'     => $request->pickup_lat,
            'pickup_lng'     => $request->pickup_lng,
            'dropoff_address'=> $request->dropoff_address,
            'dropoff_lat'    => $request->dropoff_lat,
            'dropoff_lng'    => $request->dropoff_lng,
            'payment_method' => $request->payment_method,
            'car_id'         => $request->car_id,
            'estimated_price'=> $request->estimated_price ?? null,
            'status'         => 'pending'
        ]);

        // إشعار للسائقين (سيتم تحسينه لاحقًا)
        send_notification_to_all_drivers(
            "رحلة جديدة",
            "هناك طلب رحلة جديد بالقرب منك",
            "ride",
            $ride->id
        );

        return response()->json([
            'status' => 200,
            'message' => 'Ride request created',
            'ride' => $ride
        ]);
    }

    /**
     * قبول الرحلة من السائق
     */
    public function acceptRide($id, Request $request)
    {
        $ride = Ride::findOrFail($id);

        if ($ride->status !== 'pending') {
            return response()->json(['status' => 400, 'message' => 'Ride already taken']);
        }

        // يجب أن يكون user = driver
        if ($request->user()->account_type !== 'driver') {
            return response()->json(['status' => 403, 'message' => 'Only drivers can accept rides']);
        }

        $ride->update([
            'driver_id' => $request->user()->id,
            'status'    => 'accepted'
        ]);

        // إشعار للعميل
        send_notification(
            $ride->user_id,
            "تم قبول الرحلة",
            "السائق قبل رحلتك وهو في الطريق إليك",
            "ride",
            $ride->id
        );

        return response()->json(['status' => 200, 'message' => 'Ride accepted', 'ride' => $ride]);
    }

    /**
     * تحديث حالة الرحلة
     */
    public function updateStatus($id, Request $request)
    {
        $ride = Ride::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:on_the_way,arrived,started,completed,canceled_by_driver,canceled_by_user'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'errors' => $validator->errors()]);
        }

        // فقط السائق أو العميل له الحق
        if (!in_array($request->user()->id, [$ride->user_id, $ride->driver_id])) {
            return response()->json(['status' => 403, 'message' => 'Unauthorized']);
        }

        $ride->update(['status' => $request->status]);

        // إرسال إشعار
        send_notification(
            $ride->user_id,
            "تحديث الرحلة",
            "تم تغيير حالة الرحلة إلى: {$request->status}",
            "ride",
            $ride->id
        );

        return response()->json(['status' => 200, 'message' => 'Status updated', 'ride' => $ride]);
    }

    /**
     * رحلاتي (عميل)
     */
    public function myRides(Request $request)
    {
        $rides = Ride::where('user_id', $request->user()->id)->latest()->get();

        return response()->json(['status' => 200, 'rides' => $rides]);
    }

    /**
     * رحلات السائق
     */
    public function driverRides(Request $request)
    {
        $rides = Ride::where('driver_id', $request->user()->id)->latest()->get();

        return response()->json(['status' => 200, 'rides' => $rides]);
    }

    /**
     * تفاصيل رحلة
     */
    public function show($id)
    {
        return response()->json([
            'status' => 200,
            'ride' => Ride::findOrFail($id)
        ]);
    }
}
