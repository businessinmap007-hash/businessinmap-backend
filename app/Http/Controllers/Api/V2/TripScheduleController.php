<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\TripSchedule;
use App\Services\Schedules\TripScheduleService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Scheduling / routes service API. Businesses publish their trip legs; customers
 * search a route + day and get carriers ranked by trust. See TripScheduleService
 * for the search/ranking and the create_trip_schedules migration for the domain.
 */
final class TripScheduleController extends Controller
{
    /**
     * Public search: "who moves Cairo→Damietta on Sunday", ranked by trust.
     * Requires origin + destination governorate; day comes from ?date=YYYY-MM-DD
     * or ?day_of_week=0..6 (omit both to list the whole route).
     */
    public function search(Request $request, TripScheduleService $service)
    {
        $filters = $request->validate([
            'origin_governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'destination_governorate_id' => ['required', 'integer', 'exists:governorates,id'],
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
        $data = $this->validatedData($request, (int) $business->id);
        $data['business_id'] = (int) $business->id;

        $schedule = TripSchedule::create($data);

        return response()->json([
            'success' => true,
            'message' => 'تم نشر خط التشغيل بنجاح.',
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

        $data = $this->validatedData($request, (int) $business->id, $row);
        $row->update($data);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث خط التشغيل.',
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

        return response()->json(['success' => true, 'message' => 'تم حذف خط التشغيل.']);
    }

    private function businessOrFail(Request $request)
    {
        $user = $request->user();

        if (! $user || (string) $user->type !== 'business') {
            abort(response()->json([
                'success' => false,
                'message' => 'هذه الخدمة متاحة لحسابات الأعمال فقط.',
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

    private function validatedData(Request $request, int $businessId, ?TripSchedule $existing = null): array
    {
        $pattern = (string) $request->input('schedule_pattern', $existing->schedule_pattern ?? TripSchedule::PATTERN_WEEKLY);

        $data = $request->validate([
            'mode' => ['required', Rule::in(TripSchedule::modes())],
            'origin_governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'origin_city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'destination_governorate_id' => ['required', 'integer', 'exists:governorates,id', 'different:origin_governorate_id'],
            'destination_city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'schedule_pattern' => ['required', Rule::in(TripSchedule::patterns())],
            'day_of_week' => [Rule::requiredIf($pattern === TripSchedule::PATTERN_WEEKLY), 'nullable', 'integer', 'between:0,6'],
            'trip_date' => [Rule::requiredIf($pattern === TripSchedule::PATTERN_ONE_OFF), 'nullable', 'date'],
            'departure_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'return_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'capacity_unit' => ['nullable', 'string', 'max:24'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'is_return_leg' => ['nullable', 'boolean'],
            'parent_trip_id' => ['nullable', 'integer', 'exists:trip_schedules,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in([
                TripSchedule::STATUS_ACTIVE,
                TripSchedule::STATUS_PAUSED,
                TripSchedule::STATUS_EXPIRED,
                TripSchedule::STATUS_CANCELLED,
            ])],
        ]);

        // A return leg may only hang off a parent trip the same business owns.
        if (! empty($data['parent_trip_id'])) {
            $ownsParent = TripSchedule::query()
                ->where('id', (int) $data['parent_trip_id'])
                ->where('business_id', $businessId)
                ->exists();

            if (! $ownsParent) {
                throw ValidationException::withMessages([
                    'parent_trip_id' => 'الرحلة الأصلية غير موجودة أو ليست ملكك.',
                ]);
            }
        }

        $data['is_return_leg'] = $request->boolean('is_return_leg', (bool) ($existing->is_return_leg ?? false));
        $data['currency'] = (string) ($data['currency'] ?? ($existing->currency ?? 'EGP'));
        $data['status'] = (string) ($data['status'] ?? ($existing->status ?? TripSchedule::STATUS_ACTIVE));

        return $data;
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
            'origin' => [
                'governorate_id' => (int) $s->origin_governorate_id,
                'governorate' => optional($s->originGovernorate)->name_ar,
                'city_id' => $s->origin_city_id ? (int) $s->origin_city_id : null,
            ],
            'destination' => [
                'governorate_id' => (int) $s->destination_governorate_id,
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
            'currency' => (string) $s->currency,
            'is_return_leg' => (bool) $s->is_return_leg,
            'parent_trip_id' => $s->parent_trip_id ? (int) $s->parent_trip_id : null,
            'notes' => $s->notes,
            'status' => (string) $s->status,
        ];
    }
}
