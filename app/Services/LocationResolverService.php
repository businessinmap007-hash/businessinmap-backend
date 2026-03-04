<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class LocationResolverService
{
    public function nearestCity(float $lat, float $lng, float $box = 0.5, float $maxKm = 50): ?array
    {
        $row = DB::selectOne("
            SELECT 
              c.id AS city_id,
              c.name_ar AS city_name_ar,
              c.parent_id AS governorate_id,
              g.name_ar AS governorate_name_ar,
              (
                6371 * 2 * ASIN(
                  SQRT(
                    POWER(SIN(RADIANS(c.lat - ?) / 2), 2) +
                    COS(RADIANS(?)) * COS(RADIANS(c.lat)) *
                    POWER(SIN(RADIANS(c.lng - ?) / 2), 2)
                  )
                )
              ) AS distance_km
            FROM locations c
            JOIN locations g ON g.id = c.parent_id AND g.type='governorate'
            WHERE c.type='city'
              AND c.lat BETWEEN (? - ?) AND (? + ?)
              AND c.lng BETWEEN (? - ?) AND (? + ?)
            ORDER BY distance_km ASC
            LIMIT 1
        ", [
            $lat, $lat, $lng,
            $lat, $box, $lat, $box,
            $lng, $box, $lng, $box
        ]);

        if (!$row) return null;
        if ($row->distance_km > $maxKm) return null; // غير مؤكد

        return [
            'city_id' => (int)$row->city_id,
            'governorate_id' => (int)$row->governorate_id,
            'distance_km' => (float)$row->distance_km,
        ];
    }
}
