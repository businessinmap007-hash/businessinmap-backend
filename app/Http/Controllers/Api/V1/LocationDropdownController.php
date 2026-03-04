<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\City;

class LocationDropdownController extends Controller
{
    public function countries()
    {
        return response()->json([
            'data' => Country::query()
                ->select('id', 'name_ar', 'name_en', 'iso2')
                ->orderBy('name_ar')
                ->get()
        ]);
    }

    public function governorates(Request $request)
    {
        $countryId = (int) $request->query('country_id', 1); // مصر افتراضيًا = 1

        return response()->json([
            'data' => Governorate::query()
                ->select('id', 'country_id', 'name_ar', 'name_en')
                ->where('country_id', $countryId)
                ->orderBy('name_ar')
                ->get()
        ]);
    }

    public function cities(Request $request)
    {
        $govId = (int) $request->query('governorate_id');

        if (!$govId) {
            return response()->json([
                'message' => 'governorate_id is required'
            ], 422);
        }

        return response()->json([
            'data' => City::query()
                ->select('id', 'governorate_id', 'name_ar', 'name_en', 'latitude', 'longitude')
                ->where('governorate_id', $govId)
                ->orderBy('name_ar')
                ->get()
        ]);
    }

    // بحث أثناء الكتابة (Dropdown Search)
    public function searchCities(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $govId = (int) $request->query('governorate_id', 0);

        if ($q === '') {
            return response()->json(['data' => []]);
        }

        $query = City::query()
            ->select('id', 'governorate_id', 'name_ar', 'name_en', 'latitude', 'longitude')
            ->where(function ($w) use ($q) {
                $w->where('name_ar', 'like', "%{$q}%")
                  ->orWhere('name_en', 'like', "%{$q}%");
            });

        if ($govId > 0) {
            $query->where('governorate_id', $govId);
        }

        return response()->json([
            'data' => $query->limit(30)->get()
        ]);
    }
}
