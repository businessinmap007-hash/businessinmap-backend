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
                'days' => $week,
            ],
        ]);
    }

    /** PUT /api/v2/business/working-hours — replace my week. */
    public function update(Request $request)
    {
        $data = $request->validate([
            'days' => ['required', 'array', 'min:1', 'max:7'],
            'days.*.day' => ['required', 'integer', 'between:0,6'],
            'days.*.is_closed' => ['nullable', 'boolean'],
            'days.*.open' => ['nullable', 'date_format:H:i'],
            'days.*.close' => ['nullable', 'date_format:H:i'],
        ]);

        $this->hours->save((int) $request->user()->id, $data['days']);

        return $this->show($request);
    }
}
