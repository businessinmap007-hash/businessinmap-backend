<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * «الورقيات بتكون اما بالرابطة او بالكيلو او جرام مثلها مثل الفواكة
 * والخضروات فلذلك اجعل الوحدات فيها بالثلاثة دول فقط» — owner, 2026-09-29.
 *
 * `catalog_units` had كجم/جم already; «رابطة» (bunch — بقدونس, كزبرة,
 * شبت… are commonly sold this way, unlike a fruit or a root vegetable)
 * never existed as a sellable unit at all. Added here, alongside
 * {@see \App\Support\SaleUnits::herbsCodes()} which is what actually
 * narrows the picker to these three for an «أعشاب وورقيات» row — this
 * seeder only has to make sure the row itself exists.
 *
 *     php artisan db:seed --class=HerbsBunchUnitSeeder
 *
 * Idempotent: a second run inserts nothing.
 */
class HerbsBunchUnitSeeder extends Seeder
{
    public function run(): void
    {
        $exists = DB::table('catalog_units')->where('code', 'bunch')->exists();

        if ($exists) {
            $this->command?->info('«رابطة» already exists — nothing to do.');

            return;
        }

        DB::table('catalog_units')->insert([
            'code' => 'bunch',
            'name_ar' => 'رابطة',
            'name_en' => 'Bunch',
            'unit_type' => 'count',
            'is_active' => 1,
            'sort_order' => (int) DB::table('catalog_units')->max('sort_order') + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command?->info('«رابطة» (bunch) added to catalog_units.');
    }
}
