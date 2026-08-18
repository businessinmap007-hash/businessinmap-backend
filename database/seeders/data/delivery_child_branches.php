<?php

/*
 * Delivery child→branch map — generated from the approved live configuration
 * (2026-07-12). Keyed by root slug + child name_ar for portability; values are
 * platform_service_item_groups keys. Consumed by DeliveryChildBranchesSeeder.
 * See docs/delivery-branches-taxonomy.md for the classification rationale.
 */

return [

    // ── شحن وتوصيل ──
    'shipping-delivery' => [
        'شركة' => ['delivery_freight', 'delivery_courier_ondemand', 'delivery_documents'],
        'مكتب' => ['delivery_freight', 'delivery_courier_ondemand', 'delivery_documents'],
        'مندوب' => ['delivery_freight', 'delivery_courier_ondemand', 'delivery_documents'],
        // «انقل شحن بري وبحري وجوى الى شحن وتوصيل» — owner, 2026-08-16. It
        // was in the «شركات» block, filed with the marketing and insurance
        // firms while being the same trade as «شركة» above. The line moved
        // with the child: this map is keyed by ROOT and an add-only seeder
        // reading it from the old block puts the child back under the root it
        // left — the same trap the note below records for «عفشجى».
        // «شحن بري وبحري وجوى» folded into «شركة» on 2026-08-18. Its second
        // branch is the half «شركة» did not carry, so the keeper takes both.
        // Moved here from the workshops block on 2026-08-10, following the child
        // itself (ChildRootMovesSeeder: ورش → شحن وتوصيل). Left where it was, an
        // add-only seeder keyed by ROOT quietly puts the child back under the
        // root it was moved out of — running this seeder on its own is exactly
        // what did that, and it took ChildRootMovesSeeder to undo.
        // «عفشجى» was detached from this root on 2026-08-10 (owner) and its
        // one merchant moved to «مندوب». It stands under no root now.
    ],

    // ── مهن وحرفيين ──
    'professions' => [
        'منجد' => ['delivery_freight', 'delivery_courier_ondemand'],
        'نجار موبيليا' => ['delivery_freight', 'delivery_courier_ondemand'],
        // Children removed on 2026-08-10 by the owner's rule «حجز بدون توصيل
        // هو حجز وقت او مدة فلا نستخدم خدمة التوصيل» — they book time and sell
        // no goods. BookingWithoutDeliverySeeder is the authority; withdrawing
        // them here is what stops this map re-wiring delivery on its own run.
    ],

    // ── زراعية وحيوانية ──
    'agriculture-and-animals' => [
        'أعلاف' => ['delivery_freight'],
        'تقاوي وأسمدة ومبيدات' => ['delivery_freight'],
        'حبوب وغلال' => ['delivery_freight'],
        'دواجن' => ['delivery_freight', 'delivery_coldchain'],
        'خضار وفاكهة' => ['delivery_freight', 'delivery_coldchain'],
        'مزارع سمكية' => ['delivery_freight', 'delivery_coldchain'],
        'معدات زراعية' => ['delivery_freight'],
        'معدات وتجهيزات المزارع' => ['delivery_freight', 'delivery_coldchain'],
        'مواشي وأرانب' => ['delivery_freight', 'delivery_coldchain'],
    ],

    // ── ورش ومراكز صيانة ──
    'workshops' => [
        // «عفشجى» left this root — see the shipping-delivery block at the top.
        'نجار باب وشباك' => ['delivery_freight', 'delivery_courier_ondemand'],
        // The benches folded into the four workshop domains on 2026-08-10
        // (WorkshopRemodelSeeder). A cabinet leaves on a lorry; a repaired
        // kettle goes back by courier, so the appliance bench keeps only the
        // courier branch exactly as «تصليح أجهزة كهربائية» did.
        // «آثاث» and «باب وشباك» left this root on 2026-08-10 by the owner's
        // word — see data/child_root_detachments.php. A branch map keyed by
        // ROOT that still names them re-wires a child nothing points at.
        // Children removed on 2026-08-10 by the owner's rule «حجز بدون توصيل
        // هو حجز وقت او مدة فلا نستخدم خدمة التوصيل» — they book time and sell
        // no goods. BookingWithoutDeliverySeeder is the authority; withdrawing
        // them here is what stops this map re-wiring delivery on its own run.
    ],

    // ── سيارات ──
    'cars' => [
        /*
        | «معرض سيارات» #188 arrived here on 2026-08-17, folding «سيارات» #53
        | into itself on the way — owner: «خليه معرض سيارات ونفذ الطى والنقل».
        | Both stood under «معارض» and both were the same trade with the same
        | eight option groups, row for row. The root «سيارات» held seven
        | children and not one of them sold a car; it held a car wash, a driver
        | and a limousine service. A buyer opening «سيارات» found no car.
        |
        | Its branch is unchanged by the move — a showroom hands over a vehicle
        | and that is freight either way.
        */
        'معرض سيارات' => ['delivery_freight'],

        // Children removed on 2026-08-10 by the owner's rule «حجز بدون توصيل
        // هو حجز وقت او مدة فلا نستخدم خدمة التوصيل» — they book time and sell
        // no goods. BookingWithoutDeliverySeeder is the authority; withdrawing
        // them here is what stops this map re-wiring delivery on its own run.
    ],

    // ── معارض ──
    /*
    | This root had no block at all, so its twenty-eight showrooms were
    | configured from elsewhere and the narrowing could not reach them.
    | Twenty-six already carried freight, which is right — a showroom moves
    | heavy goods. «نجف و تحف» had the generic branch instead and was being
    | offered grocery, pharmacy AND restaurant delivery; «حلويات» kept a
    | medical sample courier. Writing down what the twenty-six already say is
    | what corrects the two.
    */
    'exhibitions' => [
        // Stood under «معارض» and «المحلات» on 2026-08-16 — the two roots a
        // customer actually looks for a bathroom shop in, and it was in
        // neither. Same shelf and same branch as under شركات and مصانع.
        'سيراميك وأدوات صحية' => ['delivery_freight'],
        'آثاث' => ['delivery_freight'],
        'أجهزة رياضية' => ['delivery_freight'],
        'أجهزة كهربائية' => ['delivery_freight'],
        'أجهزه كمبيوتر' => ['delivery_freight'],
        'أدوات تجميل' => ['delivery_freight'],
        'أصواف' => ['delivery_freight'],
        'أقمشة' => ['delivery_freight'],
        'ألمونتال' => ['delivery_freight'],
        'أنتيكات وتحف' => ['delivery_freight'],
        'إسفنج' => ['delivery_freight'],
        'جلود وشنط وأحذية' => ['delivery_freight'],
        'حدايد وبويات' => ['delivery_freight'],
        'رخام' => ['delivery_freight'],
        'زجاج' => ['delivery_freight'],
        'سجاد' => ['delivery_freight'],
        'سيفتى ومقاومة حرائق' => ['delivery_freight'],
        'صينى وخزف' => ['delivery_freight'],
        'لعب أطفال' => ['delivery_freight'],
        'مراتب' => ['delivery_freight'],
        'مستلزمات مطاعم' => ['delivery_freight'],
        // «معرض سيارات» left this root for «سيارات» on 2026-08-17 and is named
        // in the `cars` block above; «سيارات» #53 was folded into it and is
        // retired. Left here, this map would re-wire delivery under a root
        // neither child stands under.
        'معرض موتوسيكلات' => ['delivery_freight'],
        'مفروشات' => ['delivery_freight'],
        'ملابس جاهزة' => ['delivery_freight'],
        'نجف و تحف' => ['delivery_freight'],
        // A sweets showroom moves a chilled load as well as a heavy one.
        'حلويات' => ['delivery_freight', 'delivery_coldchain'],
    ],

    // ── ملابس و اكسسوارات ──
    'cloth-accessories' => [
        'اكسسوار' => ['delivery'],
        'جلود وشنط وأحذية' => ['delivery'],
        'ملابس' => ['delivery'],


    ],

    // ── تكنولوجيا ──
    'technology' => [
        // Children removed on 2026-08-10 by the owner's rule «حجز بدون توصيل
        // هو حجز وقت او مدة فلا نستخدم خدمة التوصيل» — they book time and sell
        // no goods. BookingWithoutDeliverySeeder is the authority; withdrawing
        // them here is what stops this map re-wiring delivery on its own run.
    ],

    // ── مطاعم وكافيهات ──
    'restaurants-cafes' => [
        'أكل بيتى' => ['delivery'],
        'عربية قهوة ومأكولات' => ['delivery'],
        'كافيه' => ['delivery'],
        'مجمع مطاعم' => ['delivery'],
        'مطعم' => ['delivery'],
        'مطعم وكافيه' => ['delivery'],
    ],

    // ── المحلات أو أونلاين ──
    'shops-online' => [
        // Stood under «معارض» and «المحلات» on 2026-08-16 — the two roots a
        // customer actually looks for a bathroom shop in, and it was in
        // neither. Same shelf and same branch as under شركات and مصانع.
        'سيراميك وأدوات صحية' => ['delivery_freight'],
        'أجهزة بلايستيشن' => ['delivery'],
        'أجهزة رياضية' => ['delivery'],
        'أجهزة كهربائية' => ['delivery'],
        'أجهزه كمبيوتر' => ['delivery'],
        'أدوات تجميل' => ['delivery'],
        'أدوات صيد' => ['delivery'],
        'أدوات كهربائية' => ['delivery'],
        'أدوات مكتبية' => ['delivery'],
        'أسماك' => ['delivery', 'delivery_coldchain'],
        'أصواف' => ['delivery'],
        'أقمشة' => ['delivery'],
        'ألمونتال' => ['delivery'],
        'أنتيكات وتحف' => ['delivery'],
        'إسفنج' => ['delivery'],
        'اسمنت' => ['delivery', 'delivery_freight'],
        'اكياس بلاستيك' => ['delivery'],
        'بلاستيك' => ['delivery'],
        'بن' => ['delivery'],
        'جنوط وكاوتش سيارات' => ['delivery'],
        'حدايد وبويات' => ['delivery', 'delivery_freight'],
        'حديد تسليح' => ['delivery', 'delivery_freight'],
        'حلويات' => ['delivery', 'delivery_coldchain'],
        'دواجن' => ['delivery', 'delivery_coldchain'],
        'ذهب' => ['delivery'],
        'رخام' => ['delivery', 'delivery_freight'],
        'زجاج' => ['delivery', 'delivery_freight'],
        'زيت سيارات' => ['delivery'],
        'ستائر و ديكور' => ['delivery'],
        'سجاد' => ['delivery'],
        'سوبر ماركت' => ['delivery'],
        'سيفتى ومقاومة حرائق' => ['delivery'],
        'صينى وخزف' => ['delivery'],
        'عصائر' => ['delivery', 'delivery_coldchain'],
        'عطور' => ['delivery'],
        'فضة' => ['delivery'],
        'خضار وفاكهة' => ['delivery', 'delivery_coldchain'],
        'قطع غيار أجهزة كهربائية' => ['delivery'],
        'قطع غيار سيارات' => ['delivery'],
        'كبس خراطيم' => ['delivery'],
        'كتب' => ['delivery'],
        'لعب أطفال' => ['delivery'],
        'لوازم ستائر' => ['delivery'],
        'مجمدات' => ['delivery', 'delivery_coldchain'],
        'مخابز' => ['delivery'],
        'مراتب' => ['delivery', 'delivery_freight'],
        'مستلزمات طبية' => ['delivery'],
        // Both arrived on this root after the map was written and were never
        // named, so they kept the generic branch whole — including pharmacy,
        // grocery and restaurant delivery. «اكسسوار» absorbed the two folded
        // accessory children (AccessoryMergeSeeder); «مكملات غذائية» came from
        // الرياضة.
        'اكسسوار' => ['delivery'],
        'مكملات غذائية' => ['delivery'],
        'مستلزمات كافيهات' => ['delivery'],
        'مستلزمات نجارة' => ['delivery'],
        // 2026-08-10: the doors-and-windows trade took this root; a door leaves
        // the shop on a lorry, not in a courier bag.
        'باب وشباك' => ['delivery_freight'],
        'مشتقات التدخين' => ['delivery'],
        'مصنوعات خشبية ومستلزمات ديكور' => ['delivery', 'delivery_freight'],
        'مفاتيح' => ['delivery'],
        'مفروشات' => ['delivery'],
        'منظفات' => ['delivery'],
        'مني ماركت' => ['delivery'],
        'موبيلات و اكسسوار' => ['delivery'],
        'نباتات طبيعية وزينة' => ['delivery', 'delivery_coldchain'],
        'نجف و تحف' => ['delivery'],
        'نظارات' => ['delivery'],
        'هايبر ماركت' => ['delivery'],
        // «اكسسوارت سيارات» / «اكسسوار موبيلات» folded into «اكسسوار» on
        // 2026-08-10 (AccessoryMergeSeeder) and became two of its options.
        // «تجهيز عرائس» / «أبواب مصفحة» folded on 2026-08-10 — each was
        // already an OPTION on a sibling under the same root. See
        // data/child_root_detachments.php.
    ],

    // ── مكاتب ──
    'offices' => [
        // Children removed on 2026-08-10 by the owner's rule «حجز بدون توصيل
        // هو حجز وقت او مدة فلا نستخدم خدمة التوصيل» — they book time and sell
        // no goods. BookingWithoutDeliverySeeder is the authority; withdrawing
        // them here is what stops this map re-wiring delivery on its own run.
    ],

    // ── الصحة ──
    'health' => [
        // + delivery_documents (2026-08-05): a lab's other outbound thing is
        // the printed result, which is a document, not a cold-chain sample.
        // Children removed on 2026-08-10 by the owner's rule «حجز بدون توصيل
        // هو حجز وقت او مدة فلا نستخدم خدمة التوصيل» — they book time and sell
        // no goods. BookingWithoutDeliverySeeder is the authority; withdrawing
        // them here is what stops this map re-wiring delivery on its own run.
    ],

    // ── شركات ──
    'companies' => [
        'آثاث' => ['delivery_freight'],
        // 2026-08-10: and the trade itself, now that it stands here.
        'باب وشباك' => ['delivery_freight'],
        'أجهزة رياضية' => ['delivery_freight'],
        'أجهزة كهربائية' => ['delivery_freight'],
        'أجهزه كمبيوتر' => ['delivery_freight'],
        'أخشاب' => ['delivery_freight'],
        'أدوات تجميل' => ['delivery_freight'],
        'سيراميك وأدوات صحية' => ['delivery_freight'],
        'أدوات مكتبية' => ['delivery_freight'],
        'أصواف' => ['delivery_freight'],
        'أقمشة' => ['delivery_freight'],
        'ألمونتال' => ['delivery_freight'],
        'أنتيكات وتحف' => ['delivery_freight'],
        'إسفنج' => ['delivery_freight'],
        'اسمنت' => ['delivery_freight'],
        'اكسسوار' => ['delivery_freight'],
        'تبريد وتكييف' => ['delivery_freight'],
        'جلود وشنط وأحذية' => ['delivery_freight'],
        'حدايد وبويات' => ['delivery_freight'],
        'حديد تسليح' => ['delivery_freight'],
        // «مفاتيح» and «حلويات» were detached from «شركات» on 2026-08-16 —
        // a key-cutter is a one-man bench and a sweets shop is a kitchen.
        // Their lines go with them: this map is keyed by ROOT.
        'رخام' => ['delivery_freight'],
        'زجاج' => ['delivery_freight'],
        'سجاد' => ['delivery_freight'],
        'سيفتى ومقاومة حرائق' => ['delivery_freight'],
        'صينى وخزف' => ['delivery_freight'],
        'طباعة' => ['delivery_freight'],
        'طوب' => ['delivery_freight'],
        'عصائر' => ['delivery_freight', 'delivery_coldchain'],
        'خضار وفاكهة' => ['delivery_freight', 'delivery_coldchain'],
        'قطع غيار' => ['delivery_freight'],
        // The sibling row #44, never named here, so it kept the generic branch
        // whole — pharmacy, grocery and restaurant delivery on a spare-parts
        // company. مصانع already said freight for the same trade.
        'قطع غيار سيارات' => ['delivery_freight'],
        'كبس خراطيم' => ['delivery_freight'],
        'كرڤان' => ['delivery_freight'],
        'لعب أطفال' => ['delivery_freight'],
        'لوازم ستائر' => ['delivery_freight'],
        'مراتب' => ['delivery_freight'],
        'مستلزمات طبية' => ['delivery_freight'],
        'مستلزمات قهاوى' => ['delivery_freight'],
        'مستلزمات مطاعم' => ['delivery_freight'],
        'مستلزمات نجارة' => ['delivery_freight'],
        'مصاعد وسلم كهرياء' => ['delivery_freight'],
        'معدات ثقيلة' => ['delivery_freight'],
        'معدات سوبرماركت' => ['delivery_freight'],
        'مفروشات' => ['delivery_freight'],
        'ملابس جاهزة' => ['delivery_freight'],
        'منظفات' => ['delivery_freight'],
        'مواد تعبئة وتغليف' => ['delivery_freight'],
        'مواد دوائية' => ['delivery_coldchain', 'delivery_documents'],
        'مواد غذائية ومنظفات' => ['delivery_freight', 'delivery_coldchain'],
        'مواشي وأرانب' => ['delivery_freight', 'delivery_coldchain'],
        'نجف' => ['delivery_freight'],
        // «نقل دولي» folded into «شحن بري وبحري وجوى» on 2026-08-12 — it
        // already carries delivery_international, which is the whole of it.
        // Children removed on 2026-08-10 by the owner's rule «حجز بدون توصيل
        // هو حجز وقت او مدة فلا نستخدم خدمة التوصيل» — they book time and sell
        // no goods. BookingWithoutDeliverySeeder is the authority; withdrawing
        // them here is what stops this map re-wiring delivery on its own run.
        // «تجهيز عرائس» / «أبواب مصفحة» folded on 2026-08-10 — each was
        // already an OPTION on a sibling under the same root. See
        // data/child_root_detachments.php.
    ],

    // ── مصانع ──
    'factories' => [
        'آثاث' => ['delivery_freight'],
        'أجهزة كهربائية' => ['delivery_freight'],
        'أجهزه كمبيوتر' => ['delivery_freight'],
        'أخشاب' => ['delivery_freight'],
        'أدوات تجميل' => ['delivery_freight'],
        'سيراميك وأدوات صحية' => ['delivery_freight'],
        'أسماك' => ['delivery_freight', 'delivery_coldchain'],
        'أصواف' => ['delivery_freight'],
        'أقمشة' => ['delivery_freight'],
        'إسفنج' => ['delivery_freight'],
        'اسمنت' => ['delivery_freight'],
        'اكسسوار' => ['delivery_freight'],
        'اكياس بلاستيك' => ['delivery_freight'],
        'باب وشباك' => ['delivery_freight'],
        // «بي في سي» folded into «باب وشباك» above on 2026-08-12 — same trade,
        // same freight branch. A child under no root cannot carry a service.
        'جلود وشنط وأحذية' => ['delivery_freight'],
        'حدايد وبويات' => ['delivery_freight'],
        'حديد تسليح' => ['delivery_freight'],
        'حلويات' => ['delivery_freight', 'delivery_coldchain'],
        'رخام وجرانيت' => ['delivery_freight'],
        'زجاج' => ['delivery_freight'],
        'سجاد' => ['delivery_freight'],
        'سيفتى ومقاومة حرائق' => ['delivery_freight'],
        'صينى وخزف' => ['delivery_freight'],
        'طباعة مواد تعبئة وتغليف' => ['delivery_freight'],
        'طوب' => ['delivery_freight'],
        'عصائر' => ['delivery_freight', 'delivery_coldchain'],
        'قطع غيار سيارات' => ['delivery_freight'],
        'كبس خراطيم' => ['delivery_freight'],
        'لعب أطفال' => ['delivery_freight'],
        'مراتب' => ['delivery_freight'],
        'مستلزمات طبية' => ['delivery_freight'],
        'مستلزمات مطاعم' => ['delivery_freight'],
        'مستلزمات نجارة' => ['delivery_freight'],
        // «مفاتيح» left «مصانع» on 2026-08-16: the factory half became
        // «كابلات وقواطع كهرباء», a child of its own. The line follows the
        // trade, and this map is keyed by ROOT and by NAME.
        'كابلات وقواطع كهرباء' => ['delivery_freight'],
        'مفروشات' => ['delivery_freight'],
        'ملابس جاهزة' => ['delivery_freight'],
        'منظفات' => ['delivery_freight'],
        'مواد تعبئة وتغليف' => ['delivery_freight'],
        'مواد دوائية' => ['delivery_freight', 'delivery_coldchain'],
        'مواد غذائية' => ['delivery_freight', 'delivery_coldchain'],
        'نجف' => ['delivery_freight'],
        // «اكسسوارت سيارات» / «اكسسوار موبيلات» folded into «اكسسوار» on
        // 2026-08-10 (AccessoryMergeSeeder) and became two of its options.
    ],
];
