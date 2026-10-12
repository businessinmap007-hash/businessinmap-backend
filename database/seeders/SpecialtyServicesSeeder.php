<?php

namespace Database\Seeders;

use App\Models\SpecialtyService;
use Illuminate\Database\Seeder;

/**
 * «خدمات التخصص» — the platform's lists of what a specialty clinic does (dental, dermatology and cosmetic).
 *
 *     php artisan db:seed --class=SpecialtyServicesSeeder
 *
 * Add-only ([[seeder-must-withdraw]]): an entry that exists is never rewritten, and a clinic's own entries (`owner_id`
 * set) are never touched. The one thing it moves is a platform row's specialty and kind: a row is found by its Arabic
 * name alone and re-filed when the data file puts it elsewhere (the id stays, so every clinic's offer and every booked
 * appointment keeps pointing at it). The data is database/seeders/data/specialty_services.php.
 */
class SpecialtyServicesSeeder extends Seeder
{
    public function run(): void
    {
        $added = 0;
        $moved = 0;

        foreach (require __DIR__ . '/data/specialty_services.php' as $specialty => $kinds) {
            foreach ($kinds as $kind => $rows) {
                foreach ($rows as $i => [$ar, $en]) {
                    $row = SpecialtyService::query()->whereNull('owner_id')->where('name_ar', $ar)->first();

                    if ($row === null) {
                        SpecialtyService::create([
                            'specialty' => $specialty, 'kind' => $kind, 'name_ar' => $ar, 'name_en' => $en,
                            'owner_id' => null, 'sort_order' => $i + 1,
                        ]);
                        $added++;
                    } elseif ($row->kind !== $kind || $row->specialty !== $specialty) {
                        $row->update(['specialty' => $specialty, 'kind' => $kind, 'sort_order' => $i + 1]);
                        $moved++;
                    }
                }
            }
        }

        $this->command?->info("Specialty services: {$added} added, {$moved} re-filed · total " . SpecialtyService::query()->whereNull('owner_id')->count());
    }
}
