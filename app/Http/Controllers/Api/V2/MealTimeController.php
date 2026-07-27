<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\MealSchedule;
use Illuminate\Http\Request;

/**
 * A patient's meal times, off which food-tied medication doses are scheduled.
 */
class MealTimeController extends Controller
{
    /** GET /api/v2/me/meal-times — my meal times (defaults if unset). */
    public function show(Request $request)
    {
        $row = MealSchedule::query()->firstOrNew(['user_id' => (int) $request->user()->id]);

        return response()->json(['success' => true, 'data' => ['meal_times' => $this->serialize($row)]]);
    }

    /** PUT /api/v2/me/meal-times — set my meal times. */
    public function update(Request $request)
    {
        $data = $request->validate([
            'breakfast_at' => ['required', 'date_format:H:i'],
            'lunch_at' => ['required', 'date_format:H:i'],
            'dinner_at' => ['required', 'date_format:H:i'],
        ]);

        $row = MealSchedule::query()->updateOrCreate(
            ['user_id' => (int) $request->user()->id],
            [
                'breakfast_at' => $data['breakfast_at'],
                'lunch_at' => $data['lunch_at'],
                'dinner_at' => $data['dinner_at'],
            ],
        );

        return response()->json([
            'success' => true,
            'message' => __('تم حفظ مواعيد الوجبات.'),
            'data' => ['meal_times' => $this->serialize($row)],
        ]);
    }

    private function serialize(MealSchedule $row): array
    {
        return [
            'breakfast_at' => substr((string) ($row->breakfast_at ?? MealSchedule::DEFAULTS['breakfast_at']), 0, 5),
            'lunch_at' => substr((string) ($row->lunch_at ?? MealSchedule::DEFAULTS['lunch_at']), 0, 5),
            'dinner_at' => substr((string) ($row->dinner_at ?? MealSchedule::DEFAULTS['dinner_at']), 0, 5),
        ];
    }
}
