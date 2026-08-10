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
 *   - سوبر ماركت (2026-08-01) is the one exception to the food exclusion above:
 *     it now ALSO gets 'grocery_retail' (additive — its existing Menu link is
 *     untouched) so it can list SKUs from the shared catalog_products master
 *     with price+stock via business_catalog_listings, alongside its free-form
 *     Menu items. See [[retail-service-build]] / the grocery_retail branch in
 *     retail_taxonomy.php for why this uses a distinct key, not 'supermarket'.
 *
 *   - cloth-accessories / companies / factories (2026-08-04): the roots that
 *     were never given a selling surface at all. Most of their children are the
 *     SAME category_children_master rows already classified above — the map is
 *     keyed per ROOT, so «أقمشة» under معارض was wired while «أقمشة» under
 *     مصانع was not, and admin/discovery read per root. Services (شحن،
 *     استيراد وتصدير، طباعة، نقل دولي، مصاعد) are deliberately excluded: they
 *     are booked, not stocked.
 *
 * Duplicate-named children (e.g. أجهزة رياضية appears twice under exhibitions)
 * are listed once; the seeder matches every id carrying that name.
 *
 * A child may only be given a branch that actually HAS a matching item type —
 * the branch's whole type list becomes the child's allowed_item_types, so a
 * branch with nothing relevant in it hands the merchant an empty product
 * picker. «حلويات» is the standing example: it is mapped to hobbies_general
 * above, which carries toys, books and stationery and no sweets type at all,
 * so it was NOT propagated to the new roots.
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
        'زيت سيارات' => ['vehicles_parts'],
        'قطع غيار سيارات' => ['vehicles_parts'],
        'مستلزمات نجارة' => ['building_hardware'],
        // 2026-08-10: the trade took this root (TradeAxesSeeder). Without the
        // entry a full seed would leave the shop standing here with a retail
        // config nothing bounds — see the note in the companies block below.
        'باب وشباك' => ['building_hardware'],
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
        'سوبر ماركت' => ['grocery_retail'],
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
        // «اكسسوارت سيارات» / «اكسسوار موبيلات» folded into «اكسسوار» on
        // 2026-08-10 (AccessoryMergeSeeder) and became two of its options. The
        // trade took this root in their place — it sells phone cases, car mats
        // and watches off one counter, which is the electronics shelf here.
        'اكسسوار' => ['electronics_tech'],
    ],

    // ── ملابس و اكسسوارات (root 14) ──
    // The whole root sells clothing off a rail. It had no selling surface at
    // all — only delivery and offers, which move and advertise goods a
    // merchant had no way to list in the first place.
    'cloth-accessories' => [
        'ملابس' => ['fashion_textiles'],
        'ملابس كاجوال' => ['fashion_textiles'],
        'ملابس رسمي' => ['fashion_textiles'],
        'ملابس النوم' => ['fashion_textiles'],
        'ملابس رياضية' => ['fashion_textiles'],
        'ملابس زفاف' => ['fashion_textiles'],
        'كوتشي' => ['fashion_textiles'],
        'اكسسوار' => ['fashion_textiles'],
        'جلود وشنط وأحذية' => ['fashion_textiles'],
    ],

    // ── شركات (root 22) ──
    // A trading company sells the same standard goods a shop does; the child is
    // usually the SAME row already classified under معارض/المحلات, and was only
    // missed because this map was keyed per root. Services (استيراد وتصدير،
    // نقل دولي، شحن، طباعة، مصاعد) are deliberately absent — they are booked,
    // not stocked.
    'companies' => [
        'أجهزة كهربائية' => ['electronics_tech'],
        'أجهزه كمبيوتر' => ['electronics_tech'],
        'أجهزة رياضية' => ['electronics_tech'],
        'تبريد وتكييف' => ['electronics_tech'],
        'أدوات تجميل' => ['beauty_health_retail'],
        'مستلزمات طبية' => ['beauty_health_retail'],
        'مواد دوائية' => ['beauty_health_retail'],
        'أقمشة' => ['fashion_textiles'],
        'ملابس جاهزة' => ['fashion_textiles'],
        'جلود وشنط وأحذية' => ['fashion_textiles'],
        'أصواف' => ['fashion_textiles'],
        'اكسسوار' => ['fashion_textiles'],
        'ألمونتال' => ['home_furnishings'],
        'أنتيكات وتحف' => ['home_furnishings'],
        'سجاد' => ['home_furnishings'],
        'زجاج' => ['home_furnishings'],
        'صيني ومستلزمات بيت' => ['home_furnishings'],
        'صينى وخزف' => ['home_furnishings'],
        'مراتب' => ['home_furnishings'],
        'إسفنج' => ['home_furnishings'],
        'لوازم ستائر' => ['home_furnishings'],
        'نجف' => ['home_furnishings'],
        'اسمنت' => ['building_hardware'],
        'حديد تسليح' => ['building_hardware'],
        'حدايد وبويات' => ['building_hardware'],
        'رخام' => ['building_hardware'],
        'مفاتيح' => ['building_hardware'],
        'كبس خراطيم' => ['building_hardware'],
        'مستلزمات نجارة' => ['building_hardware'],
        'سيفتى ومقاومة حرائق' => ['building_hardware'],
        'أخشاب' => ['building_hardware'],
        'أبواب مصفحة' => ['building_hardware'],
        // 2026-08-10: «باب وشباك» #50 now stands under شركات too — the trade
        // itself, beside the single product «أبواب مصفحة» that used to be the
        // only way to register a doors company. This map is keyed per ROOT, so
        // its entry under مصانع does not reach here.
        'باب وشباك' => ['building_hardware'],
        'طوب' => ['building_hardware'],
        'أدوات صحية' => ['building_hardware'],
        'مواد تعبئة وتغليف' => ['building_hardware'],
        'قطع غيار' => ['vehicles_parts'],
        'منظفات' => ['hobbies_general'],
        'أدوات مكتبية' => ['hobbies_general'],
        'لعب أطفال' => ['hobbies_general'],
        'مستلزمات مطاعم' => ['hobbies_general'],
        'مستلزمات قهاوى' => ['hobbies_general'],
        'مواد غذائية ومنظفات' => ['grocery_retail'],
        'عصائر' => ['grocery_retail'],
    ],

    // ── مصانع (root 23) ──
    // A factory sells what it makes. Where the output is a standard, branded,
    // repeatable SKU it belongs here; where it is bespoke (أثاث، مفروشات، نجف
    // و تحف) it already has the LISTING surface instead — see
    // listing_service_children.php. Both can be on at once.
    'factories' => [
        'أجهزة كهربائية' => ['electronics_tech'],
        'أجهزه كمبيوتر' => ['electronics_tech'],
        'أدوات تجميل' => ['beauty_health_retail'],
        'مستلزمات طبية' => ['beauty_health_retail'],
        'مواد دوائية' => ['beauty_health_retail'],
        'أقمشة' => ['fashion_textiles'],
        'ملابس جاهزة' => ['fashion_textiles'],
        'جلود وشنط وأحذية' => ['fashion_textiles'],
        'أصواف' => ['fashion_textiles'],
        'اكسسوار' => ['fashion_textiles'],
        'سجاد' => ['home_furnishings'],
        'زجاج' => ['home_furnishings'],
        'صيني ومستلزمات بيت' => ['home_furnishings'],
        'صينى وخزف' => ['home_furnishings'],
        'مراتب' => ['home_furnishings'],
        'إسفنج' => ['home_furnishings'],
        'نجف' => ['home_furnishings'],
        'اسمنت' => ['building_hardware'],
        'حديد تسليح' => ['building_hardware'],
        'حدايد وبويات' => ['building_hardware'],
        'رخام وجرانيت' => ['building_hardware'],
        'مفاتيح' => ['building_hardware'],
        'كبس خراطيم' => ['building_hardware'],
        'مستلزمات نجارة' => ['building_hardware'],
        'سيفتى ومقاومة حرائق' => ['building_hardware'],
        'اكياس بلاستيك' => ['building_hardware'],
        'بي في سي' => ['building_hardware'],
        'أخشاب' => ['building_hardware'],
        'باب وشباك' => ['building_hardware'],
        'طوب' => ['building_hardware'],
        'أدوات صحية' => ['building_hardware'],
        'مواد تعبئة وتغليف' => ['building_hardware'],
        'طباعة مواد تعبئة وتغليف' => ['building_hardware'],
        'قطع غيار سيارات' => ['vehicles_parts'],
        'منظفات' => ['hobbies_general'],
        'لعب أطفال' => ['hobbies_general'],
        'مستلزمات مطاعم' => ['hobbies_general'],
        'مواد غذائية' => ['grocery_retail'],
        'عصائر' => ['grocery_retail'],
        // «اكسسوارت سيارات» / «اكسسوار موبيلات» folded into «اكسسوار» on
        // 2026-08-10 (AccessoryMergeSeeder) and became two of its options.
    ],
];
