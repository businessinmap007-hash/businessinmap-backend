<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Two more laptops under computers_laptops (product_category_child_id=72),
 * on top of the 11 curated by the original retail_core_products import —
 * one gaming pick and one business pick, the two categories a 2026-09-30
 * web search named as currently popular in Egypt alongside the brands
 * already seeded (Lenovo IdeaPad, HP Pavilion, Dell Inspiron/Latitude).
 * Starts «منيو مواصفات 2» (computer/laptop) the same way
 * TechDevicePhonesExpansionSeeder started «منيو مواصفات 1» (mobiles) — see
 * [[tech-spec-menu-implementation]]. {@see TechDeviceSpecsSeeder} fills in
 * the spec values once these products exist.
 */
class TechDeviceLaptopsExpansionSeeder extends Seeder
{
    private const CHILD_ID = 72;
    private const CATEGORY_ID = 13;
    private const UNIT_ID = 5;

    /** bim_code => [brand_id, manufacturer_id, name_ar, name_en, sort_order] */
    private const PRODUCTS = [
        'BIM-RT-COMP-014' => [22, 169, 'لينوفو ليجن 5 15 بوصة', 'Lenovo Legion 5 15 inch', 56],
        'BIM-RT-COMP-015' => [23, 167, 'اتش بي إيليت بوك 840 G9 14 بوصة', 'HP EliteBook 840 G9 14 inch', 57],
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
                    'short_name_ar' => 'لابتوب',
                    'image_alt_ar' => $nameAr,
                    'image_alt_en' => $nameEn,
                    'unit_id' => self::UNIT_ID,
                    'country_code' => 'EG',
                    'market_scope' => 'egypt',
                    'is_verified_egypt' => 0,
                    'verification_source' => 'research',
                    'search_keywords' => 'لابتوب laptop',
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
