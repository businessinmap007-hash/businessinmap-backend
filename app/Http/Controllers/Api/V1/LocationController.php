<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Support\Facades\Cache;

class LocationController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Countries
    |--------------------------------------------------------------------------
    */
    public function countries()
    {
        $countries = Cache::remember('locations_countries', 86400, function () {
            return Location::where('type', 'country')
                ->select('id', 'code', 'name_ar', 'name_en')
                ->orderBy('name_ar')
                ->get();
        });

        return response()->json($countries, 200, [], JSON_UNESCAPED_UNICODE);
    }

    /*
    |--------------------------------------------------------------------------
    | Governorates by country code
    |--------------------------------------------------------------------------
    */
    public function governorates(string $countryCode)
    {
        $cacheKey = "locations_governorates_{$countryCode}";

        $governorates = Cache::remember($cacheKey, 86400, function () use ($countryCode) {

            $country = Location::where('type', 'country')
                ->where('code', $countryCode)
                ->firstOrFail();

            return Location::where('type', 'governorate')
                ->where('parent_id', $country->id)
                ->select('id', 'code', 'name_ar', 'name_en')
                ->orderBy('name_ar')
                ->get();
        });

        return response()->json($governorates, 200, [], JSON_UNESCAPED_UNICODE);
    }

    /*
    |--------------------------------------------------------------------------
    | Cities by governorate code
    |--------------------------------------------------------------------------
    */
    public function cities(string $governorateCode)
    {
        $cacheKey = "locations_cities_{$governorateCode}";

        $cities = Cache::remember($cacheKey, 86400, function () use ($governorateCode) {
            return Location::where('type', 'city')
                ->where('parent_code', $governorateCode)
                ->select('id', 'code', 'name_ar', 'name_en')
                ->orderBy('name_ar')
                ->get();
        });

        return response()->json($cities, 200, [], JSON_UNESCAPED_UNICODE);
    }
}
