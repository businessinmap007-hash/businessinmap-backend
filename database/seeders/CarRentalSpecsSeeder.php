<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * A car for RENT is described by other things than a car for SALE.
 *
 *     php artisan db:seed --class=CarRentalSpecsSeeder
 *
 * Owner, 2026-10-18: «سيارة من المالك ممكن تكون للبيع أو للإيجار، ونوع التعامل هو الفلتر … إذا اختار للبيع تكون
 * مواصفات، وإذا كانت إيجار لها المواصفات الخاصة به».
 *
 * One kind of listing («سيارات»), one tick that tells the two apart («نوع التعامل»: بيع / إيجار). The fields follow
 * the tick through `menu_detail_profile_attributes.only_with_option_id` / `not_with_option_id`:
 *
 *   • «إيجار» ticked → the rental terms are asked: with or without a driver, what the insurance covers, the kilometres
 *     a day that are included, the renter's minimum age, the refundable deposit, and the weekly and monthly rates
 *     (the daily price is the listing's own price);
 *   • «إيجار» ticked → the mileage is NOT asked (it is a sale's figure);
 *   • anything else → exactly what a sale was asked before.
 *
 * Added to the `cars` profile only if it is missing, and a dependency is written only where none is set — after that the
 * fields belong to the admin's «أشكال المنيو» screen like every other ([[seeder-must-withdraw]]). Idempotent.
 */
class CarRentalSpecsSeeder extends Seeder
{
    private const PROFILE = 'cars';

    private const DEAL_GROUP = 'نوع التعامل';

    private const RENT = 'إيجار';

    /** code => [name_ar, name_en, data_type, filterable, on card] */
    private const ATTRIBUTES = [
        'rental_driver' => ['السائق', 'Driver', 'select', true, true],
        'rental_insurance' => ['التأمين', 'Insurance', 'select', true, false],
        'km_per_day' => ['الكيلومترات المشمولة يوميًا', 'Kilometres included per day', 'number', false, false],
        'min_renter_age' => ['أقل سن للمستأجر', 'Minimum renter age', 'number', false, false],
        'rental_deposit' => ['التأمين المسترد (جنيه)', 'Refundable deposit (EGP)', 'number', false, false],
        'weekly_rate' => ['سعر الأسبوع (جنيه)', 'Weekly rate (EGP)', 'number', false, true],
        'monthly_rate' => ['سعر الشهر (جنيه)', 'Monthly rate (EGP)', 'number', false, true],
    ];

    /** attribute code => [[slug, ar, en], …] */
    private const OPTIONS = [
        'rental_driver' => [['self-drive', 'بدون سائق', 'Self-drive'], ['with-driver', 'بسائق', 'With driver'], ['either', 'بسائق أو بدونه', 'With or without driver']],
        'rental_insurance' => [['comprehensive', 'شامل', 'Comprehensive'], ['third-party', 'ضد الغير فقط', 'Third-party only'], ['none', 'بدون تأمين', 'None']],
    ];

    public function run(): void
    {
        $profileId = (int) DB::table('menu_detail_profiles')->where('code', self::PROFILE)->value('id');
        $rentId = (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', self::DEAL_GROUP)->where('o.name_ar', self::RENT)->value('o.id');

        if ($profileId <= 0 || $rentId <= 0) {
            $this->command?->warn('  ! نوع التفاصيل «سيارات» أو خيار «إيجار» غير موجود.');

            return;
        }

        $now = now();
        $sort = (int) DB::table('catalog_attributes')->max('sort_order') + 1;

        foreach (self::ATTRIBUTES as $code => [$ar, $en, $type]) {
            if (! DB::table('catalog_attributes')->where('code', $code)->exists()) {
                DB::table('catalog_attributes')->insert([
                    'code' => $code, 'name_ar' => $ar, 'name_en' => $en, 'data_type' => $type, 'unit_id' => null,
                    'is_filterable' => 1, 'is_variant_axis' => 0, 'is_required' => 0,
                    'sort_order' => $sort++, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        $attributeIds = DB::table('catalog_attributes')->pluck('id', 'code')->map(fn ($id) => (int) $id);

        foreach (self::OPTIONS as $code => $options) {
            $attributeId = $attributeIds[$code];

            foreach ($options as $i => [$slug, $ar, $en]) {
                if (! DB::table('catalog_attribute_options')->where('attribute_id', $attributeId)->where('slug', $slug)->exists()) {
                    DB::table('catalog_attribute_options')->insert([
                        'attribute_id' => $attributeId, 'slug' => $slug, 'value_ar' => $ar, 'value_en' => $en,
                        'sort_order' => ($i + 1) * 10, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }

        $position = (int) DB::table('menu_detail_profile_attributes')->where('menu_detail_profile_id', $profileId)->max('sort_order');
        $added = 0;

        foreach (self::ATTRIBUTES as $code => [, , , $filterable, $onCard]) {
            $attributeId = $attributeIds[$code];
            $row = DB::table('menu_detail_profile_attributes')->where('menu_detail_profile_id', $profileId)->where('catalog_attribute_id', $attributeId);

            if ($row->exists()) {
                $row->whereNull('only_with_option_id')->update(['only_with_option_id' => $rentId, 'updated_at' => $now]);

                continue;
            }

            DB::table('menu_detail_profile_attributes')->insert([
                'menu_detail_profile_id' => $profileId, 'catalog_attribute_id' => $attributeId,
                'sort_order' => $position += 10, 'show_on_card' => $onCard, 'per_item' => 1, 'is_filterable' => $filterable,
                'only_with_option_id' => $rentId, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $added++;
        }

        // the mileage is a sale's figure: a rental is not asked it
        DB::table('menu_detail_profile_attributes')
            ->where('menu_detail_profile_id', $profileId)
            ->where('catalog_attribute_id', (int) ($attributeIds['mileage_km'] ?? 0))
            ->whereNull('not_with_option_id')
            ->update(['not_with_option_id' => $rentId, 'updated_at' => $now]);

        $this->command?->info("Car rental specs: {$added} field(s) added to «" . self::PROFILE . '».');
    }
}
