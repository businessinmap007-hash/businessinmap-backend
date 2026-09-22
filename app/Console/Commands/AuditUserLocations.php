<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CityLocatorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cross-checks every account's GPS point against the governorate/city stored on
 * it, using our own `cities` table (CityLocatorService - no third-party geocoder).
 *
 *   php artisan users:audit-locations                 report only (default)
 *   php artisan users:audit-locations --apply         fill accounts that have GPS but no governorate/city
 *   php artisan users:audit-locations --apply --overwrite   also correct accounts whose stored place disagrees with the GPS
 *
 * A point that resolves to no city within the confidence cap (abroad, at sea, or
 * simply bad data such as 0,0) is never written - it is listed so a human looks.
 */
final class AuditUserLocations extends Command
{
    protected $signature = 'users:audit-locations {--apply : Write the fixes} {--overwrite : With --apply, also replace a stored place that disagrees with the GPS} {--sample=8 : Rows to print per bucket}';

    protected $description = 'Compare each account\'s GPS point with its stored governorate/city and (optionally) fix it';

    public function handle(CityLocatorService $locator): int
    {
        $apply = (bool) $this->option('apply');
        $overwrite = (bool) $this->option('overwrite');
        $sample = max(0, (int) $this->option('sample'));

        $undo = [];
        $stats = ['ok' => 0, 'fill' => 0, 'conflict' => 0, 'outside' => 0, 'no_gps' => 0, 'written' => 0];
        $samples = ['fill' => [], 'conflict' => [], 'outside' => []];

        User::query()->withoutGlobalScopes()->orderBy('id')->chunkById(500, function ($users) use ($locator, $apply, $overwrite, $sample, &$stats, &$samples, &$undo) {
            foreach ($users as $u) {
                $lat = (float) $u->latitude;
                $lng = (float) $u->longitude;
                if (! $u->latitude || ! $u->longitude || ($lat == 0.0 && $lng == 0.0)) {
                    $stats['no_gps']++;

                    continue;
                }

                $city = $locator->nearest($lat, $lng);
                if (! $city) {
                    $stats['outside']++;
                    if (count($samples['outside']) < $sample) {
                        $samples['outside'][] = "#{$u->id} ({$lat}, {$lng})";
                    }

                    continue;
                }

                $blank = ! $u->governorate_id || ! $u->city_id;
                $same = (int) $u->governorate_id === (int) $city->governorate_id && (int) $u->city_id === (int) $city->id;
                if ($same) {
                    $stats['ok']++;

                    continue;
                }

                $bucket = $blank ? 'fill' : 'conflict';
                $stats[$bucket]++;
                if (count($samples[$bucket]) < $sample) {
                    $samples[$bucket][] = "#{$u->id} stored {$u->governorate_id}/{$u->city_id} -> gps {$city->governorate_id}/{$city->id} (" . round((float) $city->distance_km, 1) . ' km)';
                }

                if ($apply && ($blank || $overwrite)) {
                    $undo[] = ['id' => (int) $u->id, 'governorate_id' => $u->governorate_id, 'city_id' => $u->city_id];
                    DB::table('users')->where('id', $u->id)->update([
                        'governorate_id' => (int) $city->governorate_id,
                        'city_id' => (int) $city->id,
                    ]);
                    $stats['written']++;
                }
            }
        });

        if ($undo) {
            $file = 'audit-locations-' . now()->format('Ymd-His') . '.json';
            \Illuminate\Support\Facades\Storage::put($file, json_encode($undo));
            $this->info('Previous values saved to storage/app/' . $file . ' (restore by writing them back).');
        }

        $this->table(['bucket', 'accounts'], [
            ['already correct', $stats['ok']],
            ['GPS but no governorate/city (fillable)', $stats['fill']],
            ['stored place disagrees with GPS', $stats['conflict']],
            ['GPS outside any known city (needs a human)', $stats['outside']],
            ['no GPS at all', $stats['no_gps']],
            [$apply ? 'rows written' : 'rows written (dry run)', $stats['written']],
        ]);

        foreach ($samples as $bucket => $rows) {
            if ($rows) {
                $this->line("\n<info>{$bucket}</info>");
                foreach ($rows as $row) {
                    $this->line('  ' . $row);
                }
            }
        }

        return self::SUCCESS;
    }
}
