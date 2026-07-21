<?php

namespace App\Services;

use App\Models\City;

/**
 * Resolve a raw GPS point to our own city. No map provider or third-party
 * geocoder is involved — the answer comes entirely from the `cities` table.
 *
 * Shared by "use my location" (LocationController::nearest) and checkout, where
 * a customer drops a pin for a one-off delivery spot (CustomerCartService).
 */
final class CityLocatorService
{
    /**
     * Nearest city to the pin, or null when nothing sits within the confidence
     * cap — a point far out at sea or in a neighbouring country must return "no
     * confident match" rather than the least-distant city hundreds of km away.
     *
     * The returned model carries a `distance_km` attribute and an eager-loaded
     * `governorate` (id, country_id, name_ar, name_en).
     */
    public function nearest(float $lat, float $lng, ?float $maxKm = null): ?City
    {
        // Widest the answer is allowed to be from the pin before we call it
        // unconfident. A city index is coarse, so this is generous, not tight.
        $maxKm ??= (float) config('bim.location.nearest_max_km', 60);

        // Degree half-widths for the pre-filter box. Longitude degrees shrink
        // toward the poles, so scale by cos(lat); clamp near the poles so the
        // divisor never collapses to zero.
        $latPad = rad2deg($maxKm / 6371);
        $lngPad = rad2deg($maxKm / 6371 / max(0.01, cos(deg2rad($lat))));

        $city = City::query()
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->whereBetween('latitude', [$lat - $latPad, $lat + $latPad])
            ->whereBetween('longitude', [$lng - $lngPad, $lng + $lngPad])
            ->selectRaw(
                '*, (6371 * acos(LEAST(1, GREATEST(-1,'
                . ' cos(radians(?)) * cos(radians(latitude))'
                . ' * cos(radians(longitude) - radians(?))'
                . ' + sin(radians(?)) * sin(radians(latitude))'
                . ')))) AS distance_km',
                [$lat, $lng, $lat]
            )
            ->orderBy('distance_km')
            ->with('governorate:id,country_id,name_ar,name_en')
            ->first();

        if (! $city || (float) $city->distance_km > $maxKm) {
            return null;
        }

        return $city;
    }
}
