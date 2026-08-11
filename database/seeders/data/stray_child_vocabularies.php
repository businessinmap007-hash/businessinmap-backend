<?php

/*
|--------------------------------------------------------------------------
| The last two mute children on the platform
|--------------------------------------------------------------------------
| Neither belongs to a root that needed a pass — each is the one child its
| siblings left behind — so they are collected here rather than given a root
| file of their own.
|
| **«نادي صحي» #516** stands under «الرياضة» beside جيم، نادي رياضي، حمام سباحة
| and أكاديمية رياضية, and all four carry «الأنشطة الرياضية». It arrived after
| that grant — it spent a day rootless during the sports remodel — and nobody
| went back for it. It borrows the rows a health club actually runs, which is
| the indoor half: no football pitch, no horse riding.
|
| **«سائق» #85** under «سيارات» is a hired driver, and what he is booked with is
| the VEHICLE. «مركبات النقل والركاب» is exactly that list and eleven children
| already carry it. He takes the passenger side of it and leaves «معدات ثقيلة»
| and «مقطورة» to the freight trades — a driver-for-hire is not a haulier.
|
| ── What is NOT here ──────────────────────────────────────────────────────
|
| **«مندوب» #243, 159 merchants — the largest child on the platform — stays
| mute, and that is correct.** The owner withdrew all thirteen of its options
| by hand on 2026-08-11, «ربع نقل» and «ربع نقل صندوق» among them. The seeder
| would refuse to re-grant them anyway; naming him here would only produce a
| line of noise in the report every run.
*/

return [

    'root' => 'sports',

    'name_en_suffix' => 'Stray',

    /*
    | ── and the axis «الرياضة» was missing ────────────────────────────────
    |
    | «جيم» #130 carried «الأنشطة الرياضية» and NOTHING else — not one word
    | about the place itself. A member choosing between two gyms could compare
    | «كارديو» against «كارديو» and learn nothing: not whether either has a
    | pool, a women's section, or a locker.
    |
    | Descriptive, because none of it is priced on its own — the same shape as
    | «تسهيلات ومرافق طبية» under «الصحة» and «نوع العملاء» under «مكاتب».
    */
    'groups' => [
        'مرافق النادي الرياضي' => [
            'name_en' => 'Club Facilities',
            'price_role' => 'descriptive',
            'children' => [130, 519, 521, 516, 520],
            'options' => [
                'حمام سباحة' => 'Swimming Pool',
                'ساونا' => 'Sauna',
                'جاكوزي' => 'Jacuzzi',
                'حمام مغربي' => 'Moroccan Bath',
                'قسم سيدات' => 'Ladies Section',
                'مدرب شخصي' => 'Personal Trainer',
                'استشارة تغذية' => 'Nutrition Advice',
                'خزائن ودش' => 'Lockers & Showers',
                'انتظار سيارات' => 'Parking',
                'حضانة أطفال' => 'Creche',
            ],
        ],
    ],

    'links' => [

        /*
         * ── the axis a gym actually prices on ────────────────────────────
         *
         * «الأنشطة الرياضية» is the line — كارديو، سباحة — and what changes
         * what a line costs is the SUBSCRIPTION, which is the whole commercial
         * shape of a gym. «نظام التعاقد» already asks exactly that, written for
         * the coworking desks and already answering for a maid and a lawyer.
         * Borrowed, not written a fourth time.
         *
         * «بالزيارة» and «بالإقامة» stay out: a day pass is «يومي», and nobody
         * lives at the gym.
         */
        130 => ['نظام التعاقد' => ['بالساعة', 'يومي', 'أسبوعي', 'شهري', 'ربع سنوي', 'سنوي']],
        519 => ['نظام التعاقد' => ['بالساعة', 'يومي', 'شهري', 'ربع سنوي', 'سنوي']],
        521 => ['نظام التعاقد' => ['بالساعة', 'يومي', 'شهري']],


        /*
         * The indoor half. A health club runs the gym floor, the pool and the
         * classes; it does not field a football team.
         */
        516 => [
            'الأنشطة الرياضية' => [
                'كمال أجسام / حديد', 'كارديو', 'كروس فيت', 'سبينينج',
                'يوجا', 'بيلاتس', 'زومبا', 'آيروبكس',
                'سباحة', 'ملاكمة', 'كيك بوكسينج', 'تنس طاولة',
            ],
        ],

        /*
         * A driver is booked WITH a vehicle. The freight sizes stay with the
         * freight trades.
         */
        85 => [
            'مركبات النقل والركاب' => [
                'ميني ڤان 7', 'ميكروباص 15', 'ميني باص 25 راكب',
                'كوتش', 'باص 50 راكب',
            ],
        ],
    ],
];
