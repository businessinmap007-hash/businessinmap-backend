<?php

namespace App\Services;

use App\Models\City;
use App\Models\Governorate;
use App\Models\Country;

class LocationService
{
    public static function detect(float $lat, float $lng): ?array
    {
        $city = City::selectRaw("
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

        if (!$city) return null;

        $governorate = Governorate::find($city->governorate_id);
        $country     = Country::find($governorate->country_id);

        return [
            'country_id'     => $country->id,
            'governorate_id' => $governorate->id,
            'city_id'        => $city->id,
        ];
    }
}

