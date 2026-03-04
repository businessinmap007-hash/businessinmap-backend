<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ServiceFee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ServiceFeeController extends Controller
{
    /**
     * GET /v1/service-fees
     * Optional: ?codes=delivery_platform_fee,booking_platform_fee
     */
    public function index(Request $request)
    {
        $codes = $request->query('codes');
        $codesArr = [];

        if (is_string($codes) && trim($codes) !== '') {
            $codesArr = array_values(array_filter(array_map('trim', explode(',', $codes))));
        }

        $cacheKey = 'service_fees:index:' . md5(json_encode($codesArr));

        $data = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($codesArr) {
            $q = ServiceFee::query()->where('is_active', 1);

            if (!empty($codesArr)) {
                $q->whereIn('code', $codesArr);
            }

            return $q->orderBy('code')->get([
                'id', 'code', 'amount', 'rules', 'is_active', 'updated_at'
            ]);
        });

        return response()->json([
            'status'  => 200,
            'message' => 'Service fees',
            'data'    => $data,
        ]);
    }

    /**
     * GET /v1/service-fees/{code}
     */
    public function show(string $code)
    {
        $cacheKey = 'service_fees:show:' . $code;

        $fee = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($code) {
            return ServiceFee::where('code', $code)
                ->where('is_active', 1)
                ->first(['id','code','amount','rules','is_active','updated_at']);
        });

        if (!$fee) {
            return response()->json([
                'status'  => 404,
                'message' => 'Service fee not found',
            ], 404);
        }

        return response()->json([
            'status'  => 200,
            'message' => 'Service fee',
            'data'    => $fee,
        ]);
    }
}
