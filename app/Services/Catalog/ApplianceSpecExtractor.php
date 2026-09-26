<?php

namespace App\Services\Catalog;

/**
 * Derives the structured spec rows of an electrical-appliance catalog master
 * from its English name («Toshiba El Araby No-Frost Refrigerator 16ft»), so
 * the product page can show a spec table instead of a run-on title.
 *
 * Pure: takes a name, returns [attribute code => value]. A number value is
 * ['number' => 16.0]; a text value is ['ar' => ..., 'en' => ...].
 */
final class ApplianceSpecExtractor
{
    /** Attribute code => [name_ar, name_en, data_type, unit code|null, sort_order]. */
    public const ATTRIBUTES = [
        'appliance_type' => ['نوع الجهاز', 'Appliance type', 'text', null, 100],
        'operation_type' => ['نوع التشغيل', 'Operation', 'text', null, 110],
        'capacity_cu_ft' => ['السعة', 'Capacity', 'number', 'cu_ft', 120],
        'wash_capacity_kg' => ['سعة الغسيل', 'Wash capacity', 'number', 'kg', 121],
        'capacity_liters' => ['السعة', 'Capacity', 'number', 'liter', 122],
        'power_hp' => ['القدرة', 'Power', 'number', 'hp', 130],
        'screen_inches' => ['حجم الشاشة', 'Screen size', 'number', 'inch', 140],
        'drawers' => ['عدد الأدراج', 'Drawers', 'number', 'drawer', 150],
        'burners' => ['عدد الشعلات', 'Burners', 'number', 'burner', 151],
        'place_settings' => ['عدد الأفراد', 'Place settings', 'number', 'place_setting', 152],
    ];

    /** Unit code => [name_ar, name_en]. Codes already in catalog_units (kg, liter) are reused. */
    public const UNITS = [
        'cu_ft' => ['قدم', 'cu ft'],
        'hp' => ['حصان', 'HP'],
        'inch' => ['بوصة', 'inch'],
        'drawer' => ['درج', 'drawers'],
        'burner' => ['شعلة', 'burners'],
        'place_setting' => ['فرد', 'settings'],
    ];

    /** English keyword (lowercase) => [ar, en] appliance type, most specific first. */
    private const TYPES = [
        'refrigerator' => ['ثلاجة', 'Refrigerator'],
        'deep freezer' => ['ديب فريزر', 'Deep freezer'],
        'washing machine' => ['غسالة ملابس', 'Washing machine'],
        'dishwasher' => ['غسالة أطباق', 'Dishwasher'],
        'air conditioner' => ['تكييف', 'Air conditioner'],
        'water heater' => ['سخان مياه', 'Water heater'],
        'microwave' => ['ميكروويف', 'Microwave oven'],
        'cooker' => ['بوتاجاز', 'Cooker'],
        'smart tv' => ['شاشة سمارت', 'Smart TV'],
        'electric heater' => ['دفاية كهربائية', 'Electric heater'],
        'stand fan' => ['مروحة عمود', 'Stand fan'],
        'blender' => ['خلاط', 'Blender'],
    ];

    /** Operation words => [ar, en], most specific first. */
    private const OPERATIONS = [
        'semi-automatic' => ['نصف أوتوماتيك', 'Semi-automatic'],
        'fully automatic' => ['فول أوتوماتيك', 'Fully automatic'],
        'automatic' => ['أوتوماتيك', 'Automatic'],
        'no-frost' => ['نوفروست', 'No-Frost'],
        'split' => ['سبليت', 'Split'],
        'electric' => ['كهرباء', 'Electric'],
        'gas' => ['غاز', 'Gas'],
    ];

    /** @return array<string, array{number?: float, ar?: string, en?: string}> */
    public function extract(string $nameEn): array
    {
        $name = strtolower($nameEn);
        $out = [];

        foreach (self::TYPES as $keyword => [$ar, $en]) {
            if (str_contains($name, $keyword)) {
                $out['appliance_type'] = ['ar' => $ar, 'en' => $en];
                break;
            }
        }

        foreach (self::OPERATIONS as $keyword => [$ar, $en]) {
            if (str_contains($name, $keyword)) {
                $out['operation_type'] = ['ar' => $ar, 'en' => $en];
                break;
            }
        }

        $numbers = [
            'capacity_cu_ft' => '/(\d+(?:\.\d+)?)\s*ft\b/',
            'wash_capacity_kg' => '/(\d+(?:\.\d+)?)\s*kg\b/',
            'capacity_liters' => '/(\d+(?:\.\d+)?)\s*l\b/',
            'power_hp' => '/(\d+(?:\.\d+)?)\s*hp\b/',
            'screen_inches' => '/(\d+(?:\.\d+)?)\s*inch\b/',
            'drawers' => '/(\d+)\s*drawers?\b/',
            'burners' => '/(\d+)\s*burners?\b/',
            'place_settings' => '/(\d+)\s*place settings?\b/',
        ];

        foreach ($numbers as $code => $pattern) {
            if (preg_match($pattern, $name, $m)) {
                $out[$code] = ['number' => (float) $m[1]];
            }
        }

        return $out;
    }
}
