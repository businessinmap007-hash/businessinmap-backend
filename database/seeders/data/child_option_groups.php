<?php

/**
 * The per-child option map.
 *
 * Everything here concerns ONE thing: the grab-bag group «أنماط خدمة وتجارية»,
 * which held 24 unrelated options and was attached to 247 of the 253 children —
 * three quarters of every option link on the platform. A نقاش was offered
 * «تصدير» and «تسليم أرض المصنع»; a hotel was offered «بدون مبيدات».
 *
 * It is split into eight small groups that each answer ONE question, and each
 * child is given only the groups that its trade can actually answer.
 *
 * The domain groups (مركبات ونقل، أثاث وتشطيب منزلي، تخصصات طبية، مرافق الإقامة…)
 * are NOT managed here — they were already targeted deliberately. Only the four
 * outliers listed under `domain_strips` / `domain_adds` are corrected.
 *
 * Keys of `child_overrides` are "root-slug:child-id" because one child row is
 * shared by several roots and means a different trade under each: «آثاث» is a
 * workshop under ورش and a showroom under معارض.
 */

// ── shorthands, so a change to a bundle lands everywhere it belongs ──────────
$goods = ['trade_scope', 'product_condition', 'fulfilment', 'returns_policy', 'payment_terms'];
$madeHere = ['trade_scope', 'fulfilment', 'returns_policy', 'payment_terms']; // a factory sells new only
$fieldWork = ['service_mode', 'payment_terms'];                                // craftsmen, professional offices
$venue = ['venue_suitability', 'service_mode', 'payment_terms'];               // a place you visit

return [

    /*
    |--------------------------------------------------------------------------
    | The eight groups the grab-bag becomes
    |--------------------------------------------------------------------------
    | Option ids are the EXISTING rows; nothing new is invented, they are only
    | re-filed. `بيع` (#311) is retired instead — every business sells, so the
    | option carried no information.
    */
    'groups' => [
        'trade_scope' => [
            'name_ar' => 'نطاق التعامل',
            'name_en' => 'Trade Scope',
            'reorder' => 40,
            'options' => [384, 305, 132, 199], // جملة، تجزئة، تصدير، إستيراد
        ],
        'product_condition' => [
            'name_ar' => 'حالة المنتج',
            'name_en' => 'Product Condition',
            'reorder' => 41,
            'options' => [262, 368], // جديد، مستعمل
        ],
        'fulfilment' => [
            'name_ar' => 'التسليم والاستلام',
            'name_en' => 'Delivery & Pickup',
            'reorder' => 42,
            'options' => [108, 152, 322, 109, 134, 356],
        ],
        'payment_terms' => [
            'name_ar' => 'الدفع والسداد',
            'name_en' => 'Payment Terms',
            'reorder' => 43,
            'options' => [204, 292], // تقسيط بدون فوائد، دفع مسبق
        ],
        'returns_policy' => [
            'name_ar' => 'الاستبدال والإرجاع',
            'name_en' => 'Returns & Exchange',
            'reorder' => 44,
            'options' => [303, 70], // استبدال، تغيير
        ],
        'service_mode' => [
            'name_ar' => 'نمط تقديم الخدمة',
            'name_en' => 'Service Mode',
            'reorder' => 45,
            'options' => [200, 357, 267, 294], // فردي، فريق عمل، أونلاين، خاص
        ],
        'venue_suitability' => [
            'name_ar' => 'ملاءمة المكان',
            'name_en' => 'Venue Suitability',
            'reorder' => 46,
            'options' => [137, 264], // عائلي، ممنوع التدخين
        ],
        'produce_quality' => [
            'name_ar' => 'مواصفات المنتج الغذائي',
            'name_en' => 'Food Product Attributes',
            'reorder' => 47,
            'options' => [270], // بدون مبيدات
        ],
    ],

    /** Unlinked everywhere and left groupless — «بيع» describes every business. */
    'retire_options' => [311],

    /*
    |--------------------------------------------------------------------------
    | What each root's children get by default
    |--------------------------------------------------------------------------
    */
    'root_defaults' => [
        'shipping-delivery' => ['fulfilment', 'service_mode', 'payment_terms'],
        'professions' => $fieldWork,
        'sports' => $venue,
        'agriculture-and-animals' => ['trade_scope', 'fulfilment', 'payment_terms'],
        'arts-entertainment' => $venue,
        'workshops' => $fieldWork,
        'halls' => ['venue_suitability', 'payment_terms'],
        'training-courses' => $fieldWork,
        'cars' => $fieldWork,
        'cloth-accessories' => $goods,
        'technology' => $fieldWork,
        'restaurants-cafes' => ['venue_suitability', 'fulfilment', 'payment_terms'],
        'shops-online' => $goods,
        'property-and-land' => $fieldWork,
        'offices' => $fieldWork,
        'health' => $fieldWork,
        'exhibitions' => $goods,
        'companies' => $goods,
        'factories' => $madeHere,
        'tourist-hotels' => ['venue_suitability', 'payment_terms'],
        'hair-dresser' => $venue,
    ],

    /*
    |--------------------------------------------------------------------------
    | The children whose trade differs from their root's default
    |--------------------------------------------------------------------------
    */
    'child_overrides' => [

        // ── a shop sitting among craftsmen, and a salon among them too
        'professions:136' => $venue,                       // كوافير
        'sports:274' => $goods,                            // مكملات غذائية — a supplement shop
        'arts-entertainment:217' => $fieldWork,            // فوتوجرافر — goes to you
        'offices:62' => $venue,                            // منطقة عمل مشتركة — you sit in it
        'halls:282' => $venue,                             // قاعات تدريب
        'health:215' => ['trade_scope', 'fulfilment', 'payment_terms'], // صيدلية dispenses goods
        'health:516' => $venue,                            // نادي صحي
        'shops-online:271' => $fieldWork,                  // استوديوهات — a service, not stock
        'shops-online:35' => ['service_mode', 'fulfilment', 'payment_terms'], // تجهيز عرائس

        // ── workshops that deliver what they build
        'workshops:116' => ['service_mode', 'fulfilment', 'returns_policy', 'payment_terms'], // آثاث
        'workshops:160' => ['service_mode', 'fulfilment', 'returns_policy', 'payment_terms'], // مطابخ و دريسنج
        'workshops:288' => ['service_mode', 'fulfilment', 'payment_terms'],                   // تنجيد
        'workshops:269' => ['service_mode', 'fulfilment', 'payment_terms'],                   // استانلس ومعدات مطاعم

        // ── street food and home kitchens have no dining room to describe
        'restaurants-cafes:143' => ['fulfilment', 'payment_terms'], // أكل بيتى
        'restaurants-cafes:65' => ['fulfilment', 'payment_terms'],                     // عربية قهوة ومأكولات

        // ── companies: the service firms among the goods traders
        'companies:253' => $fieldWork,   // أمن
        'companies:261' => $fieldWork,   // برمجيات
        'companies:283' => $fieldWork,   // تحويل أموال
        'companies:177' => $fieldWork,   // تسويق
        'companies:70' => $fieldWork,    // تنسيق حفلات
        'companies:11' => $fieldWork,    // دعاية وإعلان
        'companies:285' => $fieldWork,   // رحلات
        'companies:279' => $fieldWork,   // سياحة
        'companies:153' => $fieldWork,   // شركات تأمين
        'companies:187' => $fieldWork,   // صرافة نقود
        'companies:231' => $fieldWork,   // طباعة
        'companies:72' => $fieldWork,    // مقاولات
        'companies:152' => $fieldWork,   // مقاولات بنية تحتية
        'companies:166' => ['fulfilment', 'service_mode', 'payment_terms'], // شحن بري وبحري وجوى
        'companies:154' => ['fulfilment', 'service_mode', 'payment_terms'], // نقل دولي
        'companies:150' => ['trade_scope', 'fulfilment', 'payment_terms'],  // استيراد وتصدير
    ],

    /*
    |--------------------------------------------------------------------------
    | Food children — they answer «بدون مبيدات» on top of their root's default
    |--------------------------------------------------------------------------
    */
    'produce_children' => [
        // crops only — «بدون مبيدات» says nothing about a rabbit, a fish farm
        // or a bag of feed, and a supermarket answers it per product, not per shop
        'agriculture-and-animals' => [292, 114, 128, 14], // خضروات، فواكة، حبوب وغلال، تقاوي
        'shops-online' => [292, 114, 158],                 // خضروات، فواكة، عصائر
        'companies' => [292, 114, 158],
        'restaurants-cafes' => [143],                      // أكل بيتى
    ],

    /** Farm equipment is bought used as often as new. */
    'condition_children' => [
        'agriculture-and-animals' => [12, 235, 230, 171],
    ],

    /*
    |--------------------------------------------------------------------------
    | Domain-group corrections (outside the eight managed groups)
    |--------------------------------------------------------------------------
    | «آثاث» carried 63 vehicle options and 17 property options — legacy spray
    | from before the domain groups were targeted. «معرض سيارات» keeps its
    | vehicle options but is not a property listing. «سيارات» under معارض had no
    | options at all.
    */
    'domain_strips' => [
        116 => ['مركبات ونقل', 'عقارات وممتلكات'],
        188 => ['عقارات وممتلكات'],
        // A lab runs tests; it has no surgeons and no MRI. health_taxonomy.php
        // already says `carries_specialties => false` for it and hands imaging
        // to مراكز أشعة alone — these links predate that decision.
        // One name each is enough again now that specialties are a single group;
        // while they were split into families this list had to name all five,
        // because a strip keyed on a group name misses everything that moved out.
        163 => ['تخصصات طبية', 'أنواع الأشعة'],
    ],

    'domain_adds' => [
        53 => ['مركبات ونقل'],
        // children created after LinkCategoryChildrenToOptionsSeeder last ran,
        // so its keyword pass never reached them
        116 => ['أثاث وتشطيب منزلي'],
        518 => ['عقارات وممتلكات'], // مطور عقاري
        522 => ['عقارات وممتلكات'], // مالك عقار
        527 => ['مرافق ومعدات'],    // قاعة مناسبات
        528 => ['مرافق ومعدات'],    // مركز مؤتمرات واجتماعات
        529 => ['مرافق ومعدات'],    // مركز تدريب
    ],
];
