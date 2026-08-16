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

                /*
                | ── and a fourth question: what the hotel SELLS you ──────────
                |
                | «قسّم مرافق النادي الرياضي» drew the line and this is the same
                | one: a facility is a room the guest walks into, a service is a
                | person the hotel assigns and bills for. المسبح، الجيم، السبا
                | and المطعم الداخلي are rooms; «نقل من المطار» is a driver, a
                | car and a fare, and while it sat among the amenities a hotel
                | could say it runs transfers and never say for how much.
                |
                | «خدمة الغرف» deliberately stayed a facility. Staff bring it,
                | but the bill is the food and the hotel prices that on a menu —
                | the row means «there is room service», which is a fact about
                | the place. Same reading that kept «ساونا» with the gym's rooms.
                |
                | One row, and that is not an oversight. A group of one earns its
                | place when it exists so something can be PRICED that otherwise
                | cannot be — it renders as a working line on the pricing screen,
                | not as a heading with nothing under it. The folds this file
                | undid in `merges` were the opposite case: headings that had
                | been emptied of everything except a leftover.
                */
                'خدمات الفندق' => [
                    'name_en' => 'Hotel Services',
                    'reorder' => 35,
                    'options' => [867], // نقل من المطار
                ],
            ],
            // the nine that remain are facilities proper: wifi, pool, spa, parking…
        ],

        /*
        | ── Clinics: a facilities list holding the one thing that is a price ──
        |
        | health_child_vocabularies.php ruled that the medical children need no
        | modifier at all: «a modifier exists where the SAME line prices two
        | ways, and «كشف» does not — what changes a consultation's price here is
        | the specialty, and the specialty is already the line».
        |
        | That is right about every row it was written for and wrong about one it
        | was already holding. «زيارة منزلية» IS the same line priced two ways: a
        | كشف باطنة in the clinic and the same كشف at your bedside are two prices
        | for one specialty, and a lab's home collection is a fee on top of the
        | test. The modifier the file declined to invent existed in its own
        | descriptive list, where it could never reach a price.
        |
        | So it moves rather than being created — into «نمط تقديم الخدمة», which
        | is exactly this axis and says so: vehicle_option_groups.php parks
        | «سيارة بسائق» there on the grounds that it «describes HOW the service
        | is supplied, the same axis as فردي / فريق عمل / أونلاين». A doctor at
        | your door is that question answered.
        |
        | The five carriers keep the row; مراكز أشعة and #215 do not have it and
        | do not get it — an X-ray suite does not travel.
        */
        'تسهيلات ومرافق طبية' => [
            'into_existing' => [
                'نمط تقديم الخدمة' => [1979], // زيارة منزلية
            ],
            // the nine that remain are the place itself: insurance, an in-house
            // lab, an accessible entrance, parking, a women's section.
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
    | One answer, one row — the same disease one level down
    |--------------------------------------------------------------------------
    | A group can ask two questions; a ROW can restate two answers that are
    | already in the list beside it. «ادمج التسليم والاستلام» — owner,
    | 2026-08-16.
    |
    | «شحن وتوصيل» is not a third method. It is «شحن» and «توصيل طلبات» joined
    | by a واو, and both stand in the same group. The link data says so without
    | being asked: of its 110 children, 92 also carry «شحن», 73 also carry
    | «توصيل طلبات», and only SIX carry it alone. The other 104 were answering
    | one question twice and once more compounded.
    |
    | Dissolving is not deleting. Every child holding the compound is given the
    | two rows it stands for — under the same root scope, and never against a
    | withdrawal — so nobody loses a way to say what they do. The six who had
    | only the compound gain both and are the ones this actually rescues: بن،
    | ستائر و ديكور، جنوط وكاوتش، فضة، مشتقات التدخين، قطع غيار أجهزة كهربائية
    | could each say «شحن وتوصيل» and neither «شحن» nor «توصيل».
    |
    | ## Retiring the row it leaves behind
    |
    | The emptied option is moved to «صفوف متقاعدة», which is INACTIVE. Both the
    | admin picker and MerchantOfferingVocabulary filter on
    | `option_groups.is_active`, so an inactive group is offered to nobody while
    | the row itself survives, keeps its id and can be read back — the same
    | tombstone «أقسام السوبر ماركت» is, one level down.
    |
    | It is deliberately NOT `group_id = NULL`, which is what
    | VehicleOptionGroupsSeeder does. A groupless option fails
    | TaxonomyRedistributionTest and for a good reason written there: it can
    | never be shown, edited or restored through any screen, and it keeps a
    | `name_en` that is UNIQUE platform-wide, so a dead row silently costs a
    | live one its English name.
    |
    | ## What was NOT merged
    |
    | «تيك أواى» and «تسليم أرض المصنع» are one logistical answer — you collect
    | from us — and 48 of the 55 children carrying the first carry the second
    | too. They are still two rows, because they are two trades' words: an EXW
    | delivery is an incoterm a factory quotes and تيك أواى is what you say at a
    | counter. Merging them would have a café answering «تسليم أرض المصنع».
    | The real defect there is that the whole six-row group is granted per child
    | by this map, which is how «تيك أواى» reached a gold dealer and a
    | heavy-equipment yard — a scoping job, and it needs his list.
    |
    | «توصيل مجانى» is a price and not a method, and it stays. It is the widest
    | row in the group (113 children) and it is a real commercial claim a
    | customer filters on; folding it into «توصيل طلبات» would cost every one of
    | them the word «مجانى» to fix a tidiness problem.
    |
    | ## Run ChildOptionGroupsSeeder after this
    |
    | Dissolving hands each part to every child that held the compound, and it
    | knows nothing about which children are OFFERED the fulfilment group at
    | all. Eleven service children — دعاية وإعلان، تسويق، تحويل أموال، صرافة،
    | تأمين، سياحة، أمن، طباعة، مقاولات ×2، تنسيق حفلات — held «شحن وتوصيل»
    | under «شركات» and came out of the merge holding «توصيل طلبات», which a
    | marketing agency has no business answering. child_option_groups.php is the
    | authority on who gets this group and it prunes exactly those eleven. Their
    | compound row had been standing against the same map and would have been
    | pruned the same way; the merge only changed which row it takes.
    */
    'row_merges' => [
        [
            'group' => 'التسليم والاستلام',
            'from' => 109,          // شحن وتوصيل
            'into' => [322, 108],   // شحن + توصيل طلبات
        ],
    ],

    /** Where a dissolved row goes. Inactive, so no screen offers it. */
    'retired_group' => [
        'name_ar' => 'صفوف متقاعدة',
        'name_en' => 'Retired Rows',
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
