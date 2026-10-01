<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Phones, tablets and smart watches under mobiles_accessories, each filed
 * under its menu BRANCH (`line_option_id`) and its SERIES — the two facets the
 * merchant's picker and the customer's storefront filter by.
 *
 *   php artisan db:seed --class=MobileDeviceCatalogSeeder
 *
 * Data: `data/mobile_device_catalog.php`. Idempotent — a product is matched by
 * its name_en inside mobiles_accessories, so the 21 phones seeded before this
 * file are re-filed rather than duplicated, and a re-run changes nothing.
 * A missing brand is created (unverified) rather than skipped.
 */
class MobileDeviceCatalogSeeder extends Seeder
{
    private const CHILD_SLUG = 'mobiles_accessories';

    private const UNIT_ID = 5;

    /** The branch group(s) a `line_option_id` is resolved from, by option name_ar. */
    private const BRANCH_GROUPS = ['أجهزة الموبايل', 'اكسسوارات'];

    public function run(): void
    {
        $map = require database_path('seeders/data/mobile_device_catalog.php');

        $child = DB::table('product_category_children')->where('slug', self::CHILD_SLUG)->first(['id', 'product_category_id']);

        if (! $child) {
            $this->command?->warn('  ! mobiles_accessories غير موجود — تُخطّى.');

            return;
        }

        $branches = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->whereIn('g.name_ar', self::BRANCH_GROUPS)
            ->pluck('o.id', 'o.name_ar')
            ->map(fn ($id) => (int) $id)
            ->all();

        $specs = $this->attributes();
        $created = $refiled = 0;

        DB::transaction(function () use ($map, $child, $branches, $specs, &$created, &$refiled) {
            foreach ($map as $brandEn => $brand) {
                $brandId = $brandEn === '' ? null : $this->brand($brandEn, (string) $brand['ar']);
                $manufacturerId = $brandId ? DB::table('catalog_products')->where('brand_id', $brandId)->whereNotNull('manufacturer_id')->value('manufacturer_id') : null;

                foreach ($brand as $branchName => $seriesList) {
                    if ($branchName === 'ar') {
                        continue;
                    }

                    $lineOptionId = $branches[$branchName] ?? null;

                    if (! $lineOptionId) {
                        $this->command?->warn("  ! فرع «{$branchName}» غير موجود — تُخطّى.");

                        continue;
                    }

                    foreach ($seriesList as $series => $models) {
                        foreach ($models as $nameEn => $spec) {
                            $existing = DB::table('catalog_products')
                                ->where('product_category_child_id', $child->id)
                                ->where('name_en', $nameEn)
                                ->whereNull('deleted_at')
                                ->value('id');

                            $facets = ['series' => $series === '' ? null : $series, 'line_option_id' => $lineOptionId];

                            if ($existing) {
                                $refiled += DB::table('catalog_products')->where('id', $existing)->update($facets);
                                $productId = (int) $existing;

                                // A name still exactly as the old digit rule wrote it is
                                // rewritten with the new one; a name an admin edited is not.
                                if ($spec !== null) {
                                    $this->renameIfUntouched($productId, (string) $spec[0]);
                                }
                            } elseif ($spec === null) {
                                continue; // an accessory row is only ever re-filed, never minted
                            } else {
                                $productId = $this->insert($child, $brandId, $manufacturerId, $nameEn, $spec[0], $branchName, $facets);
                                $created++;
                            }

                            if ($spec !== null) {
                                $this->writeSpecs($productId, $spec, $specs);
                            }
                        }
                    }
                }
            }
        });

        $cameraBattery = $this->cameraAndBattery((int) $child->id);
        $images = $this->images((int) $child->id);

        $this->command?->info('Mobile device catalog:');
        $this->command?->line("  - منتجات أُضيفت : {$created}");
        $this->command?->line("  - منتجات صُنّفت (فرع + سلسلة) : {$refiled}");
        $this->command?->line("  - قيم كاميرا/بطارية كُتبت : {$cameraBattery}");
        $this->command?->line("  - صور مفتوحة المصدر وُضعت : {$images}");
    }

    /**
     * The three attributes this file owns — rear camera, front camera,
     * battery — created on first run, then filled from
     * `data/mobile_device_camera_battery.php`. A null in the file is a value
     * nobody is sure of, and is left unwritten.
     */
    public static function ensureCameraBatteryAttributes(): array
    {
        $units = [];
        foreach (['mp' => ['ميجابكسل', 'MP'], 'mah' => ['مللي أمبير', 'mAh']] as $code => [$ar, $en]) {
            DB::table('catalog_units')->updateOrInsert(
                ['code' => $code],
                ['name_ar' => $ar, 'name_en' => $en, 'unit_type' => 'other', 'is_active' => 1, 'sort_order' => 100, 'updated_at' => now()]
            );
            $units[$code] = (int) DB::table('catalog_units')->where('code', $code)->value('id');
        }

        $attrs = [];
        foreach ([
            'rear_camera_mp' => ['الكاميرا الخلفية', 'Rear camera', 'mp', 206],
            'front_camera_mp' => ['الكاميرا الأمامية', 'Front camera', 'mp', 207],
            'battery_mah' => ['البطارية', 'Battery', 'mah', 208],
        ] as $code => [$ar, $en, $unit, $sort]) {
            DB::table('catalog_attributes')->updateOrInsert(
                ['code' => $code],
                [
                    'name_ar' => $ar, 'name_en' => $en, 'data_type' => 'number', 'unit_id' => $units[$unit],
                    'is_filterable' => 1, 'is_variant_axis' => 0, 'is_required' => 0,
                    'sort_order' => $sort, 'updated_at' => now(),
                ]
            );
            $attrs[$code] = [(int) DB::table('catalog_attributes')->where('code', $code)->value('id'), $units[$unit]];
        }

        return $attrs;
    }

    private function cameraAndBattery(int $childId): int
    {
        $attrs = self::ensureCameraBatteryAttributes();
        $products = DB::table('catalog_products')
            ->where('product_category_child_id', $childId)
            ->whereNull('deleted_at')
            ->pluck('id', 'name_en');

        $written = 0;
        foreach (require database_path('seeders/data/mobile_device_camera_battery.php') as $nameEn => $values) {
            $productId = $products[$nameEn] ?? null;
            if (! $productId) {
                continue;
            }

            foreach (array_combine(['rear_camera_mp', 'front_camera_mp', 'battery_mah'], $values) as $code => $number) {
                if ($number === null) {
                    continue;
                }

                [$attributeId, $unitId] = $attrs[$code];
                DB::table('catalog_product_attribute_values')->updateOrInsert(
                    ['product_id' => (int) $productId, 'attribute_id' => $attributeId, 'option_id' => null],
                    ['value_number' => (float) $number, 'value_text_en' => null, 'unit_id' => $unitId, 'sort_order' => 0, 'updated_at' => now()]
                );
                $written++;
            }
        }

        return $written;
    }

    /**
     * The open-licensed photos in `data/mobile_device_images.php`, each with
     * its licence credit. Only fills an EMPTY image, or replaces one this file
     * wrote earlier (a Commons URL) — a photo an admin uploaded is never
     * touched.
     */
    private function images(int $childId): int
    {
        $path = database_path('seeders/data/mobile_device_images.php');
        if (! is_file($path)) {
            return 0;
        }

        $written = 0;
        foreach (require $path as $nameEn => [$url, $credit]) {
            $written += DB::table('catalog_products')
                ->where('product_category_child_id', $childId)
                ->where('name_en', $nameEn)
                ->whereNull('deleted_at')
                ->where(fn ($q) => $q->whereNull('main_image')->orWhere('main_image', '')
                    ->orWhere('main_image', 'like', 'https://%.wikimedia.org/%'))
                ->where(fn ($q) => $q->whereNull('main_image')->orWhere('main_image', '!=', $url))
                ->update(['main_image' => $url, 'main_image_credit' => $credit, 'updated_at' => now()]);
        }

        return $written;
    }

    private function brand(string $nameEn, string $nameAr): int
    {
        $id = DB::table('catalog_brands')->where('name_en', $nameEn)->whereNull('deleted_at')->value('id');

        if ($id) {
            return (int) $id;
        }

        return (int) DB::table('catalog_brands')->insertGetId([
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'slug' => Str::slug($nameEn),
            'is_active' => 1,
            'is_verified' => 0,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insert(object $child, ?int $brandId, $manufacturerId, string $nameEn, string $nameAr, string $branchName, array $facets): int
    {
        $nameAr = $this->arabicDigits($nameAr);

        return (int) DB::table('catalog_products')->insertGetId($facets + [
            'bim_code' => $this->nextCode(),
            'product_category_id' => $child->product_category_id,
            'product_category_child_id' => $child->id,
            'brand_id' => $brandId,
            'manufacturer_id' => $manufacturerId,
            'product_type' => 'simple',
            'name_ar' => $nameAr,
            'normalized_name_ar' => mb_strtolower($nameAr),
            'name_en' => $nameEn,
            'normalized_name_en' => mb_strtolower($nameEn),
            'short_name_ar' => $branchName,
            'image_alt_ar' => $nameAr,
            'image_alt_en' => $nameEn,
            'unit_id' => self::UNIT_ID,
            'country_code' => 'EG',
            'market_scope' => 'egypt',
            'is_verified_egypt' => 0,
            'verification_source' => 'research',
            'search_keywords' => trim($branchName . ' ' . ($facets['series'] ?? '')),
            'duplicate_status' => 'unique',
            'is_active' => 1,
            'approval_status' => 'approved',
            'sort_order' => 100,
            'curation_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** The catalog's own convention: «A١٥ ١٢٨ جيجا», not «A15 128 جيجا». */
    private function arabicDigits(string $text): string
    {
        /*
         * Only a standalone number turns Arabic-Indic («١٢٨ جيجا»). A digit
         * inside a Latin model code stays Western: «A١٧ Pro» splits into three
         * bidi runs in an RTL line and rendered as «(Pro A١٧)» on the
         * emulator, 2026-10-01. «A17», «S24», «M2», «5G» keep their digits.
         */
        return preg_replace_callback('/\S+/u', function ($m) {
            return preg_match('/[A-Za-z]/', $m[0]) ? $m[0] : $this->indic($m[0]);
        }, $text);
    }

    /** The first rule — every digit Arabic-Indic. Kept to recognise names it wrote. */
    private function legacyArabicDigits(string $text): string
    {
        return str_replace('٥G', '5G', $this->indic($text));
    }

    private function indic(string $text): string
    {
        return strtr($text, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);
    }

    private function renameIfUntouched(int $productId, string $nameAr): void
    {
        $new = $this->arabicDigits($nameAr);
        $current = (string) DB::table('catalog_products')->where('id', $productId)->value('name_ar');

        if ($current === $new || $current !== $this->legacyArabicDigits($nameAr)) {
            return;
        }

        DB::table('catalog_products')->where('id', $productId)->update([
            'name_ar' => $new,
            'normalized_name_ar' => mb_strtolower($new),
            'image_alt_ar' => $new,
            'updated_at' => now(),
        ]);
    }

    private function nextCode(): string
    {
        $last = (string) DB::table('catalog_products')->where('bim_code', 'like', 'BIM-RT-MOBI-%')->orderByDesc('bim_code')->value('bim_code');
        $n = $last === '' ? 0 : (int) Str::afterLast($last, '-');

        return sprintf('BIM-RT-MOBI-%03d', $n + 1);
    }

    /** @return array{attrs: array<string,int>, units: array<string,int>} */
    private function attributes(): array
    {
        // TechDeviceSpecsSeeder owns these rows; this seeder only reads them.
        $attrs = DB::table('catalog_attributes')
            ->whereIn('code', ['processor', 'ram_gb', 'storage', 'screen_inches', 'os'])
            ->pluck('id', 'code')->map(fn ($id) => (int) $id)->all();

        $units = DB::table('catalog_units')->whereIn('code', ['gb', 'inch'])->pluck('id', 'code')->map(fn ($id) => (int) $id)->all();

        return ['attrs' => $attrs, 'units' => $units];
    }

    private function writeSpecs(int $productId, array $spec, array $meta): void
    {
        [, $chip, $ram, $storage, $screen, $os] = $spec;

        $values = [
            'processor' => [null, $chip],
            'ram_gb' => [$ram, null],
            'storage' => [null, $storage],
            'screen_inches' => [$screen, null],
            'os' => [null, $os],
        ];

        foreach ($values as $code => [$number, $text]) {
            if (($number === null && $text === null) || ! isset($meta['attrs'][$code])) {
                continue;
            }

            $unit = $code === 'ram_gb' ? ($meta['units']['gb'] ?? null) : ($code === 'screen_inches' ? ($meta['units']['inch'] ?? null) : null);

            DB::table('catalog_product_attribute_values')->updateOrInsert(
                ['product_id' => $productId, 'attribute_id' => $meta['attrs'][$code], 'option_id' => null],
                [
                    'value_number' => $number === null ? null : (float) $number,
                    'value_text_ar' => null,
                    'value_text_en' => $text,
                    'unit_id' => $unit,
                    'sort_order' => 0,
                    'updated_at' => now(),
                ]
            );
        }
    }
}
