<?php

namespace Database\Seeders;

use App\Models\PlatformService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Give every booking kind the unit it is measured in.
 *
 * Writes `booking_kind_granularity.php` onto `platform_service_item_types.meta`
 * — the kind's own row, because the kind is what says HOW a thing is booked and
 * a unit is part of that answer. It was previously nowhere: `duration_unit`
 * arrived from the client and was checked only against an enum.
 *
 * MERGES into whatever meta the row already holds, so a key set elsewhere
 * survives; only the three granularity keys are written. A kind absent from the
 * file is left completely alone.
 *
 * Idempotent.
 */
class BookingKindGranularitySeeder extends Seeder
{
    public function run(): void
    {
        $serviceId = (int) PlatformService::query()
            ->where('key', PlatformService::KEY_BOOKING)
            ->value('id');

        if ($serviceId <= 0) {
            $this->command?->warn('The booking service is missing — nothing to do.');

            return;
        }

        $declared = require database_path('seeders/data/booking_kind_granularity.php');

        $written = 0;
        $unclaimed = [];

        $rows = DB::table('platform_service_item_types')
            ->where('platform_service_id', $serviceId)
            ->where('is_active', 1)
            ->get(['id', 'key', 'name_ar', 'meta']);

        foreach ($rows as $row) {
            $spec = $declared[(string) $row->key] ?? null;

            if (! $spec) {
                $unclaimed[] = (string) $row->name_ar;

                continue;
            }

            $meta = json_decode((string) $row->meta, true);
            $meta = is_array($meta) ? $meta : [];

            $merged = array_merge($meta, [
                'duration_unit' => (string) $spec['unit'],
                'slot_minutes' => (int) $spec['slot_minutes'],
                'all_day' => (bool) $spec['all_day'],
            ]);

            if ($merged === $meta) {
                continue;
            }

            DB::table('platform_service_item_types')
                ->where('id', $row->id)
                ->update(['meta' => json_encode($merged, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);

            $written++;
        }

        $this->command?->info("Booking granularity: {$written} kind(s) written.");

        foreach ($unclaimed as $name) {
            $this->command?->warn("«{$name}» has no declared unit — the client still decides for it.");
        }
    }
}
