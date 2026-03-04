<?php

namespace App\Helpers;

use App\Models\City;
use App\Models\Governorate;
use App\Models\Country;

class LocationHelper
{
    public static function detectFromLatLng(float $lat, float $lng): ?array
    {
        $radius = 50; // KM
        $earthRadius = 6371;

        $latDelta = rad2deg($radius / $earthRadius);
        $lngDelta = rad2deg($radius / $earthRadius / cos(deg2rad($lat)));

        $cities = City::whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta])
            ->selectRaw("
                id,
                governorate_id,
                (
                    6371 * acos(
                        cos(radians(?))
                        * cos(radians(latitude))
                        * cos(radians(longitude) - radians(?))
                        + sin(radians(?))
                        * sin(radians(latitude))
                    )
                ) AS distance
            ", [$lat, $lng, $lat])
            ->orderBy('distance')
            ->first();

        if (!$cities) return null;

        $governorate = Governorate::find($cities->governorate_id);
        $country     = Country::find($governorate->country_id);

        return [
            'country_id'     => $country->id,
            'governorate_id' => $governorate->id,
            'city_id'        => $cities->id,
        ];
    }
}
