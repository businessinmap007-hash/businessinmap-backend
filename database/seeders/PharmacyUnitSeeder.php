<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * «وحدة البيع عبوة او شريط او قطعة لا يوجد لتر وجرام وكيلو» — المالك،
 * 2026-08-26, correcting «قاموس الأدوية».
 *
 *     php artisan db:seed --class=PharmacyUnitSeeder
 *
 * `catalog_units` had `pack`/`pcs` already but no «شريط» — the blister strip a
 * pharmacist actually counts loose tablets by, distinct from the box it comes
 * in. Idempotent: `insertOrIgnore` on the unique `code`.
 *
 * @see \App\Support\SaleUnits::pharmacyOptions()
 */
class PharmacyUnitSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('catalog_units')->insertOrIgnore([
            'code' => 'strip',
            'name_ar' => 'شريط',
            'name_en' => 'Strip',
            'unit_type' => 'count',
            'is_active' => 1,
            'sort_order' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command?->info('«شريط» ensured in catalog_units.');
    }
}
