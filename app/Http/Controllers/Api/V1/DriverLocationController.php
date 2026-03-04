<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DriverLocationController extends Controller
{
    public function __construct()
    {
        $language = request()->headers->get('lang') ?: 'ar';
        app()->setLocale($language);
    }

    /**
     * تحديث موقع السائق الحالي
     * يستخدمها courier من الموبايل كل X ثواني
     */
    public function update(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $driver = $request->user(); // السائق الحالي

        // لو الجدول عندك اسمه driver_id بدلاً من courier_id
        // غيّر courier_id إلى driver_id في السطرين دول
        $existing = DB::table('driver_locations')
            ->where('courier_id', $driver->id)
            ->first();

        if ($existing) {
            DB::table('driver_locations')
                ->where('courier_id', $driver->id)
                ->update([
                    'lat'        => $request->lat,
                    'lng'        => $request->lng,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('driver_locations')->insert([
                'courier_id' => $driver->id,
                'lat'        => $request->lat,
                'lng'        => $request->lng,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'status'  => 200,
            'message' => 'Driver location updated',
        ]);
    }

    /**
     * جلب موقع سائق معيّن
     * تستخدمها شاشة العميل / البزنس لتتبع السائق
     */
    public function show($courier_id)
    {
        // لو العمود عندك اسمه driver_id: غيّر courier_id إلى driver_id هنا
        $location = DB::table('driver_locations')
            ->where('courier_id', $courier_id)
            ->first();

        if (! $location) {
            return response()->json([
                'status'  => 404,
                'message' => 'Driver location not found',
            ], 404);
        }

        return response()->json([
            'status'   => 200,
            'message'  => 'Driver location',
            'location' => $location,
        ]);
    }
}
