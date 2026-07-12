<?php

/*
 * Retail child→branch map. Keyed by root slug + child name_ar (exactly as the
 * strings appear in category_children_master); values are retail
 * platform_service_item_groups keys. Consumed by RetailChildBranchesSeeder.
 *
 * Coverage (docs/retail-branches-taxonomy.md):
 *   - exhibitions (root 21): all 29 children — the previously service-less
 *     showrooms.
 *   - shops-online (root 17): every non-food child. The 12 pure-food children
 *     (أسماك، بن، مخابز، حلويات، خضروات، دواجن، سوبر ماركت، عصائر، فواكة،
 *     مجمدات، مني ماركت، هايبر ماركت) and استوديوهات are intentionally omitted —
 *     they stay on Menu. منظفات is included (household_cleaners) and keeps its
 *     Menu link too.
 *
 * Duplicate-named children (e.g. أجهزة رياضية appears twice under exhibitions)
 * are listed once; the seeder matches every id carrying that name.
 */

return [

    // ── معارض (showrooms) ──
    'exhibitions' => [
        'أجهزة رياضية' => ['electronics_tech'],
        'ألمونتال' => ['home_furnishings'],
        'أنتيكات وتحف' => ['home_furnishings'],
        'سجاد' => ['home_furnishings'],
        'نجف و تحف' => ['home_furnishings'],
        'ملابس جاهزة' => ['fashion_textiles'],
        'أجهزه كمبيوتر' => ['electronics_tech'],
        'أدوات تجميل' => ['beauty_health_retail'],
        'أجهزة كهربائية' => ['electronics_tech'],
        'أقمشة' => ['fashion_textiles'],
        'مفروشات' => ['home_furnishings'],
        'آثاث' => ['home_furnishings'],
        'زجاج' => ['home_furnishings'],
        'صيني ومستلزمات بيت' => ['home_furnishings'],
        'جلود وشنط وأحذية' => ['fashion_textiles'],
        'رخام' => ['building_hardware'],
        'مراتب' => ['home_furnishings'],
        'معرض سيارات' => ['vehicles_parts'],
        'معرض موتوسيكلات' => ['vehicles_parts'],
        'حدايد وبويات' => ['building_hardware'],
        'حلويات' => ['hobbies_general'],
        'صينى وخزف' => ['home_furnishings'],
        'مستلزمات مطاعم' => ['hobbies_general'],
        'سيفتى ومقاومة حرائق' => ['building_hardware'],
        'إسفنج' => ['home_furnishings'],
        'لعب أطفال' => ['hobbies_general'],
        'أصواف' => ['fashion_textiles'],
    ],

    // ── المحلات أو أونلاين (shops, non-food) ──
    'shops-online' => [
        'أجهزة رياضية' => ['electronics_tech'],
        'لوازم ستائر' => ['home_furnishings'],
        'ألمونتال' => ['home_furnishings'],
        'أنتيكات وتحف' => ['home_furnishings'],
        'كتب' => ['hobbies_general'],
        'تجهيز عرائس' => ['fashion_textiles'],
        'مستلزمات كافيهات' => ['hobbies_general'],
        'اكسسوارت سيارات' => ['vehicles_parts'],
        'زيت سيارات' => ['vehicles_parts'],
        'قطع غيار سيارات' => ['vehicles_parts'],
        'مستلزمات نجارة' => ['building_hardware'],
        'سجاد' => ['home_furnishings'],
        'اسمنت' => ['building_hardware'],
        'نجف و تحف' => ['home_furnishings'],
        'أجهزه كمبيوتر' => ['electronics_tech'],
        'أدوات تجميل' => ['beauty_health_retail'],
        'ستائر و ديكور' => ['home_furnishings'],
        'نباتات طبيعية وزينة' => ['hobbies_general'],
        'منظفات' => ['hobbies_general'],
        'أدوات كهربائية' => ['building_hardware'],
        'أجهزة كهربائية' => ['electronics_tech'],
        'أقمشة' => ['fashion_textiles'],
        'مفروشات' => ['home_furnishings'],
        'نظارات' => ['fashion_textiles'],
        'زجاج' => ['home_furnishings'],
        'ذهب' => ['jewelry'],
        'صيني ومستلزمات بيت' => ['home_furnishings'],
        'كبس خراطيم' => ['building_hardware'],
        'أدوات صيد' => ['hobbies_general'],
        'مفاتيح' => ['building_hardware'],
        'رخام' => ['building_hardware'],
        'مراتب' => ['home_furnishings'],
        'مستلزمات طبية' => ['beauty_health_retail'],
        'موبيلات و اكسسوار' => ['electronics_tech'],
        'حدايد وبويات' => ['building_hardware'],
        'عطور' => ['beauty_health_retail'],
        'اكياس بلاستيك' => ['building_hardware'],
        'بلاستيك' => ['building_hardware'],
        'أجهزة بلايستيشن' => ['electronics_tech'],
        'صينى وخزف' => ['home_furnishings'],
        'جنوط وكاوتش سيارات' => ['vehicles_parts'],
        'سيفتى ومقاومة حرائق' => ['building_hardware'],
        'فضة' => ['jewelry'],
        'مشتقات التدخين' => ['hobbies_general'],
        'حديد تسليح' => ['building_hardware'],
        'قطع غيار أجهزة كهربائية' => ['electronics_tech'],
        'إسفنج' => ['home_furnishings'],
        'أدوات مكتبية' => ['hobbies_general'],
        'لعب أطفال' => ['hobbies_general'],
        'مصنوعات خشبية ومستلزمات ديكور' => ['home_furnishings'],
        'أصواف' => ['fashion_textiles'],
    ],
];
