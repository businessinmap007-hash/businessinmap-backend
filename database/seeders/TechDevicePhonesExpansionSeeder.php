<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seven more phones under mobiles_accessories (product_category_child_id=73),
 * on top of the 14 curated by the original retail_core_products import — a
 * current/popular pick per brand already in the catalog (no new brand
 * introduced), per the owner's «ابدأ بعدد محدود من الموبايلات المشهورة
 * فقط». Same bim_code/brand/manufacturer/unit pattern as the existing 19
 * mobiles_accessories rows. {@see TechDeviceSpecsSeeder} fills in the spec
 * values once these products exist.
 */
class TechDevicePhonesExpansionSeeder extends Seeder
{
    private const CHILD_ID = 73;
    private const CATEGORY_ID = 13;
    private const UNIT_ID = 5;

    /** bim_code => [brand_id, manufacturer_id, name_ar, name_en, sort_order] */
    private const PRODUCTS = [
        'BIM-RT-MOBI-020' => [16, 159, 'سامسونج جالاكسي S٢٤ الترا ٢٥٦ جيجا', 'Samsung Galaxy S24 Ultra 256GB', 75],
        'BIM-RT-MOBI-021' => [17, 172, 'ابل ايفون ١٥ برو ماكس ٢٥٦ جيجا', 'Apple iPhone 15 Pro Max 256GB', 76],
        'BIM-RT-MOBI-022' => [18, 173, 'شاومي ريدمي نوت ١٣ برو ٢٥٦ جيجا', 'Xiaomi Redmi Note 13 Pro 256GB', 77],
        'BIM-RT-MOBI-023' => [18, 173, 'شاومي بوكو X٦ برو ٢٥٦ جيجا', 'Xiaomi Poco X6 Pro 256GB', 78],
        'BIM-RT-MOBI-024' => [20, 175, 'ريلمي ١٢ ١٢٨ جيجا', 'Realme 12 128GB', 79],
        'BIM-RT-MOBI-025' => [214, 176, 'انفينكس نوت ٤٠ برو ٢٥٦ جيجا', 'Infinix Note 40 Pro 256GB', 80],
        'BIM-RT-MOBI-026' => [215, 177, 'تكنو كامون ٢٠ ٢٥٦ جيجا', 'Tecno Camon 20 256GB', 81],
    ];

    public function run(): void
    {
        $now = now();

        foreach (self::PRODUCTS as $bimCode => [$brandId, $manufacturerId, $nameAr, $nameEn, $sort]) {
            DB::table('catalog_products')->updateOrInsert(
                ['bim_code' => $bimCode],
                [
                    'product_category_id' => self::CATEGORY_ID,
                    'product_category_child_id' => self::CHILD_ID,
                    'brand_id' => $brandId,
                    'manufacturer_id' => $manufacturerId,
                    'product_type' => 'simple',
                    'name_ar' => $nameAr,
                    'normalized_name_ar' => mb_strtolower($nameAr),
                    'name_en' => $nameEn,
                    'short_name_ar' => 'موبايل',
                    'image_alt_ar' => $nameAr,
                    'image_alt_en' => $nameEn,
                    'unit_id' => self::UNIT_ID,
                    'country_code' => 'EG',
                    'market_scope' => 'egypt',
                    'is_verified_egypt' => 0,
                    'verification_source' => 'research',
                    'search_keywords' => 'موبايل mobile',
                    'duplicate_status' => 'unique',
                    'is_active' => 1,
                    'approval_status' => 'approved',
                    'sort_order' => $sort,
                    'curation_status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
