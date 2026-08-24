<?php

/*
 * Menu child→branch map — generated from the approved live configuration
 * (2026-07-12). Keyed by root slug + child name_ar; values are
 * platform_service_item_groups keys. Consumed by MenuChildBranchesSeeder.
 */

return [

    // ── مطاعم وكافيهات ──
    'restaurants-cafes' => [
        'أكل بيتى' => ['restaurant_menu'],
        'عربية قهوة ومأكولات' => ['restaurant_menu'],
        'كافيه' => ['restaurant_menu'],
        'مجمع مطاعم' => ['restaurant_menu'],
        'مطعم' => ['restaurant_menu'],
        'مطعم وكافيه' => ['restaurant_menu'],
    ],

    // ── المحلات أو أونلاين ──
    'shops-online' => [
        'أسماك' => ['fresh_market'],
        // 2026-08-24 — the meat counter, beside the fish one.
        'جزارة' => ['fresh_market'],
        // «بن يبيع حبوب فقط» (2026-08-10). The ruling was carried through to
        // the option bands — بن lost its four menu bands and kept «أقسام
        // البقالة الجافة» — but not to here, and this file is the other half:
        // beverages_drinks maps to «منيو» in the kinds collapse, so the shop
        // kept a drinks menu it had just been told it does not have. A ruling
        // applied on one axis and not the other is a ruling the next seed
        // undoes.
        'بن' => ['grocery_pantry'],
        'حلويات' => ['bakery_sweets'],
        'دواجن' => ['fresh_market'],
        'سوبر ماركت' => ['supermarket'],
        'عصائر' => ['beverages_drinks'],
        'خضار وفاكهة' => ['fresh_market'],
        'مجمدات' => ['fresh_market'],
        'مخابز' => ['bakery_sweets'],
        'منظفات' => ['household_personal'],
        'مني ماركت' => ['supermarket'],
        'هايبر ماركت' => ['supermarket'],
    ],
];
