<?php

namespace App\Services\Requests;

use Illuminate\Support\Facades\DB;

/**
 * A ride without a map: how far, and what a fair starting price is. The distance is the straight line between the
 * CENTRES of the two cities, stretched by a road factor; when it cannot be known that way (both ends in one city, a
 * city with no coordinates) the customer says how far, because nobody else can. The price is only a suggestion — the
 * customer starts from it and every driver answers with his own.
 */
final class RideQuote
{
    /**
     * @return array{distance_km: float, source: string, suggested_price: float}|null null when the distance is unknown
     *         and the customer gave none
     */
    public static function between(int $originCityId, int $destinationCityId, ?float $manualKm = null): ?array
    {
        $km = null;
        $source = 'cities';

        if ($originCityId !== $destinationCityId) {
            $points = DB::table('cities')->whereIn('id', [$originCityId, $destinationCityId])->get(['id', 'latitude', 'longitude'])->keyBy('id');
            $a = $points->get($originCityId);
            $b = $points->get($destinationCityId);

            if ($a && $b && $a->latitude !== null && $b->latitude !== null) {
                $km = self::haversineKm((float) $a->latitude, (float) $a->longitude, (float) $b->latitude, (float) $b->longitude)
                    * (float) config('bim.rides.road_factor', 1.3);
            }
        }

        if ($km === null) {
            if ($manualKm === null || $manualKm <= 0) {
                return null;
            }

            $km = $manualKm;
            $source = 'manual';
        }

        $km = round($km, 1);

        return ['distance_km' => $km, 'source' => $source, 'suggested_price' => self::priceFor($km)];
    }

    /** The suggested fare for a distance: the rate per km, never below the minimum fare, rounded to a whole pound. */
    public static function priceFor(float $km): float
    {
        return (float) max(
            (float) config('bim.rides.min_fare', 30),
            round($km * (float) config('bim.rides.rate_per_km', 7))
        );
    }

    public static function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
