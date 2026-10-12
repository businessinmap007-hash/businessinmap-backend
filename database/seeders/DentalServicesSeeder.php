<?php

namespace Database\Seeders;

use App\Models\DentalService;
use Illuminate\Database\Seeder;

/**
 * «خدمات عيادات الأسنان» — the platform's list of what a dental clinic does.
 *
 *     php artisan db:seed --class=DentalServicesSeeder
 *
 * Add-only ([[seeder-must-withdraw]]): an entry that exists is never rewritten, and a clinic's own entries
 * (`owner_id` set) are never touched. The data is database/seeders/data/dental_services.php.
 */
class DentalServicesSeeder extends Seeder
{
    public function run(): void
    {
        $added = 0;

        foreach (require __DIR__ . '/data/dental_services.php' as $kind => $rows) {
            foreach ($rows as $i => [$ar, $en]) {
                $row = DentalService::query()->firstOrCreate(
                    ['kind' => $kind, 'name_ar' => $ar, 'owner_id' => null],
                    ['name_en' => $en, 'sort_order' => $i + 1],
                );

                $added += $row->wasRecentlyCreated ? 1 : 0;
            }
        }

        $this->command?->info("Dental services: {$added} added · total " . DentalService::query()->whereNull('owner_id')->count());
    }
}
