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

    'links' => [

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
