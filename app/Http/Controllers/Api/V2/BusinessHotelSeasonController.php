<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use App\Models\HotelSeason;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * «المواسم»: a hotel writes its seasons — the dates, the rooms' price in them (a % or an amount on every room type, or a night price
 * for a type), the meal plans' price in them, and the shortest stay that may arrive in them. A room type's own price rule still wins.
 */
final class BusinessHotelSeasonController extends Controller
{
    use ResolvesOwnerCatalog;

    /** GET /api/v2/business/seasons — every season, and what the form needs: the room types and the meal plans the hotel sells */
    public function index()
    {
        $biz = $this->businessId();

        return response()->json(['success' => true, 'data' => [
            'seasons' => HotelSeason::query()->where('business_id', $biz)->orderBy('start_date')->get()->map(fn (HotelSeason $s) => $s->payload())->all(),
            'room_types' => BookableItem::query()->where('business_id', $biz)->where('item_type', 'booking_stay')->orderBy('id')->get()
                ->map(fn (BookableItem $i) => ['id' => (int) $i->id, 'title' => $i->title ?: $i->code])->all(),
            'meal_plans' => app(\App\Services\Hotels\MealPlans::class)->offered($biz),
        ]]);
    }

    public function store(Request $request)
    {
        $season = HotelSeason::create($this->validated($request) + ['business_id' => $this->businessId()]);

        return response()->json(['success' => true, 'data' => ['season' => $season->payload()]], 201);
    }

    public function update(Request $request, int $id)
    {
        $season = $this->mine($id);
        $season->update($this->validated($request, $season->id));

        return response()->json(['success' => true, 'data' => ['season' => $season->fresh()->payload()]]);
    }

    public function destroy(int $id)
    {
        $this->mine($id)->delete();

        return response()->json(['success' => true]);
    }

    private function mine(int $id): HotelSeason
    {
        return HotelSeason::query()->where('business_id', $this->businessId())->findOrFail($id);
    }

    /** @return array<string,mixed> */
    private function validated(Request $request, ?int $except = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'room_change_type' => ['nullable', Rule::in([HotelSeason::CHANGE_PERCENT, HotelSeason::CHANGE_AMOUNT])],
            'room_change_value' => ['nullable', 'numeric', 'min:-100000', 'max:100000'],
            'room_prices' => ['nullable', 'array'],
            'room_prices.*' => ['nullable', 'numeric', 'min:0'],
            'meal_prices' => ['nullable', 'array'],
            'meal_prices.*' => ['nullable', 'numeric', 'min:0'],
            'min_nights' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $biz = $this->businessId();

        if (($data['room_change_type'] ?? null) === HotelSeason::CHANGE_PERCENT && (float) ($data['room_change_value'] ?? 0) < -100) {
            throw ValidationException::withMessages(['room_change_value' => __('النسبة لا تقل عن -100%.')]);
        }

        // one season a day: two seasons on the same night would leave the night's price to chance
        $overlap = HotelSeason::query()->where('business_id', $biz)
            ->when($except, fn ($q) => $q->whereKeyNot($except))
            ->whereDate('start_date', '<=', $data['end_date'])->whereDate('end_date', '>=', $data['start_date'])->exists();

        if ($overlap) {
            throw ValidationException::withMessages(['start_date' => __('هذه التواريخ تتداخل مع موسم آخر.')]);
        }

        // a price for a room type is for one of THIS hotel's room types; a meal price for a plan it sells
        $types = BookableItem::query()->where('business_id', $biz)->where('item_type', 'booking_stay')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $plans = array_map(fn ($p) => $p['option_id'], app(\App\Services\Hotels\MealPlans::class)->offered($biz));
        $clean = function (?array $prices, array $allowed, string $field): ?array {
            $out = [];

            foreach ((array) $prices as $id => $price) {
                if ($price === null || $price === '') {
                    continue;
                }

                if (! in_array((int) $id, $allowed, true)) {
                    throw ValidationException::withMessages([$field => __('سعر لعنصر لا يخص هذا الفندق.')]);
                }

                $out[(string) (int) $id] = round((float) $price, 2);
            }

            return $out === [] ? null : $out;
        };

        return [
            'name' => trim($data['name']),
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'room_change_type' => isset($data['room_change_value']) ? ($data['room_change_type'] ?? HotelSeason::CHANGE_PERCENT) : null,
            'room_change_value' => isset($data['room_change_value']) ? round((float) $data['room_change_value'], 2) : null,
            'room_prices' => $clean($data['room_prices'] ?? null, $types, 'room_prices'),
            'meal_prices' => $clean($data['meal_prices'] ?? null, $plans, 'meal_prices'),
            'min_nights' => $data['min_nights'] ?? null,
        ];
    }
}
