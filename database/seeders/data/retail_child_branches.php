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
 * a branch with nothing relevant in it hands the merchant an empty product
 * picker. «حلويات» was the standing example: mapped to hobbies_general, which
 * carries toys, books and stationery and no sweets type at all, and for that
 * reason NOT propagated to the new roots. It moved to grocery_retail on
 * 2026-08-11, matching what مصانع already said, and the example is now a
 * closed one.
 *
 * The branch is no longer the whole answer, either. data/retail_child_types.php
 * narrows a trade to the part of its shelf it actually sells, because a branch
 * is a shelf and not a shop: أثاث ومفروشات carries twelve types over eleven
 * trades. Adding a child here without adding it there gives it the whole shelf.
 */

return [

    // ── معارض (showrooms) ──
    /*
    | «معرض سيارات» #188 moved معارض → سيارات on 2026-08-17, folding «سيارات»
    | #53 into itself first — owner: «خليه معرض سيارات ونفذ الطى والنقل». The
    | shelf does not change with the root: a showroom lists cars either way.
    | The root had no retail block at all before this.
    */
    'cars' => [
        'معرض سيارات' => ['vehicles_parts'],
    ],

    'exhibitions' => [
        // Stood under «معارض» and «المحلات» on 2026-08-16 — the two roots a
        // customer actually looks for a bathroom shop in, and it was in
        // neither. Same shelf and same branch as under شركات and مصانع.
        'سيراميك وأدوات صحية' => ['building_hardware'],
        'أجهزة رياضية' => ['electronics_tech'],
        'ألمونتال' => ['home_furnishings'],
        // The trade took this root on 2026-08-12 so the two «ألمونتال»
        // showroom accounts had somewhere to move to. A doors showroom sells
        // building hardware, the same as its other three standings.
        'باب وشباك' => ['building_hardware'],
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
        'جلود وشنط وأحذية' => ['fashion_textiles'],
        'رخام' => ['building_hardware'],
        'مراتب' => ['home_furnishings'],
        // «معرض سيارات» left this root for «سيارات» on 2026-08-17 and is named
        // in the `cars` block above. A line left here would re-file it.
        'معرض موتوسيكلات' => ['vehicles_parts'],
        'حدايد وبويات' => ['building_hardware'],
        // hobbies_general until 2026-08-11, which the header of this file has
        // called out as the standing example of a wrong branch since the day
        // it was written: it carries toys, books, stationery and fishing gear
        // and no sweets type at all. Under مصانع the same trade was already on
        // grocery_retail, where chocolate and biscuits live; the two roots now
        // agree.
        'حلويات' => ['grocery_retail'],
        'صينى وخزف' => ['home_furnishings'],
        'مستلزمات مطاعم وكافيهات' => ['hobbies_general'],
        'سيفتى ومقاومة حرائق' => ['building_hardware'],
        'إسفنج' => ['home_furnishings'],
        'لعب أطفال' => ['hobbies_general'],
        'أصواف' => ['fashion_textiles'],
        // «سيارات» #53 was folded into «معرض سيارات» on 2026-08-17 and is
        // retired; the narrowing this line carried lives on in the `cars`
        // block above, on the child that survived.
    ],

    // ── المحلات أو أونلاين (shops, non-food) ──
    'shops-online' => [
        // Stood under «معارض» and «المحلات» on 2026-08-16 — the two roots a
        // customer actually looks for a bathroom shop in, and it was in
        // neither. Same shelf and same branch as under شركات and مصانع.
        'سيراميك وأدوات صحية' => ['building_hardware'],
        /*
        | The eight food shops the owner put on retail from the bulk screen on
        | 2026-08-16 03:53. Retail was switched on and no branch named them, so
        | each took whatever the expansion happened to give it — «أسماك» came
        | out offering `gaming_consoles`.
        |
        | All eight are grocery trades and `grocery_retail` is the food shelf,
        | so that is the branch. What each may actually stock is narrowed per
        | child in `retail_child_types.php`, which already named most of them —
        | «أسماك» has said `frozen, canned` there since the branch was built and
        | nothing could reach it.
        */
        'مخابز' => ['grocery_retail'],
        'بن' => ['grocery_retail'],
        'أسماك' => ['grocery_retail'],
        'مجمدات' => ['grocery_retail'],
        'خضار وفاكهة' => ['grocery_retail'],
        'عصائر' => ['grocery_retail'],
        'حلويات' => ['grocery_retail'],
        'دواجن' => ['grocery_retail'],

        'أجهزة رياضية' => ['electronics_tech'],
        'لوازم ستائر' => ['home_furnishings'],
        'ألمونتال' => ['home_furnishings'],
        'أنتيكات وتحف' => ['home_furnishings'],
        'كتب' => ['hobbies_general'],
        'مستلزمات مطاعم وكافيهات' => ['hobbies_general'],   // «مستلزمات كافيهات»/«مستلزمات قهاوى» folded in 2026-08-23
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
        'كبس خراطيم' => ['building_hardware'],
        'أدوات صيد' => ['hobbies_general'],
        'مفاتيح' => ['building_hardware'],
        'رخام' => ['building_hardware'],
        'مراتب' => ['home_furnishings'],
        'سوبر ماركت' => ['grocery_retail'],
        // «المني والهايبر بقالة مش مطاعم» (2026-08-10) moved these two onto the
        // grocery shelf via ServiceReinstatementSeeder pass 5. The ruling was
        // applied but never written down here, and a config this file does not
        // name is a config the branch map cannot correct later. All 22 grocery
        // types on purpose — a market IS the whole shelf.
        'مني ماركت' => ['grocery_retail'],
        'هايبر ماركت' => ['grocery_retail'],
        'مستلزمات طبية' => ['beauty_health_retail'],
        // Missing entirely until 2026-08-10, which is the whole story: a child
        // this file does not name still gets a retail config, from
        // ChildRootMovesSeeder::adoptRootShape() copying the ROOT'S MAJORITY.
        // Under المحلات that majority is أثاث ومفروشات, so the supplements shop
        // was offered furniture, chandeliers, carpets and mattresses. The move
        // entry that brought it here already said «بجوار عطور وأدوات تجميل».
        'مكملات غذائية' => ['beauty_health_retail'],
        'موبيلات و اكسسوار' => ['electronics_tech'],
        'حدايد وبويات' => ['building_hardware'],
        'عطور' => ['beauty_health_retail'],
        'مواد تعبئة وتغليف' => ['building_hardware'],   // «اكياس بلاستيك» folded in 2026-08-23
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
        // «تجهيز عرائس» / «أبواب مصفحة» folded on 2026-08-10 — each was
        // already an OPTION on a sibling under the same root. See
        // data/child_root_detachments.php.
    ],

    // ── ملابس و اكسسوارات (root 14) ──
    // The whole root sells clothing off a rail. It had no selling surface at
    // all — only delivery and offers, which move and advertise goods a
    // merchant had no way to list in the first place.
    'cloth-accessories' => [
        'ملابس' => ['fashion_textiles'],
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
        'صينى وخزف' => ['home_furnishings'],
        'مراتب' => ['home_furnishings'],
        'إسفنج' => ['home_furnishings'],
        'لوازم ستائر' => ['home_furnishings'],
        'نجف' => ['home_furnishings'],
        'حديد تسليح' => ['building_hardware'],
        'حدايد وبويات' => ['building_hardware'],
        'رخام' => ['building_hardware'],
        // «مفاتيح» and «حلويات» were detached from «شركات» on 2026-08-16 —
        // a key-cutter is a one-man bench and a sweets shop is a kitchen.
        // Their lines go with them: this map is keyed by ROOT.
        'كبس خراطيم' => ['building_hardware'],
        'مستلزمات نجارة' => ['building_hardware'],
        'سيفتى ومقاومة حرائق' => ['building_hardware'],
        'أخشاب' => ['building_hardware'],
        // 2026-08-10: «باب وشباك» #50 now stands under شركات too — the trade
        // itself, beside the single product «أبواب مصفحة» that used to be the
        // only way to register a doors company. This map is keyed per ROOT, so
        // its entry under مصانع does not reach here.
        'باب وشباك' => ['building_hardware'],
        'سيراميك وأدوات صحية' => ['building_hardware'],
        'مواد تعبئة وتغليف' => ['building_hardware'],
        'قطع غيار' => ['vehicles_parts'],
        // «قطع غيار سيارات» #44 also stands here and was not named — the
        // sibling «قطع غيار» above is a different row, so the per-root miss
        // was invisible. It held all six vehicle types, whole cars included.
        'قطع غيار سيارات' => ['vehicles_parts'],
        'منظفات' => ['hobbies_general'],
        'أدوات مكتبية' => ['hobbies_general'],
        'لعب أطفال' => ['hobbies_general'],
        'مستلزمات مطاعم وكافيهات' => ['hobbies_general'],
        'مواد غذائية ومنظفات' => ['grocery_retail'],
        'عصائر' => ['grocery_retail'],
        // «تجهيز عرائس» / «أبواب مصفحة» folded on 2026-08-10 — each was
        // already an OPTION on a sibling under the same root. See
        // data/child_root_detachments.php.
    ],

    // ── مصانع (root 23) ──
    // A factory sells what it makes. Where the output is a standard, branded,
    // repeatable SKU it belongs here; where it is bespoke (أثاث، مفروشات، نجف
    // و تحف) it already has the LISTING surface instead — see
    // listing_service_children.php. Both can be on at once.
    'factories' => [
        'أجهزة كهربائية' => ['electronics_tech'],
        'أجهزه كمبيوتر' => ['electronics_tech'],
        // Unnamed here until 2026-08-11 and so left on the whole electronics
        // shelf: a sports-equipment factory offered laptops and playstations.
        'أجهزة رياضية' => ['electronics_tech'],
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
        'صينى وخزف' => ['home_furnishings'],
        'مراتب' => ['home_furnishings'],
        'إسفنج' => ['home_furnishings'],
        'نجف' => ['home_furnishings'],
        'اسمنت' => ['building_hardware'],
        'حديد تسليح' => ['building_hardware'],
        'حدايد وبويات' => ['building_hardware'],
        'رخام وجرانيت' => ['building_hardware'],
        // «مفاتيح» left «مصانع» on 2026-08-16 and its factory half became
        // «كابلات وقواطع كهرباء». The line is REMOVED rather than renamed: the
        // catalog has no shelf for cable. `building_hardware` holds keys_locks،
        // marble_stone، timber_boards, and an entry here with no narrowing in
        // retail_child_types.php means EVERY type of the branch — a cable
        // factory listing marble. It sells through delivery and offers until
        // there is an item type that fits, which is the owner's call and the
        // catalog axis.
        'كبس خراطيم' => ['building_hardware'],
        'مستلزمات نجارة' => ['building_hardware'],
        'سيفتى ومقاومة حرائق' => ['building_hardware'],
        'أخشاب' => ['building_hardware'],
        // «بي في سي» folded into «باب وشباك» on 2026-08-12 — it carried this
        // exact branch, which is half of why the two were the same child.
        'باب وشباك' => ['building_hardware'],
        // «ألمونتال» #17 took this root on 2026-08-12. Under المحلات it is a
        // home_furnishings shop (cookware beside chandeliers); the FACTORY form
        // of it presses profiles, which is a building material.
        'ألمونتال' => ['building_hardware'],
        'طوب' => ['building_hardware'],
        'سيراميك وأدوات صحية' => ['building_hardware'],
        'مواد تعبئة وتغليف' => ['building_hardware'],
        'طباعة مواد تعبئة وتغليف' => ['building_hardware'],
        'قطع غيار سيارات' => ['vehicles_parts'],
        'منظفات' => ['hobbies_general'],
        'لعب أطفال' => ['hobbies_general'],
        'مستلزمات مطاعم وكافيهات' => ['hobbies_general'],
        'مواد غذائية' => ['grocery_retail'],
        'عصائر' => ['grocery_retail'],
        // Both had a live grocery_retail config from elsewhere and no entry
        // here, so the narrowing could not reach them and a fish factory was
        // offered baby care, detergents and tea. Naming them does not decide
        // whether a fish or sweets FACTORY should list SKUs at all — that is
        // still open — it decides what it lists while it does.
        'أسماك' => ['grocery_retail'],
        'حلويات' => ['grocery_retail'],
        // «اكسسوارت سيارات» / «اكسسوار موبيلات» folded into «اكسسوار» on
        // 2026-08-10 (AccessoryMergeSeeder) and became two of its options.
    ],
];
