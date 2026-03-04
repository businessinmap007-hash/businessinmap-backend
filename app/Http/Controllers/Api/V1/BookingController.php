<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BookingController extends Controller
{
    /**
     * إنشاء حجز جديد
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'business_id'  => 'required|exists:users,id',
            'service_type' => 'nullable|string',
            'service_id'   => 'nullable|integer',
            'date'         => 'nullable|date',
            'time'         => 'nullable|string',
            'price'        => 'nullable|numeric',
            'notes'        => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $booking = Booking::create([
            'user_id'     => auth()->id(),
            'business_id' => $request->business_id,
            'service_type'=> $request->service_type,
            'service_id'  => $request->service_id,
            'date'        => $request->date,
            'time'        => $request->time,
            'price'       => $request->price,
            'notes'       => $request->notes,
            'status'      => 'pending'
        ]);

        // إرسال إشعار للبزنس
        send_notification(
            $request->business_id,
            "تم استلام حجز جديد",
            "new_booking",
            ["booking_id" => $booking->id]
        );

        return response()->json([
            'status' => true,
            'message' => 'Booking created successfully',
            'data' => $booking
        ]);
    }

    /**
     * تحديث حالة الحجز من قبل البزنس
     */
    public function updateStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'booking_id' => 'required|exists:bookings,id',
            'status'     => 'required|in:pending,accepted,rejected,canceled_by_business,canceled_by_user,completed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $booking = Booking::find($request->booking_id);

        if ($booking->business_id != auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $booking->update(['status' => $request->status]);

        // إشعار للمستخدم
        send_notification(
            $booking->user_id,
            "تم تحديث حالة الحجز إلى: {$request->status}",
            "booking_status",
            ["booking_id" => $booking->id]
        );

        return response()->json([
            'status' => true,
            'message' => 'Status updated successfully',
            'data' => $booking
        ]);
    }

    /**
     * حجوزات المستخدم
     */
    public function myBookings()
    {
        return response()->json([
            'status' => true,
            'data' => Booking::where('user_id', auth()->id())->latest()->get()
        ]);
    }

    /**
     * حجوزات مقدم الخدمة
     */
    public function businessBookings()
    {
        return response()->json([
            'status' => true,
            'data' => Booking::where('business_id', auth()->id())->latest()->get()
        ]);
    }
}
