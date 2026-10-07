<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use App\Services\BookingDayUseService;
use Illuminate\Http\Request;

/**
 * «خدمة Day use» — a hotel room type also sold through the day: a window, a flat price.
 */
final class BusinessDayUseController extends Controller
{
    use ResolvesOwnerCatalog;

    public function __construct(private readonly BookingDayUseService $dayUse)
    {
    }

    private function item(int $id): BookableItem
    {
        return BookableItem::query()->where('business_id', $this->businessId())->findOrFail($id);
    }

    /** GET /api/v2/business/bookable-items/{item}/day-use */
    public function show(int $item)
    {
        return response()->json(['success' => true, 'data' => ['day_use' => $this->dayUse->settings($this->item($item))]]);
    }

    /** PUT /api/v2/business/bookable-items/{item}/day-use — `{enabled, from: "09:00", to: "18:00", price}` */
    public function update(Request $request, int $item)
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'from' => ['required', 'date_format:H:i'],
            'to' => ['required', 'date_format:H:i'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
        ]);

        return response()->json(['success' => true, 'data' => ['day_use' => $this->dayUse->save(
            $this->item($item),
            (bool) $data['enabled'],
            $data['from'],
            $data['to'],
            isset($data['price']) ? (float) $data['price'] : null,
        )]]);
    }
}
