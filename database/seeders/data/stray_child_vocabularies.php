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
    |--------------------------------------------------------------------------
    | «قسّم مرافق النادي الرياضي» — owner, 2026-08-16
    |--------------------------------------------------------------------------
    | It was written as one `descriptive` list and it was two. Eight of its rows
    | are the PLACE and three of them are the BILL, and a group can only have
    | one price role, so as long as they shared a name the trainer, the
    | nutritionist and the bath attendant could not be priced at all.
    |
    | ## The line the split is drawn on
    |
    | A FACILITY is a room the member uses himself — the pool, the sauna, the
    | lockers, the car park. A SERVICE is a person the club assigns and bills
    | for. That is why «كيدز ايريا» and «حضانة أطفال» both exist and are now on
    | opposite sides: one is a room with toys in it, the other is staff watching
    | your child while you train, and every club in Egypt sells the second by
    | the month.
    |
    | It is also why «ساونا» and «حمام مغربي» part company, which is the pair
    | that makes the rule earn its keep. Both are hot rooms. You walk into the
    | sauna alone and it comes with the membership; a حمام مغربي is an
    | appointment with an attendant, and the platform already prices it that way
    | — «حمام مغربي وسبا» is a `line` in «خدمات الكوافير والتجميل».
    |
    | ## Precedent for the shape
    |
    | «صيدلية» carries «أقسام الصيدلية» and «خدمات الصيدلية» — the shelves and
    | the counter — and 42 of the platform's 206 children carry more than one
    | `line` group. And a hotel already has exactly the descriptive half of this:
    | «مرافق الإقامة» lists سبا beside المسبح and prices neither, because what a
    | hotel sells is the room. What a gym sells is the subscription, which is
    | «الأنشطة الرياضية», and now also the three things it charges extra for.
    |
    | ## What moves
    |
    | Only `options.group_id`, the same promise MenuBandSplitSeeder and
    | GroceryAisleSplitSeeder make. Not one `category_child_option` row is
    | touched: every club keeps every row it had, under two headings instead of
    | one. The five carriers are unchanged and so is every withdrawal the owner
    | has made against them — «ملاعب كرة» is not in this file's children and
    | does not become one.
    */
    'regroup' => [
        'خدمات النادي الرياضي' => [
            'name_en' => 'Club Services',
            'price_role' => 'line',
            'from' => 'مرافق النادي الرياضي',
            'options' => ['مدرب شخصي', 'استشارة تغذية', 'حمام مغربي', 'حضانة أطفال'],
        ],
    ],

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
    |
    | Eight rows since the 2026-08-16 split; the three the club bills for moved
    | to «خدمات النادي الرياضي» below. «كيدز ايريا» was added in the admin by
    | hand and is written down here so the file describes what exists — this
    | seeder matches an option by group AND Arabic name, so naming it creates
    | nothing and only stops the next reader wondering where it came from.
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
                'قسم سيدات' => 'Ladies Section',
                'خزائن ودش' => 'Lockers & Showers',
                'انتظار سيارات' => 'Parking',
                'كيدز ايريا' => "Kids' Area",
            ],
        ],

        /*
        | ── what the club charges extra for ────────────────────────────────
        |
        | `line`, and the second one these children carry: «الأنشطة الرياضية» is
        | the subscription and this is everything sold beside it. Both are
        | things a member buys, which is what a line means — «صيدلية» has held
        | the same pair, shelves and services, since it was written.
        |
        | Three of the four are one person's time. «حضانة أطفال» is the fourth
        | and it is the one that looks like a facility until you ask who is in
        | the room: «كيدز ايريا» is the room, and it stayed above.
        |
        | Same five carriers as the facilities list. The links already exist —
        | `regroup` moved the options, not the rows — and this entry is what
        | keeps them if the group is ever rebuilt from nothing.
        */
        'خدمات النادي الرياضي' => [
            'name_en' => 'Club Services',
            'price_role' => 'line',
            'children' => [130, 519, 521, 516, 520],
            'options' => [
                'مدرب شخصي' => 'Personal Trainer',
                'استشارة تغذية' => 'Nutrition Advice',
                'حمام مغربي' => 'Moroccan Bath',
                'حضانة أطفال' => 'Creche',
            ],
        ],

        /*
        | ── «جراج» #119 was answering with another trade's list ────────────
        |
        | It carried «مركبات النقل والركاب» — باص ٥٠ راكب، جامبو، مقطورة — which
        | is the list of vehicles a HAULIER hires out. A جراج does not hire out
        | a bus; it parks one. The child booked appointments and could name
        | nothing it books them for, and the borrowed list is why it read as a
        | twin of «مغسلة سيارات» in the merge audit.
        |
        | `line`: it carries booking and no retail, so the priced row is the
        | stay itself — an hour, a night, a monthly space.
        |
        | The vehicle list is declared empty for it in `child_option_scopes.php`.
        */
        'خدمات الجراج والانتظار' => [
            'name_en' => 'Parking Services',
            'price_role' => 'line',
            'children' => [119],
            'options' => [
                'انتظار بالساعة' => 'Hourly Parking',
                'انتظار يومي' => 'Daily Parking',
                'اشتراك شهري' => 'Monthly Parking',
                'مكان ثابت محجوز' => 'Reserved Bay',
                'انتظار مغطى' => 'Covered Parking',
                'انتظار مكشوف' => 'Open-air Parking',
                'انتظار حافلات ونقل' => 'Bus & Truck Parking',
                'خدمة صف السيارة' => 'Valet Parking',
                'شحن سيارات كهربائية' => 'EV Charging',
                'غسيل داخل الجراج' => 'In-garage Wash',
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
