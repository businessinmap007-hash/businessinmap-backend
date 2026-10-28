<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\BusinessBookingSetting;
use Illuminate\Http\Request;

/**
 * «خطط الأسعار»: beside the flexible rate, a hotel may sell the cheaper «غير قابل للاسترداد» — a percentage off. The guest pays the hotel
 * in full outside the app and cannot move the dates; the app confiscates nothing (a deposit is never forfeited).
 */
final class BusinessRatePlanController extends Controller
{
    use ResolvesOwnerCatalog;

    /** GET /api/v2/business/booking-settings/rate-plans */
    public function show()
    {
        return response()->json(['success' => true, 'data' => [
            'non_refundable_percent' => app(\App\Services\Hotels\StayTerms::class)->nonRefundablePercent($this->businessId()),
        ]]);
    }

    /** PUT /api/v2/business/booking-settings/rate-plans — `{non_refundable_percent}` (null = only the flexible rate) */
    public function update(Request $request)
    {
        $data = $request->validate(['non_refundable_percent' => ['nullable', 'numeric', 'min:1', 'max:50']]);

        BusinessBookingSetting::query()->updateOrCreate(
            ['business_id' => $this->businessId()],
            ['non_refundable_percent' => isset($data['non_refundable_percent']) ? round((float) $data['non_refundable_percent'], 2) : null]
        );

        return $this->show();
    }
}
