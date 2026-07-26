<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Services\BusinessHoursService;
use Illuminate\Http\Request;

/**
 * A business sets and reads its own weekly opening hours. The `business`
 * middleware guarantees the caller is a business, so these always act on the
 * caller's own account.
 */
class BusinessHoursController extends Controller
{
    public function __construct(private readonly BusinessHoursService $hours)
    {
    }

    /** GET /api/v2/business/working-hours — my week + whether I'm open now. */
    public function show(Request $request)
    {
        $businessId = (int) $request->user()->id;
        $rows = $this->hours->hoursFor($businessId);

        $week = collect(BusinessHoursService::DAYS)->map(function (int $day) use ($rows) {
            $row = $rows->get($day);

            return [
                'day' => $day,
                'is_closed' => $row ? (bool) $row->is_closed : null,
                'open' => $row && $row->open_time ? substr((string) $row->open_time, 0, 5) : null,
                'close' => $row && $row->close_time ? substr((string) $row->close_time, 0, 5) : null,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'is_open_now' => $this->hours->isOpenNow($businessId),
                'timezone' => $this->hours->timezoneFor($businessId),
                'days' => $week,
            ],
        ]);
    }

    /**
     * PUT /api/v2/business/working-hours — set my week.
     *
     * Two ways, combinable: `bulk` applies ONE open/close (or closed) across
     * many days at once — `all: true` for the whole week, or `days: [..]` —
     * and `days[]` sets individual days. When both are sent, bulk is applied
     * first and the per-day entries override it.
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'days' => ['nullable', 'array', 'max:7'],
            'days.*.day' => ['required_with:days', 'integer', 'between:0,6'],
            'days.*.is_closed' => ['nullable', 'boolean'],
            'days.*.open' => ['nullable', 'date_format:H:i'],
            'days.*.close' => ['nullable', 'date_format:H:i'],

            'bulk' => ['nullable', 'array'],
            'bulk.all' => ['nullable', 'boolean'],
            'bulk.days' => ['nullable', 'array'],
            'bulk.days.*' => ['integer', 'between:0,6'],
            'bulk.is_closed' => ['nullable', 'boolean'],
            'bulk.open' => ['nullable', 'date_format:H:i'],
            'bulk.close' => ['nullable', 'date_format:H:i'],

            'timezone' => ['nullable', 'timezone'],
        ]);

        // The shop's own timezone, so its hours are judged where it is.
        if ($request->has('timezone')) {
            $request->user()->forceFill(['timezone' => $data['timezone'] ?? null])->save();
        }

        $entries = [];

        if (! empty($data['bulk'])) {
            $bulk = $data['bulk'];
            $days = ! empty($bulk['all'])
                ? BusinessHoursService::DAYS
                : array_values(array_unique(array_map('intval', $bulk['days'] ?? [])));

            foreach ($days as $day) {
                $entries[] = [
                    'day' => (int) $day,
                    'is_closed' => (bool) ($bulk['is_closed'] ?? false),
                    'open' => $bulk['open'] ?? null,
                    'close' => $bulk['close'] ?? null,
                ];
            }
        }

        // Per-day entries come after, so they win over the bulk defaults.
        foreach ($data['days'] ?? [] as $entry) {
            $entries[] = $entry;
        }

        if ($entries === [] && ! $request->has('timezone')) {
            return response()->json([
                'success' => false,
                'message' => __('حدّد المواعيد عبر days أو bulk.'),
            ], 422);
        }

        if ($entries !== []) {
            $this->hours->save((int) $request->user()->id, $entries);
        }

        return $this->show($request);
    }
}
