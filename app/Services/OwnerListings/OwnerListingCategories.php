<?php

namespace App\Services\OwnerListings;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The categories an ordinary account may list ONE item in, what each one asks about the item, and which deals it allows.
 * A closed list in code: a category is a product decision (the owner gave «سيارة» and «شقة» and «أي غرض مستعمل»), not data a
 * user can grow.
 *
 * A field is one of: `text`, `int`, `select` (a fixed list in this file) or `option` (one row of a platform option
 * group — the car's kind and make — stored by id so the make is the platform's make, not a spelling).
 */
final class OwnerListingCategories
{
    public const CAR = 'car';
    public const PROPERTY = 'property';

    private const CONDITION = [
        'new' => ['جديد', 'New'],
        'like_new' => ['كالجديد', 'Like new'],
        'used' => ['مستعمل', 'Used'],
        'needs_repair' => ['يحتاج إصلاح', 'Needs repair'],
    ];

    /** key → [Arabic, English, icon, deals] — the goods share one set of fields. */
    private const GOODS = [
        'phones' => ['موبايلات وتابلت', 'Phones & tablets', 'smartphone'],
        'computers' => ['كمبيوتر ولابتوب', 'Computers & laptops', 'laptop'],
        'appliances' => ['أجهزة كهربائية', 'Appliances', 'kitchen'],
        'furniture' => ['أثاث ومفروشات', 'Furniture', 'chair'],
        'clothes' => ['ملابس وأحذية', 'Clothes & shoes', 'checkroom'],
        'kids' => ['مستلزمات أطفال', 'Kids & baby', 'child'],
        'sports' => ['رياضة وهوايات', 'Sports & hobbies', 'sports'],
        'tools' => ['عدد ومعدات', 'Tools & equipment', 'construction'],
        'other' => ['أغراض أخرى', 'Other items', 'category'],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_merge([self::CAR, self::PROPERTY], array_keys(self::GOODS));
    }

    public static function exists(string $key): bool
    {
        return in_array($key, self::keys(), true);
    }

    /** @return list<string> the deals this category allows: a flat or a car may be let, a phone may not. */
    public static function deals(string $key): array
    {
        return match ($key) {
            self::CAR => ['sale', 'rent', 'swap'],
            self::PROPERTY => ['sale', 'rent'],
            default => ['sale', 'swap'],
        };
    }

    /** @return array{0:string,1:string,2:string} [Arabic, English, icon] */
    public static function names(string $key): array
    {
        return match ($key) {
            self::CAR => ['سيارة', 'Car', 'directions_car'],
            self::PROPERTY => ['شقة وعقار', 'Flat & property', 'apartment'],
            default => self::GOODS[$key] ?? ['أغراض أخرى', 'Other items', 'category'],
        };
    }

    public static function name(string $key, bool $english): string
    {
        $n = self::names($key);

        return $english ? $n[1] : $n[0];
    }

    /**
     * The fields of a category.
     *
     * @return list<array{key:string,type:string,label:array{0:string,1:string},required?:bool,min?:int,max?:int,options?:array<string,array{0:string,1:string}>,group?:string,deals?:list<string>}>
     */
    public static function fields(string $key): array
    {
        $year = (int) now()->year + 1;

        return match ($key) {
            self::CAR => [
                ['key' => 'vehicle_type', 'type' => 'option', 'label' => ['نوع السيارة', 'Kind of car'], 'group' => 'نوع المركبة', 'required' => true],
                ['key' => 'brand', 'type' => 'option', 'label' => ['الماركة', 'Make'], 'group' => 'ماركات السيارات', 'required' => true],
                ['key' => 'year', 'type' => 'int', 'label' => ['سنة الصنع', 'Year'], 'min' => 1980, 'max' => $year, 'required' => true],
                ['key' => 'mileage_km', 'type' => 'int', 'label' => ['عداد الكيلومترات', 'Mileage (km)'], 'min' => 0, 'max' => 2000000, 'deals' => ['sale', 'swap']],
                ['key' => 'transmission', 'type' => 'select', 'label' => ['ناقل الحركة', 'Transmission'], 'required' => true, 'options' => ['automatic' => ['أوتوماتيك', 'Automatic'], 'manual' => ['يدوي', 'Manual']]],
                ['key' => 'condition', 'type' => 'select', 'label' => ['الحالة', 'Condition'], 'options' => ['excellent' => ['ممتازة', 'Excellent'], 'good' => ['جيدة', 'Good'], 'needs_work' => ['تحتاج صيانة', 'Needs work']]],
            ],
            self::PROPERTY => [
                ['key' => 'property_type', 'type' => 'select', 'label' => ['نوع العقار', 'Kind of property'], 'required' => true, 'options' => [
                    'apartment' => ['شقة', 'Apartment'], 'villa' => ['فيلا', 'Villa'], 'floor' => ['دور', 'Floor'],
                    'shop' => ['محل', 'Shop'], 'office' => ['مكتب', 'Office'], 'land' => ['أرض', 'Land'],
                ]],
                ['key' => 'area_m2', 'type' => 'int', 'label' => ['المساحة (م²)', 'Area (m²)'], 'min' => 10, 'max' => 1000000, 'required' => true],
                ['key' => 'rooms', 'type' => 'int', 'label' => ['عدد الغرف', 'Rooms'], 'min' => 0, 'max' => 30],
                ['key' => 'bathrooms', 'type' => 'int', 'label' => ['عدد الحمامات', 'Bathrooms'], 'min' => 0, 'max' => 20],
                ['key' => 'floor', 'type' => 'int', 'label' => ['الدور', 'Floor'], 'min' => -2, 'max' => 80],
                ['key' => 'finishing', 'type' => 'select', 'label' => ['التشطيب', 'Finishing'], 'options' => [
                    'super_lux' => ['سوبر لوكس', 'Super lux'], 'lux' => ['لوكس', 'Lux'], 'semi' => ['نصف تشطيب', 'Semi-finished'], 'bare' => ['على الطوب', 'Bare shell'],
                ]],
                ['key' => 'furnished', 'type' => 'select', 'label' => ['مفروشة', 'Furnished'], 'deals' => ['rent'], 'options' => ['yes' => ['نعم', 'Yes'], 'no' => ['لا', 'No']]],
            ],
            default => [
                ['key' => 'condition', 'type' => 'select', 'label' => ['الحالة', 'Condition'], 'required' => true, 'options' => self::CONDITION],
                ['key' => 'brand', 'type' => 'text', 'label' => ['الماركة', 'Brand']],
                ['key' => 'model', 'type' => 'text', 'label' => ['الموديل', 'Model']],
            ],
        };
    }

    /**
     * The categories and their fields as the app draws them: names in the caller's language, the option lists filled.
     *
     * @return list<array<string,mixed>>
     */
    public static function forApi(bool $english): array
    {
        return collect(self::keys())->map(function (string $key) use ($english) {
            [, , $icon] = self::names($key);

            return [
                'key' => $key,
                'name' => self::name($key, $english),
                'icon' => $icon,
                'deals' => self::deals($key),
                'fields' => collect(self::fields($key))->map(fn (array $f) => array_filter([
                    'key' => $f['key'],
                    'type' => $f['type'] === 'option' ? 'select' : $f['type'],
                    'label' => $english ? $f['label'][1] : $f['label'][0],
                    'required' => ! empty($f['required']),
                    'min' => $f['min'] ?? null,
                    'max' => $f['max'] ?? null,
                    'deals' => $f['deals'] ?? null,
                    'options' => self::optionsOf($f, $english),
                ], fn ($v) => $v !== null && $v !== []))->values()->all(),
            ];
        })->all();
    }

    /**
     * @param  array<string,mixed>  $field
     * @return list<array{value:string,label:string}>|null
     */
    private static function optionsOf(array $field, bool $english): ?array
    {
        if ($field['type'] === 'select') {
            return collect($field['options'])->map(fn ($n, $v) => ['value' => (string) $v, 'label' => $english ? $n[1] : $n[0]])->values()->all();
        }

        if ($field['type'] === 'option') {
            return DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')->where('g.name_ar', $field['group'])
                ->orderBy('o.name_ar')->get(['o.id', 'o.name_ar', 'o.name_en'])
                ->map(fn ($o) => ['value' => (string) $o->id, 'label' => (string) ($english ? ($o->name_en ?: $o->name_ar) : ($o->name_ar ?: $o->name_en))])
                ->values()->all();
        }

        return null;
    }

    /**
     * Check what the owner sent against the category's fields (for the deal he chose) and keep only the known ones.
     *
     * @param  array<string,mixed>  $input
     * @return array<string,mixed>
     */
    public static function clean(string $key, string $deal, array $input): array
    {
        $out = [];
        $errors = [];

        foreach (self::fields($key) as $field) {
            if (isset($field['deals']) && ! in_array($deal, $field['deals'], true)) {
                continue;
            }

            $name = $field['key'];
            $value = $input[$name] ?? null;
            $value = is_string($value) ? trim($value) : $value;

            if ($value === null || $value === '') {
                if (! empty($field['required'])) {
                    $errors["attributes.$name"] = __('هذا الحقل مطلوب.');
                }

                continue;
            }

            switch ($field['type']) {
                case 'int':
                    if (! is_numeric($value) || (int) $value != $value || (int) $value < ($field['min'] ?? PHP_INT_MIN) || (int) $value > ($field['max'] ?? PHP_INT_MAX)) {
                        $errors["attributes.$name"] = __('قيمة غير صالحة.');
                    } else {
                        $out[$name] = (int) $value;
                    }
                    break;

                case 'select':
                    if (! array_key_exists((string) $value, $field['options'])) {
                        $errors["attributes.$name"] = __('اختر من القائمة.');
                    } else {
                        $out[$name] = (string) $value;
                    }
                    break;

                case 'option':
                    $ok = DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
                        ->where('g.name_ar', $field['group'])->where('o.id', (int) $value)->exists();

                    if (! $ok) {
                        $errors["attributes.$name"] = __('اختر من القائمة.');
                    } else {
                        $out[$name] = (int) $value;
                    }
                    break;

                default:
                    $out[$name] = mb_substr((string) $value, 0, 80);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    /**
     * The attributes in words, for a card or a page: `[{key,label,value}]` in the caller's language.
     *
     * @param  array<string,mixed>  $attributes
     * @return list<array{key:string,label:string,value:string}>
     */
    public static function describe(string $key, array $attributes, bool $english): array
    {
        $rows = [];

        foreach (self::fields($key) as $field) {
            $value = $attributes[$field['key']] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $text = match ($field['type']) {
                'select' => isset($field['options'][(string) $value]) ? ($english ? $field['options'][(string) $value][1] : $field['options'][(string) $value][0]) : (string) $value,
                'option' => (string) (($row = DB::table('options')->where('id', (int) $value)->first(['name_ar', 'name_en'])) ? ($english ? ($row->name_en ?: $row->name_ar) : ($row->name_ar ?: $row->name_en)) : $value),
                default => (string) $value,
            };

            $rows[] = ['key' => $field['key'], 'label' => $english ? $field['label'][1] : $field['label'][0], 'value' => $text];
        }

        return $rows;
    }
}
