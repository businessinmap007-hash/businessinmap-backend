<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\City;

class LinkCitiesByGeoSeeder extends Seeder
{
    public function run(): void
    {
        $bounds = config('egypt_governorates_bbox');
        $governorates = DB::table('governorates')->get()->keyBy('name_ar');

        $updated = 0;
        $skipped = 0;

        foreach ($governorates as $govName => $gov) {

            if (!isset($bounds[$govName])) {
                continue;
            }

            [$minLat, $maxLat] = $bounds[$govName]['lat'];
            [$minLng, $maxLng] = $bounds[$govName]['lng'];

            $affected = City::whereBetween('latitude', [$minLat, $maxLat])
                ->whereBetween('longitude', [$minLng, $maxLng])
                ->update([
                    'governorate_id' => $gov->id
                ]);

            $updated += $affected;
        }

        $this->command->info("✅ Cities linked by geo: {$updated}");
        $this->command->warn("⚠ Cities skipped: {$skipped}");
    }
}
