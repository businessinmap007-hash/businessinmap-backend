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
        /*
        | «ادمج التسليم والاستلام» — owner, 2026-08-16.
        |
        | #109 «شحن وتوصيل» is gone from this list and from the taxonomy. It was
        | never a third answer: it is «شحن» AND «توصيل طلبات», both of which are
        | in this very line, and 104 of its 110 children already carried at
        | least one of them. A merchant was being asked the same question twice
        | and once more compounded.
        |
        | Removing the id here is half the merge and the half that makes it
        | stick — this seeder grants the whole list per child, so a dissolved
        | row left in the array is handed back to every goods child on the next
        | run. See `row_merges` in option_group_splits.php for the other half.
        */
        'fulfilment' => [
            'name_ar' => 'التسليم والاستلام',
            'name_en' => 'Delivery & Pickup',
            'reorder' => 42,
            'options' => [108, 152, 322, 134, 356],
            // Two of these five are narrowed further — see `option_only_for`
            // at the foot of this file. A bundle is per TRADE; those two are
            // per trade AND per word.
        ],
        'payment_terms' => [
            'name_ar' => 'الدفع والسداد',
            'name_en' => 'Payment Terms',
            'reorder' => 43,
            // «دفع مسبق» left this list on 2026-08-08: it is not a payment term
            // the way تقسيط is, it is what a CARRIER asks for, and granting the
            // whole group per root had put it on 286 children. PrepaymentScopeSeeder
            // is now its only authority — leaving it here would re-add it to every
            // root that gets this group.
            //
            // Owner, 2026-08-10: «الدفع والسداد استخدم منها كاش وتقسيط اما
            // الاخرين فسأحددهم يدويا». The list had exactly the wrong member —
            // «تقسيط بدون فوائد» was the ONE option granted per root, which is
            // why it reached 297 children while كاش reached 95 and تقسيط 84.
            // Interest-free instalments are a real commercial claim and only the
            // merchant can make it; كاش and تقسيط are the two answers every
            // trade on the platform can give. #204 now joins «دفع مسبق» as
            // hand-set only, and this seeder withdraws it wherever it was
            // granted wholesale — except from a merchant who has already ticked
            // it, whose answer outranks this map.
            'options' => [66, 203], // كاش، تقسيط
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
    | Alive, selectable, and never granted in bulk
    |--------------------------------------------------------------------------
    | Owner, 2026-08-10: «الدفع والسداد استخدم منها كاش وتقسيط اما الاخرين
    | فسأحددهم يدويا».
    |
    | Different from `retire_options` above, which unfiles the option and ends it
    | as an answer. #204 «تقسيط بدون فوائد» stays exactly as it is — a merchant
    | can still be given it, and one who already has it keeps it. What ends is
    | the wholesale grant that had put it on 297 children, more than any other
    | option in its group, purely because it was the one member of the list.
    |
    | «دفع مسبق» #292 is NOT here: it left this map on 2026-08-08 and
    | PrepaymentScopeSeeder deliberately holds it on the four carrier children.
    | Adding it would take a scope the owner asked for and call it noise.
    */
    'hand_set_options' => [204], // تقسيط بدون فوائد

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

    /*
    |--------------------------------------------------------------------------
    | «ضيّق تيك أواى وتسليم أرض المصنع» — owner, 2026-08-16
    |--------------------------------------------------------------------------
    | A bundle is granted per TRADE, and until now that was the finest cut this
    | map could make: a child either got all five fulfilment rows or none. Two
    | of the five are not trade-wide words, and handing them out with the bundle
    | is how «تيك أواى» reached a gold dealer, a marble yard and a freight
    | company, and how «تسليم أرض المصنع» reached a juice bar and a bakery.
    |
    | Nothing here is a taste call; each list is the option's own name applied
    | literally.
    |
    | ── «تسليم أرض المصنع» ────────────────────────────────────────────────
    | The word says FACTORY GROUNDS. So: every child standing under «مصانع»,
    | by the root and not by a list, so a factory child added tomorrow inherits
    | it — plus the trades outside that root whose goods leave on the BUYER's
    | lorry, by the ton, the head or the load. A caravan is towed away, cattle
    | are collected from the farm, and EXW is an importer's own vocabulary.
    |
    | What goes: the service trades that were answering it at all — تسويق،
    | تأمين، صرافة، تحويل أموال، سياحة، أمن، دعاية، طباعة، تنسيق حفلات and both
    | مقاولات, none of which hand over goods; the showrooms (سيارات، معرض
    | سيارات، معرض موتوسيكلات، ذهب، أنتيكات، نجف وتحف), which hand over on the
    | spot and call it a sale; the fitters (مصاعد، تبريد وتكييف، ستائر), whose
    | product is installed and never collected; and the counter shops.
    |
    | ── «تيك أواى» ───────────────────────────────────────────────────────
    | Prepared food and drink handed over a counter. The «مطاعم وكافيهات» root
    | is exactly the six venues, and three kitchens stand outside it: مخابز،
    | حلويات and عصائر — all three ruled kitchens by the owner on 2026-08-10,
    | which is the same ruling that gave حلويات and مخابز the bakery counter.
    |
    | «بن» is deliberately absent though it sits beside them: «بن يبيع حبوب
    | فقط» — a shop, not a kitchen, and a bag of beans is not a takeaway.
    |
    | What goes: 49 of the 55, and the shape of the list says why they were
    | there — ذهب، رخام، حديد تسليح، معدات ثقيلة، شحن بري وبحري وجوى. Not one
    | of them was curated in; they got the word because they got the bundle.
    |
    | ── The one thing this cannot say ────────────────────────────────────
    | «حلويات» is a factory under «مصانع» and a shop under «المحلات», and it
    | answers both lists here because a child is one row. Per-ROOT narrowing
    | exists in `category_child_option.category_id` but this map folds a child's
    | roots into one set before it decides, so it cannot ask the factory and the
    | shop different questions. Left as it is rather than half-built.
    */
    'option_only_for' => [
        134 => [   // تسليم أرض المصنع
            'roots' => ['factories'],
            'children' => [
                47,   // كرڤان — towed off the yard
                110,  // مواد غذائية ومنظفات — by the pallet
                139,  // معدات ثقيلة
                150,  // استيراد وتصدير — EXW is its own incoterm
                170,  // مواشي وأرانب — collected live from the farm
                173,  // رخام
                222,  // بلاستيك
                273,  // معدات سوبرماركت — shelving and fridges, by lorry
            ],
        ],
        356 => [   // تيك أواى
            'roots' => ['restaurants-cafes'],
            'children' => [
                27,   // مخابز
                158,  // عصائر
                210,  // حلويات
            ],
        ],
    ],
];
