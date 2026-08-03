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

        /*
        |----------------------------------------------------------------------
        | Sports and health — one question, but too long to read at once
        |----------------------------------------------------------------------
        | These three were left whole on the first pass, because none of them
        | MIXES questions the way the four above did. They are split now for a
        | different reason: 45, 41 and 28 rows in one fold are a wall, and the
        | families below are the ones a venue or a clinic thinks in.
        |
        | This was only safe once SportsRemodelSeeder and HealthRemodelSeeder
        | stopped looking options up by (group_id, name_ar) — under the old
        | lookup a re-filed option read as missing and was re-created, so every
        | re-run would have duplicated the lot.
        */

        'الأنشطة الرياضية' => [
            'new' => [
                'رياضات جماعية' => [
                    'name_en' => 'Team Sports',
                    'reorder' => 35,
                    'options' => [630, 631, 632, 633, 637, 673, 666],
                ],
                'رياضات المضرب' => [
                    'name_en' => 'Racket Sports',
                    'reorder' => 36,
                    'options' => [634, 635, 647, 653, 669],
                ],
                'رياضات مائية' => [
                    'name_en' => 'Water Sports',
                    'reorder' => 37,
                    'options' => [636, 663, 664, 665, 667],
                ],
                'رياضات قتالية' => [
                    'name_en' => 'Combat Sports',
                    'reorder' => 38,
                    'options' => [639, 640, 649, 650, 651, 652, 656, 659, 660, 661, 662],
                ],
                'لياقة وصالات' => [
                    'name_en' => 'Fitness & Studio',
                    'reorder' => 39,
                    'options' => [641, 642, 643, 644, 645, 646, 655, 670, 671],
                ],
            ],
            // eight stay behind — باليه، جمباز، ألعاب قوى، رماية، فروسية،
            // دراجات، تزلج، باركور — individual sports that belong to no family
        ],

        'تخصصات طبية' => [
            'new' => [
                'تخصصات جراحية' => [
                    'name_en' => 'Surgical Specialties',
                    'reorder' => 40,
                    'options' => [584, 585, 586, 587, 588, 589, 590, 591, 592],
                ],
                'تخصصات باطنية' => [
                    'name_en' => 'Internal Medicine',
                    'reorder' => 41,
                    'options' => [577, 579, 581, 582, 594, 595, 599, 601, 609, 610, 611, 612, 613],
                ],
                'أطفال ونساء' => [
                    'name_en' => "Paediatrics & Women's Health",
                    'reorder' => 42,
                    'options' => [578, 596, 597, 615],
                ],
                'عيون وأنف وأذن' => [
                    'name_en' => 'Eye, Ear & Throat',
                    'reorder' => 43,
                    'options' => [580, 598, 600, 608],
                ],
                'عظام وتأهيل' => [
                    'name_en' => 'Orthopaedics & Rehabilitation',
                    'reorder' => 44,
                    'options' => [604, 605, 606, 607, 616],
                ],
            ],
            // six stay behind — أسنان، تخسيس وتغذية، جلدية، طب الأسرة، طب
            // المسنين، ممارسة عامة — the ones a general clinic offers
        ],

        'التحاليل الطبية' => [
            'new' => [
                'تحاليل الدم والكيمياء' => [
                    'name_en' => 'Blood & Chemistry Tests',
                    'reorder' => 45,
                    'options' => [714, 715, 716, 717, 718, 719, 723, 724, 725, 726, 727, 735, 736, 738],
                ],
                'هرمونات وفيتامينات' => [
                    'name_en' => 'Hormones & Vitamins',
                    'reorder' => 46,
                    'options' => [720, 721, 722, 729],
                ],
                'ميكروبيولوجي وفيروسات' => [
                    'name_en' => 'Microbiology & Virology',
                    'reorder' => 47,
                    'options' => [728, 734, 737],
                ],
                'باقات وفحوص خاصة' => [
                    'name_en' => 'Panels & Special Tests',
                    'reorder' => 48,
                    'options' => [730, 739, 740, 741],
                ],
            ],
            // three stay behind — حمل، بول كامل، براز كامل — the routine ones
        ],
    ],
];
