<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Services\Hotels\MealPlans;
use Illuminate\Http\Request;

/** «مواعيد الوجبات»: breakfast, lunch and dinner, each from … to …, which the guest reads on his booking for the plan he chose. */
final class BusinessMealTimesController extends Controller
{
    use ResolvesOwnerCatalog;

    public function __construct(private readonly MealPlans $plans)
    {
    }

    /** GET /api/v2/business/meal-times — null until written */
    public function show()
    {
        return response()->json(['success' => true, 'data' => ['times' => $this->plans->times($this->businessId())]]);
    }

    /** PUT /api/v2/business/meal-times — `{breakfast:{from:"07:00",to:"10:30"}, lunch:{…}, dinner:{…}}` (a meal may be left empty) */
    public function update(Request $request)
    {
        $rules = [];

        foreach (MealPlans::MEALS as $meal) {
            $rules[$meal] = ['nullable', 'array'];
            $rules[$meal . '.from'] = ['nullable', 'date_format:H:i'];
            $rules[$meal . '.to'] = ['nullable', 'date_format:H:i'];
        }

        $data = $request->validate($rules);
        $this->plans->saveTimes($this->businessId(), $data);

        return response()->json(['success' => true, 'data' => ['times' => $this->plans->times($this->businessId())]]);
    }
}
