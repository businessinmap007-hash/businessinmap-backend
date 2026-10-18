<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use App\Services\BookingStayDiscountService;
use Illuminate\Http\Request;

/**
 * «خصم الأسبوع والشهر» on a unit let by the period — a car, a flat, a shop, a room.
 */
final class BusinessStayDiscountController extends Controller
{
    use ResolvesOwnerCatalog;

    public function __construct(private readonly BookingStayDiscountService $discounts)
    {
    }

    private function item(int $id): BookableItem
    {
        return BookableItem::query()->where('business_id', $this->businessId())->findOrFail($id);
    }

    /** GET /api/v2/business/bookable-items/{item}/stay-discounts */
    public function show(int $item)
    {
        return response()->json(['success' => true, 'data' => ['stay_discounts' => $this->discounts->settings($this->item($item))]]);
    }

    /** PUT /api/v2/business/bookable-items/{item}/stay-discounts — `{weekly_percent, monthly_percent}` */
    public function update(Request $request, int $item)
    {
        $data = $request->validate([
            'weekly_percent' => ['nullable', 'numeric', 'min:0', 'max:' . BookingStayDiscountService::MAX_PERCENT],
            'monthly_percent' => ['nullable', 'numeric', 'min:0', 'max:' . BookingStayDiscountService::MAX_PERCENT],
        ]);

        return response()->json(['success' => true, 'data' => ['stay_discounts' => $this->discounts->save(
            $this->item($item),
            (float) ($data['weekly_percent'] ?? 0),
            (float) ($data['monthly_percent'] ?? 0),
        )]]);
    }
}
