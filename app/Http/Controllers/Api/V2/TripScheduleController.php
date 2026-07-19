<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\PlatformService;
use App\Models\PlatformServiceItemType;
use App\Models\TripSchedule;
use App\Services\Schedules\TripScheduleService;
use App\Services\Schedules\TripScheduleValidator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Scheduling / routes service API. Businesses publish their trip legs; customers
 * search a route + day and get carriers ranked by trust. See TripScheduleService
 * for the search/ranking and the create_trip_schedules migration for the domain.
 */
final class TripScheduleController extends Controller
{
    public function __construct(private readonly TripScheduleValidator $validator) {}

    /**
     * Public search: "who moves Cairo→Damietta on Sunday", ranked by trust.
     * Requires origin + destination governorate; day comes from ?date=YYYY-MM-DD
     * or ?day_of_week=0..6 (omit both to list the whole route).
     */
    public function search(Request $request, TripScheduleService $service)
    {
        $filters = $request->validate([
            // Anchor by governorate pair (domestic) OR country pair (international).
            'origin_governorate_id' => ['nullable', 'integer', 'exists:governorates,id', 'required_without:origin_country_id'],
            'destination_governorate_id' => ['nullable', 'integer', 'exists:governorates,id', 'required_without:destination_country_id'],
            'origin_country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'destination_country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'origin_city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'destination_city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'scope' => ['nullable', Rule::in(TripSchedule::scopes())],
            'vehicle_type_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date'],
            'day_of_week' => ['nullable', 'integer', 'between:0,6'],
            'mode' => ['nullable', Rule::in(TripSchedule::modes())],
        ]);

        $results = $service->search($filters)->map(fn (array $row) => [
            'schedule' => $this->serialize($row['schedule']),
            'trust' => $row['trust'],
            'remaining_capacity' => $row['remaining_capacity'],
        ]);

        return response()->json([
            'success' => true,
            'data' => ['results' => $results, 'count' => $results->count()],
        ]);
    }

    /**
     * The platform-standard vehicle/cargo classes for the scheduling service —
     * the picker for both the carrier's publish form and the customer's filter.
     * Optional ?mode= narrows to one trip mode.
     */
    public function vehicleTypes(Request $request)
    {
        $service = PlatformService::query()->where('key', PlatformService::KEY_SCHEDULES)->first();

        if (! $service) {
            return response()->json(['success' => true, 'data' => ['vehicle_types' => []]]);
        }

        $mode = trim((string) $request->get('mode', ''));
        $group = trim((string) $request->get('group', ''));

        $types = PlatformServiceItemType::query()
            ->forService((int) $service->id)
            ->active()
            ->with('groups:id,key,name_ar,name_en')
            ->ordered()
            ->get()
            ->filter(fn (PlatformServiceItemType $t) => $mode === '' || (string) data_get($t->meta, 'mode') === $mode)
            ->filter(fn (PlatformServiceItemType $t) => $group === '' || $t->groups->contains('key', $group))
            ->map(function (PlatformServiceItemType $t) {
                $g = $t->groups->first();

                return [
                    'id' => (int) $t->id,
                    'key' => (string) $t->key,
                    'name' => $t->displayName(),
                    'mode' => data_get($t->meta, 'mode'),
                    'scope' => data_get($t->meta, 'scope'),
                    'default_unit' => data_get($t->meta, 'default_unit'),
                    'is_default' => (bool) $t->is_default,
                    'group' => $g ? ['id' => (int) $g->id, 'key' => (string) $g->key, 'name' => $g->displayName()] : null,
                ];
            })
            ->values();

        return response()->json(['success' => true, 'data' => ['vehicle_types' => $types]]);
    }

    /*
     * countries() lived here and now lives in Api\V2\LocationController: the
     * same list was being built in two places. GET /api/v2/schedules/countries
     * still works — the route points at that controller instead.
     */

    /** The calling business's own published schedules. */
    public function index(Request $request)
    {
        $business = $this->businessOrFail($request);

        $schedules = TripSchedule::query()
            ->where('business_id', (int) $business->id)
            ->with(['originGovernorate:id,name_ar,name_en', 'destinationGovernorate:id,name_ar,name_en'])
            ->latest('id')
            ->paginate((int) $request->get('per_page', 20));

        $schedules->getCollection()->transform(fn (TripSchedule $s) => $this->serialize($s));

        return response()->json(['success' => true, 'data' => $schedules]);
    }

    public function store(Request $request)
    {
        $business = $this->businessOrFail($request);
        $data = $this->validator->validated($request, (int) $business->id);
        $data['business_id'] = (int) $business->id;

        $schedule = TripSchedule::create($data);

        return response()->json([
            'success' => true,
            'message' => __('تم نشر خط التشغيل بنجاح.'),
            'data' => ['schedule' => $this->serialize($schedule->fresh([
                'originGovernorate:id,name_ar,name_en',
                'destinationGovernorate:id,name_ar,name_en',
            ]))],
        ], 201);
    }

    public function update(Request $request, int $schedule)
    {
        $business = $this->businessOrFail($request);
        $row = $this->ownedOrFail((int) $business->id, $schedule);

        $data = $this->validator->validated($request, (int) $business->id, $row);
        $row->update($data);

        return response()->json([
            'success' => true,
            'message' => __('تم تحديث خط التشغيل.'),
            'data' => ['schedule' => $this->serialize($row->fresh([
                'originGovernorate:id,name_ar,name_en',
                'destinationGovernorate:id,name_ar,name_en',
            ]))],
        ]);
    }

    public function destroy(Request $request, int $schedule)
    {
        $business = $this->businessOrFail($request);
        $row = $this->ownedOrFail((int) $business->id, $schedule);

        $row->delete();

        return response()->json(['success' => true, 'message' => __('تم حذف خط التشغيل.')]);
    }

    private function businessOrFail(Request $request)
    {
        $user = $request->user();

        if (! $user || (string) $user->type !== 'business') {
            abort(response()->json([
                'success' => false,
                'message' => __('هذه الخدمة متاحة لحسابات الأعمال فقط.'),
            ], 403));
        }

        return $user;
    }

    private function ownedOrFail(int $businessId, int $scheduleId): TripSchedule
    {
        return TripSchedule::query()
            ->where('id', $scheduleId)
            ->where('business_id', $businessId)
            ->firstOrFail();
    }

    private function serialize(TripSchedule $s): array
    {
        return [
            'id' => (int) $s->id,
            'business_id' => (int) $s->business_id,
            'business' => $s->relationLoaded('business') && $s->business ? [
                'id' => (int) $s->business->id,
                'name' => $s->business->name,
                'logo' => $s->business->logo,
            ] : null,
            'mode' => (string) $s->mode,
            'vehicle_type' => $s->vehicle_type_id ? [
                'id' => (int) $s->vehicle_type_id,
                'key' => optional($s->vehicleType)->key,
                'name' => $s->relationLoaded('vehicleType') && $s->vehicleType ? $s->vehicleType->displayName() : null,
            ] : null,
            'vehicle_label' => $s->vehicle_label,
            'scope' => (string) $s->scope,
            'origin' => [
                'country_id' => $s->origin_country_id ? (int) $s->origin_country_id : null,
                'country' => optional($s->originCountry)->name_ar,
                'governorate_id' => $s->origin_governorate_id ? (int) $s->origin_governorate_id : null,
                'governorate' => optional($s->originGovernorate)->name_ar,
                'city_id' => $s->origin_city_id ? (int) $s->origin_city_id : null,
            ],
            'destination' => [
                'country_id' => $s->destination_country_id ? (int) $s->destination_country_id : null,
                'country' => optional($s->destinationCountry)->name_ar,
                'governorate_id' => $s->destination_governorate_id ? (int) $s->destination_governorate_id : null,
                'governorate' => optional($s->destinationGovernorate)->name_ar,
                'city_id' => $s->destination_city_id ? (int) $s->destination_city_id : null,
            ],
            'schedule_pattern' => (string) $s->schedule_pattern,
            'day_of_week' => $s->day_of_week !== null ? (int) $s->day_of_week : null,
            'trip_date' => optional($s->trip_date)->toDateString(),
            'departure_time' => $s->departure_time,
            'return_time' => $s->return_time,
            'capacity' => $s->capacity !== null ? (int) $s->capacity : null,
            'capacity_unit' => $s->capacity_unit,
            'price' => $s->price !== null ? (float) $s->price : null,
            'deposit_per_unit' => $s->deposit_per_unit !== null ? (float) $s->deposit_per_unit : null,
            'currency' => (string) $s->currency,
            'is_return_leg' => (bool) $s->is_return_leg,
            'parent_trip_id' => $s->parent_trip_id ? (int) $s->parent_trip_id : null,
            'notes' => $s->notes,
            'status' => (string) $s->status,
        ];
    }
}
