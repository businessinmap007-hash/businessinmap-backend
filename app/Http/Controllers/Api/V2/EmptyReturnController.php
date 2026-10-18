<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\TripSchedule;
use App\Services\Schedules\EmptyReturnService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * «راجع فاضي»: a limousine driver offers the way home at a lower price; the customer who asks for that very ride is
 * shown the offers and books one with the ordinary trip reservation (POST schedules/{id}/reserve). See
 * App\Services\Schedules\EmptyReturnService.
 */
final class EmptyReturnController extends Controller
{
    public function __construct(private readonly EmptyReturnService $service) {}

    /** GET empty-returns/quote — the reference fare and the price a return starts from. */
    public function quote(Request $request)
    {
        $data = Validator::make($request->all(), [
            'origin_city_id' => ['required', 'integer', 'exists:cities,id'],
            'destination_city_id' => ['required', 'integer', 'exists:cities,id', 'different:origin_city_id'],
            'distance_km' => ['nullable', 'numeric', 'min:0.3', 'max:3000'],
        ])->validate();

        $quote = $this->service->quote((int) $data['origin_city_id'], (int) $data['destination_city_id'], isset($data['distance_km']) ? (float) $data['distance_km'] : null);

        return response()->json(['success' => true, 'data' => [
            'known' => $quote !== null,
            'needs_distance' => $quote === null,
            'distance_km' => $quote['distance_km'] ?? null,
            'reference_price' => $quote['reference_price'] ?? null,
            'suggested_price' => $quote['suggested_price'] ?? null,
            'currency' => 'EGP',
        ]]);
    }

    /** GET empty-returns?origin_city_id&destination_city_id[&date] — what a customer going that way is offered. */
    public function index(Request $request)
    {
        $data = Validator::make($request->all(), [
            'origin_city_id' => ['required', 'integer', 'exists:cities,id'],
            'destination_city_id' => ['required', 'integer', 'exists:cities,id'],
            'date' => ['nullable', 'date'],
        ])->validate();

        $rows = $this->service->search((int) $data['origin_city_id'], (int) $data['destination_city_id'], $data['date'] ?? null)
            ->map(fn (array $r) => $this->serialize($r['schedule'], $r['remaining'], $r['exact'], $r['trust']));

        return response()->json(['success' => true, 'data' => ['results' => $rows, 'count' => $rows->count()]]);
    }

    /** GET business/empty-returns/suggestions — the driver's own rides, each with its way back worked out. */
    public function suggestions(Request $request)
    {
        $rows = $this->service->suggestions($this->driver($request));

        return response()->json(['success' => true, 'data' => ['results' => $rows, 'count' => $rows->count()]]);
    }

    /** GET business/empty-returns — the driver's own offers. */
    public function mine(Request $request)
    {
        $driver = $this->driver($request);
        $rows = $this->service->mine($driver)->map(fn (TripSchedule $s) => $this->serialize($s, null, null, []));

        return response()->json(['success' => true, 'data' => ['results' => $rows, 'count' => $rows->count()]]);
    }

    /** POST business/empty-returns — {origin_city_id, destination_city_id, departs_at, price, seats?, distance_km?, notes?, vehicle_label?} */
    public function store(Request $request)
    {
        $driver = $this->driver($request);
        $data = Validator::make($request->all(), [
            'origin_city_id' => ['required', 'integer', 'exists:cities,id'],
            'destination_city_id' => ['required', 'integer', 'exists:cities,id'],
            'departs_at' => ['required', 'date'],
            'price' => ['required', 'numeric', 'min:1', 'max:100000'],
            'seats' => ['nullable', 'integer', 'min:1', 'max:30'],
            'distance_km' => ['nullable', 'numeric', 'min:0.3', 'max:3000'],
            'notes' => ['nullable', 'string', 'max:500'],
            'vehicle_label' => ['nullable', 'string', 'max:120'],
            // the way back OF one of his own rides (see suggestions)
            'from_booking_id' => ['nullable', 'integer'],
        ])->validate();

        $leg = $this->service->publish($driver, $data);

        return response()->json([
            'success' => true,
            'message' => __('تم نشر «راجع فاضي».'),
            'data' => ['empty_return' => $this->serialize($leg->load(['originCity:id,name_ar,name_en', 'destinationCity:id,name_ar,name_en']), 1, null, [])],
        ], 201);
    }

    /** DELETE business/empty-returns/{id} — takes the offer down while nobody holds it. */
    public function destroy(Request $request, int $id)
    {
        $this->service->cancel($this->driver($request), $id);

        return response()->json(['success' => true, 'message' => __('تم إلغاء العرض.')]);
    }

    private function driver(Request $request)
    {
        $business = BusinessContext::business($request);

        if (! $business || ! $business->isBusiness()) {
            abort(response()->json(['success' => false, 'message' => __('هذه الخدمة متاحة لحسابات الأعمال فقط.')], 403));
        }

        return $business;
    }

    /** @param  array<string,mixed>  $trust */
    private function serialize(TripSchedule $s, ?int $remaining, ?bool $exact, array $trust): array
    {
        $reference = (float) data_get($s->meta, 'reference_price', 0);
        $price = (float) $s->price;
        $departs = $this->service->departsAt($s);

        return [
            'id' => (int) $s->id,
            'status' => (string) $s->status,
            'driver' => $s->relationLoaded('business') && $s->business ? [
                'id' => (int) $s->business->id,
                'name' => $s->business->name,
                'logo' => $s->business->logo,
            ] : null,
            'origin' => [
                'governorate_id' => (int) $s->origin_governorate_id,
                'city_id' => $s->origin_city_id ? (int) $s->origin_city_id : null,
                'city' => $s->relationLoaded('originCity') ? $s->originCity?->loc('name') : null,
            ],
            'destination' => [
                'governorate_id' => (int) $s->destination_governorate_id,
                'city_id' => $s->destination_city_id ? (int) $s->destination_city_id : null,
                'city' => $s->relationLoaded('destinationCity') ? $s->destinationCity?->loc('name') : null,
            ],
            // a UTC instant, like every date the API returns
            'departs_at' => $departs?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'price' => $price,
            'reference_price' => $reference > 0 ? $reference : null,
            'saving_percent' => $reference > 0 && $price < $reference ? (int) round((1 - $price / $reference) * 100) : null,
            'currency' => (string) $s->currency,
            'seats' => (int) data_get($s->meta, 'seats', 4),
            'distance_km' => data_get($s->meta, 'distance_km'),
            'vehicle_label' => $s->vehicle_label,
            'notes' => $s->notes,
            'is_ahead' => $this->service->isAhead($s),
            'remaining' => $remaining,
            'exact_match' => $exact,
            'trust' => $trust === [] ? null : [
                'has_active_guarantee' => (bool) ($trust['has_active_guarantee'] ?? false),
                'success_rate' => (float) ($trust['success_rate'] ?? 0),
                'stars_average' => (float) ($trust['stars_average'] ?? 0),
                'review_count' => (int) ($trust['review_count'] ?? 0),
                'total_operations' => (int) ($trust['total_operations'] ?? 0),
            ],
        ];
    }
}
