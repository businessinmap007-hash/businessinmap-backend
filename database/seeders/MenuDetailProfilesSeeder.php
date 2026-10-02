<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The kinds of «منيو تفصيلي» and the fields each one is described by — see
 * App\Models\MenuDetailProfile. An option group with no profile is a «منيو
 * أساسي».
 *
 * A profile's fields and the groups it is assigned to are written ONCE, the
 * run that creates the profile. After that they belong to the admin's
 * «أشكال المنيو» screen: re-running this seeder never puts back a field the
 * admin unticked or re-assigns a group the admin set back to basic — an
 * add-only seeder restores what curation took away ([[seeder-must-withdraw]]).
 */
class MenuDetailProfilesSeeder extends Seeder
{
    /** code => [name_ar, name_en, data_type, unit code|null] — attributes only these profiles need. */
    private const NEW_ATTRIBUTES = [
        'model_year' => ['سنة الصنع', 'Model year', 'number', null],
        'mileage_km' => ['عدد الكيلومترات', 'Mileage', 'number', 'km'],
        'engine_cc' => ['سعة المحرك', 'Engine size', 'number', 'cc'],
        'transmission' => ['ناقل الحركة', 'Transmission', 'select', null],
        'fuel_type' => ['نوع الوقود', 'Fuel', 'select', null],
        'body_type' => ['شكل الهيكل', 'Body type', 'text', null],
        'compatible_with' => ['متوافق مع', 'Compatible with', 'text', null],
        'connector' => ['نوع المنفذ', 'Connector', 'text', null],
        'power_w' => ['القدرة', 'Power', 'number', 'w'],
    ];

    /** attribute code => [[slug, ar, en], …] — what a select field offers. */
    private const OPTIONS = [
        'transmission' => [['automatic', 'أوتوماتيك', 'Automatic'], ['manual', 'مانيوال', 'Manual']],
        'fuel_type' => [
            ['petrol', 'بنزين', 'Petrol'], ['diesel', 'ديزل', 'Diesel'], ['electric', 'كهرباء', 'Electric'],
            ['hybrid', 'هجين', 'Hybrid'], ['natural-gas', 'غاز طبيعي', 'Natural gas'],
        ],
        'color' => [
            ['white', 'أبيض', 'White'], ['black', 'أسود', 'Black'], ['silver', 'فضي', 'Silver'], ['gray', 'رمادي', 'Gray'],
            ['blue', 'أزرق', 'Blue'], ['red', 'أحمر', 'Red'], ['green', 'أخضر', 'Green'], ['gold', 'ذهبي', 'Gold'],
            ['brown', 'بني', 'Brown'], ['beige', 'بيج', 'Beige'], ['orange', 'برتقالي', 'Orange'],
            ['yellow', 'أصفر', 'Yellow'], ['purple', 'بنفسجي', 'Purple'], ['pink', 'وردي', 'Pink'],
        ],
    ];

    /**
     * Fields the merchant states for EACH unit rather than taking from the
     * catalog master: the master «Toyota Corolla» has no year, mileage,
     * gearbox or colour — the same model is sold as 2018 and as 2023, manual
     * and automatic. profile code => [attribute codes].
     */
    private const PER_ITEM = [
        'cars' => ['model_year', 'mileage_km', 'transmission', 'fuel_type', 'engine_cc', 'color'],
        'computers' => ['color'],
        'mobiles' => ['color'],
        'mobile_accessories' => ['color'],
        'appliances' => ['color'],
    ];

    /** code => [name_ar, name_en] */
    private const NEW_UNITS = [
        'km' => ['كم', 'km'],
        'cc' => ['سي سي', 'cc'],
        'w' => ['وات', 'W'],
    ];

    /**
     * code => [name_ar, name_en, icon, [attribute code => show_on_card], [option group name_ar to assign]]
     */
    private const PROFILES = [
        'mobiles' => ['موبايلات', 'Mobiles', 'smartphone', [
            'processor' => false, 'ram_gb' => true, 'storage' => true, 'screen_inches' => true, 'os' => false,
            'rear_camera_mp' => false, 'front_camera_mp' => false, 'battery_mah' => false, 'color' => false,
        ], ['أجهزة الموبايل']],
        'mobile_accessories' => ['إكسسوارات موبايل', 'Mobile accessories', 'headphones', [
            'compatible_with' => true, 'connector' => true, 'power_w' => true, 'battery_mah' => false,
            'color' => false, 'material' => false,
        ], ['اكسسوارات']],
        /*
         * Fields below were picked from the sites the owner pointed at (2026-10-02):
         * Jumia Egypt's laptop filters — brand · processor · display size ·
         * storage · operating system — with RAM and graphics card read off
         * every listing title («Core i7-13620H · 16GB · 512GB SSD · RTX 4050 ·
         * 15.6 FHD»); and hatla2ee.com's car page — year, mileage, gearbox and
         * fuel beside the price, then engine size and colour in its details
         * table (docs/ux-references.md §16). The brand is the product's own,
         * and a car's body type («سيدان»، «SUV») is the BRANCH, so neither is
         * a field here.
         */
        'computers' => ['كمبيوتر ولاب توب', 'Computers & laptops', 'laptop', [
            'processor' => true, 'ram_gb' => true, 'storage' => true, 'screen_inches' => false,
            'gpu' => false, 'os' => false, 'color' => false,
        ], ['أجهزة الكمبيوتر']],
        'cars' => ['سيارات', 'Cars', 'car', [
            'model_year' => true, 'mileage_km' => true, 'transmission' => true, 'fuel_type' => true,
            'engine_cc' => false, 'color' => false,
        ], ['نوع المركبة']],
        'appliances' => ['أجهزة كهربائية', 'Home appliances', 'appliance', [
            'appliance_type' => true, 'operation_type' => false, 'capacity_cu_ft' => true, 'wash_capacity_kg' => true,
            'capacity_liters' => true, 'power_hp' => true, 'drawers' => false, 'burners' => false,
            'place_settings' => false, 'screen_inches' => true, 'color' => false,
        ], []],
    ];

    public function run(): void
    {
        $now = now();

        foreach (self::NEW_UNITS as $code => [$ar, $en]) {
            if (! DB::table('catalog_units')->where('code', $code)->exists()) {
                DB::table('catalog_units')->insert([
                    'code' => $code, 'name_ar' => $ar, 'name_en' => $en, 'unit_type' => 'other',
                    'is_active' => 1, 'sort_order' => 100, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
        $unitIds = DB::table('catalog_units')->pluck('id', 'code')->map(fn ($id) => (int) $id);

        $nextSort = (int) DB::table('catalog_attributes')->max('sort_order') + 1;
        foreach (self::NEW_ATTRIBUTES as $code => [$ar, $en, $type, $unit]) {
            if (! DB::table('catalog_attributes')->where('code', $code)->exists()) {
                DB::table('catalog_attributes')->insert([
                    'code' => $code, 'name_ar' => $ar, 'name_en' => $en, 'data_type' => $type,
                    'unit_id' => $unit ? ($unitIds[$unit] ?? null) : null,
                    'is_filterable' => 1, 'is_variant_axis' => 0, 'is_required' => 0,
                    'sort_order' => $nextSort++, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
        // «ناقل الحركة» and «نوع الوقود» were first written as free text; they
        // are choices. Only while nothing has been written under them.
        foreach (['transmission', 'fuel_type'] as $code) {
            $id = (int) DB::table('catalog_attributes')->where('code', $code)->where('data_type', 'text')->value('id');
            if ($id && ! DB::table('catalog_product_attribute_values')->where('attribute_id', $id)->exists()) {
                DB::table('catalog_attributes')->where('id', $id)->update(['data_type' => 'select', 'updated_at' => $now]);
            }
        }

        $attributeIds = DB::table('catalog_attributes')->pluck('id', 'code')->map(fn ($id) => (int) $id);

        foreach (self::OPTIONS as $code => $options) {
            $attributeId = $attributeIds[$code] ?? null;
            if (! $attributeId || DB::table('catalog_attribute_options')->where('attribute_id', $attributeId)->exists()) {
                continue;
            }
            foreach ($options as $i => [$slug, $ar, $en]) {
                DB::table('catalog_attribute_options')->insert([
                    'attribute_id' => $attributeId, 'slug' => $slug, 'value_ar' => $ar, 'value_en' => $en,
                    'sort_order' => ($i + 1) * 10, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        $sort = 0;
        foreach (self::PROFILES as $code => [$ar, $en, $icon, $fields, $groups]) {
            $sort += 10;

            if (DB::table('menu_detail_profiles')->where('code', $code)->exists()) {
                continue;
            }

            $profileId = (int) DB::table('menu_detail_profiles')->insertGetId([
                'code' => $code, 'name_ar' => $ar, 'name_en' => $en, 'icon' => $icon,
                'sort_order' => $sort, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);

            $position = 0;
            foreach ($fields as $attribute => $onCard) {
                if (! isset($attributeIds[$attribute])) {
                    continue;
                }
                DB::table('menu_detail_profile_attributes')->insert([
                    'menu_detail_profile_id' => $profileId,
                    'catalog_attribute_id' => $attributeIds[$attribute],
                    'sort_order' => $position += 10,
                    'show_on_card' => $onCard,
                    'per_item' => in_array($attribute, self::PER_ITEM[$code] ?? [], true),
                    'is_filterable' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($groups !== []) {
                DB::table('option_groups')
                    ->whereIn('name_ar', $groups)
                    ->whereNull('menu_detail_profile_id')
                    ->update(['menu_detail_profile_id' => $profileId, 'updated_at' => $now]);
            }
        }
    }
}
