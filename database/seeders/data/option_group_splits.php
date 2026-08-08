<?php

/**
 * The option groups that were still asking more than one question at a time.
 *
 * Same rule the commerce and vehicle grab-bags were broken up by: ONE group,
 * ONE question. Long is not the problem — «ماركات السيارات» has 43 rows and asks
 * one thing. Mixed is the problem: a hotel's facilities list also held its view
 * and its meal plan, so «إطلالة بحرية» and «نصف إقامة» sat between «مسبح» and
 * «سبا» as though they answered the same question.
 *
 * Splitting a group needs NO change to `category_child_option`: a link points at
 * an OPTION, so moving the option to another group carries its links with it.
 * Every child that could answer the old group can still answer all of it — the
 * answers are just filed under the right heading now.
 *
 * `into_existing` moves rows into a group that already exists rather than a new
 * one. Options not listed anywhere stay where they are.
 */
return [

    'splits' => [

        // ── Hotels: facilities, a view, and a meal plan are three questions ──
        'مرافق الإقامة' => [
            'new' => [
                'إطلالة الوحدة' => [
                    'name_en' => 'Unit View',
                    'reorder' => 32,
                    'options' => [853, 854], // إطلالة بحرية، إطلالة على المسبح
                ],
                'نظام الوجبات' => [
                    'name_en' => 'Meal Plan',
                    'reorder' => 33,
                    'options' => [855, 856, 857], // شامل الإفطار، إقامة كاملة، نصف إقامة
                ],
            ],
            // the ten that remain are facilities proper: wifi, pool, spa, parking…
        ],

        // ── Real estate: a property type, a deal type, and a payment term ──
        'عقارات وممتلكات' => [
            'new' => [
                // Named for property but not about property: a car showroom
                // asks the same question, so the group was renamed «نوع
                // التعامل» on 2026-08-08 and given to the three vehicle
                // showrooms. It is resolved by name_ar (option_groups has no
                // key), so this string IS the identity — changing it back would
                // create a second group and move these two options into it.
                //
                // «تبديل» belongs to the group too but is listed by
                // VehicleDealTypeSeeder, which creates it; only real estate's
                // own pair is declared here, and only the child links decide
                // who sees what.
                'نوع التعامل' => [
                    'name_en' => 'Deal Type',
                    'reorder' => 24,
                    'options' => [53, 302], // بيع وشراء، إيجار
                ],
            ],
            'into_existing' => [
                // كاش / تقسيط answer the same question as دفع مسبق / تقسيط بدون
                // فوائد, and answering it twice in two places is how a filter
                // ends up with two half-populated versions of one idea.
                'الدفع والسداد' => [66, 203],
            ],
            // the thirteen that remain are what the property IS: شقة، ڤيلا،
            // أرض، عمارة، محل، مصنع، ورشة…
        ],

        // ── Furniture: a piece of furniture and a style are not the same axis ──
        'أثاث وتشطيب منزلي' => [
            'new' => [
                'طراز الأثاث' => [
                    'name_en' => 'Furniture Style',
                    'reorder' => 25,
                    'options' => [83, 257, 28], // كلاسيك، مودرن، أنتيكات
                ],
            ],
        ],

        // ── Training: eight named languages inside a list of training fields ──
        'مجالات التدريب' => [
            'new' => [
                'اللغات' => [
                    'name_en' => 'Languages Taught',
                    'reorder' => 34,
                    'options' => [703, 704, 705, 706, 707, 708, 709, 710],
                ],
            ],
            // «لغات» (#687) stays behind on purpose: as a training FIELD it says
            // the centre teaches languages at all, which is a different claim
            // from teaching Japanese.
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Folded back: sports, specialties and lab tests are ONE list each
    |--------------------------------------------------------------------------
    | These three were briefly cut into families. Owner's call to fold them back
    | (2026-08-03), and the screen agreed: the parent group kept whatever
    | belonged to no family, so it appeared BESIDE its own children rather than
    | above them, and the leftovers produced folds of one — «الأنشطة الرياضية»
    | holding only جمباز on a gym, «رياضات جماعية» holding only كرة ماء on a pool.
    |
    | The API does return options grouped by name (`/discovery/attributes`,
    | `/profile/options` both emit `groups[] = {id, name, options[]}`), so the
    | families COULD have been rendered as folds. They are folded back anyway:
    | a doctor reads one list of specialties, not five.
    |
    | What made the families useful is kept elsewhere and untouched: a venue is
    | still offered only the sports it can host, through `child_activity_pools`
    | in sports_taxonomy.php. That is per-CHILD scoping, not grouping.
    |
    | family group => the group it folds back into. Emptied groups are removed.
    */
    'merges' => [
        'رياضات جماعية' => 'الأنشطة الرياضية',
        'رياضات المضرب' => 'الأنشطة الرياضية',
        'رياضات مائية' => 'الأنشطة الرياضية',
        'رياضات قتالية' => 'الأنشطة الرياضية',
        'لياقة وصالات' => 'الأنشطة الرياضية',

        'تخصصات جراحية' => 'تخصصات طبية',
        'تخصصات باطنية' => 'تخصصات طبية',
        'أطفال ونساء' => 'تخصصات طبية',
        'عيون وأنف وأذن' => 'تخصصات طبية',
        'عظام وتأهيل' => 'تخصصات طبية',

        'تحاليل الدم والكيمياء' => 'التحاليل الطبية',
        'هرمونات وفيتامينات' => 'التحاليل الطبية',
        'ميكروبيولوجي وفيروسات' => 'التحاليل الطبية',
        'باقات وفحوص خاصة' => 'التحاليل الطبية',
    ],
];
