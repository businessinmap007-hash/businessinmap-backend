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
 * (`owner_id` set) are never touched. The one thing it moves is a platform row's KIND: a row is found by its Arabic name
 * alone and, when the data file files it under another kind, re-filed (the id stays, so every hospital's choice and every
 * request keeps pointing at it) — that is how the first 38 rows, seeded under three kinds, reached their specialties.
 * The data is database/seeders/data/medical_procedures.php.
 */
class MedicalProceduresSeeder extends Seeder
{
    public function run(): void
    {
        $added = 0;
        $moved = 0;

        foreach (require __DIR__ . '/data/medical_procedures.php' as $kind => $rows) {
            foreach ($rows as $i => [$ar, $en]) {
                $row = MedicalProcedure::query()->whereNull('owner_id')->where('name_ar', $ar)->first();

                if ($row === null) {
                    MedicalProcedure::create(['kind' => $kind, 'name_ar' => $ar, 'name_en' => $en, 'owner_id' => null, 'sort_order' => $i + 1]);
                    $added++;
                } elseif ($row->kind !== $kind) {
                    $row->update(['kind' => $kind, 'sort_order' => $i + 1]);
                    $moved++;
                }
            }
        }

        $this->command?->info("Medical procedures: {$added} added, {$moved} re-filed · total " . MedicalProcedure::query()->whereNull('owner_id')->count());
    }
}
