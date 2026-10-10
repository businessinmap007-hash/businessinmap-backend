<?php

namespace Database\Seeders;

use App\Models\MedicalProcedure;
use Illuminate\Database\Seeder;

/**
 * «الإجراءات الطبية» — the platform's list of surgeries, endoscopies and treatment procedures.
 *
 *     php artisan db:seed --class=MedicalProceduresSeeder
 *
 * Add-only ([[seeder-must-withdraw]]): an entry that exists is never rewritten, and a hospital's own entries
 * (`owner_id` set) are never touched. The data is database/seeders/data/medical_procedures.php.
 */
class MedicalProceduresSeeder extends Seeder
{
    public function run(): void
    {
        $added = 0;

        foreach (require __DIR__ . '/data/medical_procedures.php' as $kind => $rows) {
            foreach ($rows as $i => [$ar, $en]) {
                $row = MedicalProcedure::query()->firstOrCreate(
                    ['kind' => $kind, 'name_ar' => $ar, 'owner_id' => null],
                    ['name_en' => $en, 'sort_order' => $i + 1],
                );

                $added += $row->wasRecentlyCreated ? 1 : 0;
            }
        }

        $this->command?->info("Medical procedures: {$added} added · total " . MedicalProcedure::query()->whereNull('owner_id')->count());
    }
}
