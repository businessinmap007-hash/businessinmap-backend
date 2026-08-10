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
        'اصلاح زجاج السيارات' => ['delivery_courier_ondemand'],
        'سائق' => ['delivery_courier_ondemand'],
        'منجد' => ['delivery_freight', 'delivery_courier_ondemand'],
        'نجار موبيليا' => ['delivery_freight', 'delivery_courier_ondemand'],
        // Children removed on 2026-08-10 by the owner's rule «حجز بدون توصيل
        // هو حجز وقت او مدة فلا نستخدم خدمة التوصيل» — they book time and sell
        // no goods. BookingWithoutDeliverySeeder is the authority; withdrawing
        // them here is what stops this map re-wiring delivery on its own run.
    ],

    // ── زراعية وحيوانية ──
    'agriculture-and-animals' => [
        'أرانب' => ['delivery_freight'],
        'أسمدة' => ['delivery_freight'],
        'أعلاف' => ['delivery_freight'],
        'تقاوي زراعية' => ['delivery_freight'],
        'حبوب وغلال' => ['delivery_freight'],
        'خضروات' => ['delivery_freight', 'delivery_coldchain'],
        'دواجن' => ['delivery_freight', 'delivery_coldchain'],
        'فواكة' => ['delivery_freight', 'delivery_coldchain'],
        'مزارع سمكية' => ['delivery_freight', 'delivery_coldchain'],
        'معدات زراعية' => ['delivery_freight'],
        'معدات مزارع أرانب' => ['delivery_freight'],
        'معدات مزارع دواجن' => ['delivery_freight', 'delivery_coldchain'],
        'معدات مزارع مواشي' => ['delivery_freight', 'delivery_coldchain'],
        'مواشي' => ['delivery_freight', 'delivery_coldchain'],
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
        // Children removed on 2026-08-10 by the owner's rule «حجز بدون توصيل
        // هو حجز وقت او مدة فلا نستخدم خدمة التوصيل» — they book time and sell
        // no goods. BookingWithoutDeliverySeeder is the authority; withdrawing
        // them here is what stops this map re-wiring delivery on its own run.
    ],

    // ── ملابس و اكسسوارات ──
    'cloth-accessories' => [
        'اكسسوار' => ['delivery'],
        'جلود وشنط وأحذية' => ['delivery'],
        'كوتشي' => ['delivery'],
        'ملابس' => ['delivery'],
        'ملابس النوم' => ['delivery'],
        'ملابس رسمي' => ['delivery'],
        'ملابس رياضية' => ['delivery'],
        'ملابس زفاف' => ['delivery'],
        'ملابس كاجوال' => ['delivery'],
    ],

    // ── تكنولوجيا ──
    'technology' => [
        'إدارة صفحات' => ['delivery'],
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
        'تجهيز عرائس' => ['delivery'],
        'جنوط وكاوتش سيارات' => ['delivery'],
        'حدايد وبويات' => ['delivery', 'delivery_freight'],
        'حديد تسليح' => ['delivery', 'delivery_freight'],
        'حلويات' => ['delivery', 'delivery_coldchain'],
        'خضروات' => ['delivery', 'delivery_coldchain'],
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
        'صيني ومستلزمات بيت' => ['delivery'],
        'عصائر' => ['delivery', 'delivery_coldchain'],
        'عطور' => ['delivery'],
        'فضة' => ['delivery'],
        'فواكة' => ['delivery', 'delivery_coldchain'],
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
        'أبواب مصفحة' => ['delivery_freight'],
        // 2026-08-10: and the trade itself, now that it stands here.
        'باب وشباك' => ['delivery_freight'],
        'أجهزة رياضية' => ['delivery_freight'],
        'أجهزة كهربائية' => ['delivery_freight'],
        'أجهزه كمبيوتر' => ['delivery_freight'],
        'أخشاب' => ['delivery_freight'],
        'أدوات تجميل' => ['delivery_freight'],
        'أدوات صحية' => ['delivery_freight'],
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
        'حلويات' => ['delivery_freight', 'delivery_coldchain'],
        'خضروات' => ['delivery_freight', 'delivery_coldchain'],
        'رخام' => ['delivery_freight'],
        'زجاج' => ['delivery_freight'],
        'سجاد' => ['delivery_freight'],
        'سيارات' => ['delivery_freight'],
        'سيفتى ومقاومة حرائق' => ['delivery_freight'],
        'شحن بري وبحري وجوى' => ['delivery_freight', 'delivery_international'],
        'صينى وخزف' => ['delivery_freight'],
        'صيني ومستلزمات بيت' => ['delivery_freight'],
        'طباعة' => ['delivery_freight'],
        'طوب' => ['delivery_freight'],
        'عصائر' => ['delivery_freight', 'delivery_coldchain'],
        'فواكة' => ['delivery_freight', 'delivery_coldchain'],
        'قطع غيار' => ['delivery_freight'],
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
        'مفاتيح' => ['delivery_freight'],
        'مفروشات' => ['delivery_freight'],
        'ملابس جاهزة' => ['delivery_freight'],
        'منظفات' => ['delivery_freight'],
        'مواد تعبئة وتغليف' => ['delivery_freight'],
        'مواد دوائية' => ['delivery_coldchain', 'delivery_documents'],
        'مواد غذائية ومنظفات' => ['delivery_freight', 'delivery_coldchain'],
        'مواشي' => ['delivery_freight', 'delivery_coldchain'],
        'نجف' => ['delivery_freight'],
        'نقل دولي' => ['delivery_freight', 'delivery_international'],
        // Children removed on 2026-08-10 by the owner's rule «حجز بدون توصيل
        // هو حجز وقت او مدة فلا نستخدم خدمة التوصيل» — they book time and sell
        // no goods. BookingWithoutDeliverySeeder is the authority; withdrawing
        // them here is what stops this map re-wiring delivery on its own run.
    ],

    // ── مصانع ──
    'factories' => [
        'آثاث' => ['delivery_freight'],
        'أجهزة كهربائية' => ['delivery_freight'],
        'أجهزه كمبيوتر' => ['delivery_freight'],
        'أخشاب' => ['delivery_freight'],
        'أدوات تجميل' => ['delivery_freight'],
        'أدوات صحية' => ['delivery_freight'],
        'أسماك' => ['delivery_freight', 'delivery_coldchain'],
        'أصواف' => ['delivery_freight'],
        'أقمشة' => ['delivery_freight'],
        'إسفنج' => ['delivery_freight'],
        'اسمنت' => ['delivery_freight'],
        'اكسسوار' => ['delivery_freight'],
        'اكياس بلاستيك' => ['delivery_freight'],
        'باب وشباك' => ['delivery_freight'],
        'بي في سي' => ['delivery_freight'],
        'جلود وشنط وأحذية' => ['delivery_freight'],
        'حدايد وبويات' => ['delivery_freight'],
        'حديد تسليح' => ['delivery_freight'],
        'حلويات' => ['delivery_freight', 'delivery_coldchain'],
        'رخام وجرانيت' => ['delivery_freight'],
        'زجاج' => ['delivery_freight'],
        'سجاد' => ['delivery_freight'],
        'سيارات' => ['delivery_freight'],
        'سيفتى ومقاومة حرائق' => ['delivery_freight'],
        'صينى وخزف' => ['delivery_freight'],
        'صيني ومستلزمات بيت' => ['delivery_freight'],
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
        'مفاتيح' => ['delivery_freight'],
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
