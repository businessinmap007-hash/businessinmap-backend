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
                'نوع التعامل العقاري' => [
                    'name_en' => 'Property Deal Type',
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
];
