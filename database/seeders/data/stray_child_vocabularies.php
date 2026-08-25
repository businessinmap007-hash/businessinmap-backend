<?php

/*
|--------------------------------------------------------------------------
| The last two mute children on the platform
|--------------------------------------------------------------------------
| Neither belongs to a root that needed a pass — each is the one child its
| siblings left behind — so they are collected here rather than given a root
| file of their own.
|
| **«نادي صحي» #516** stood under «الرياضة» beside جيم، نادي رياضي، حمام سباحة
| and أكاديمية رياضية, and borrowed the indoor half of what those four carry —
| no football pitch, no horse riding. The owner retired it outright on
| 2026-08-14: «حذف نادي صحي ونكتفى بنادي رياضي وأكاديمية»
| (`child_root_moves.php`). It stands under no root any more — dropped from
| both groups' `children` below, and from its own narrowed `links` entry.
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
    | one. The carriers are unchanged (four now, not the five this note was
    | first written for — «نادي صحي» #516 was retired 2026-08-14 and dropped
    | 2026-08-25) and so is every withdrawal the owner has made against them —
    | «ملاعب كرة» is not in this file's children and does not become one.
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

        /*
        |----------------------------------------------------------------------
        | 2026-08-18 — «مستكشف لاعبين» #550
        |----------------------------------------------------------------------
        | Owner: «مستكشف لاعبين … المستكشف يحدد الرياضات الخاصة به ومن الممكن
        | ان تكون لكرة القدم فقط».
        |
        | Two groups, because he answers two questions, and the platform's
        | one-role-per-group rule decides which is which.
        |
        | ── What he sells ────────────────────────────────────────────────────
        |
        | A scout is not paid per sport; he is paid per JOB — a look at a boy, a
        | written report, a trial camp, a placement. That is the `line`, and it
        | is why «الأنشطة الرياضية» #28 could not be reused for it: that group is
        | already a `line` naming what a club TEACHES, held by the gym, the
        | academy, the coach and the pool. Handing it to the scout would price
        | him per sport and say he sells football.
        |
        | ── Which market he works in ─────────────────────────────────────────
        |
        | `descriptive`, because it is a filter and not a price — «ومن الممكن ان
        | تكون لكرة القدم فقط» is exactly a filter. It overlaps #28 by design and
        | not by accident: that list mixes competitive sports with gym classes —
        | يوجا، كارديو، سبينينج، آيروبكس، بيلاتس — and nobody scouts yoga. These
        | are the sports that actually have academies, trials and transfers in
        | Egypt, which is the only list a scout's filter should offer.
        */
        'خدمات استكشاف اللاعبين' => [
            'name_en' => 'Player Scouting Services',
            'price_role' => 'line',
            'children' => [550],
            'options' => [
                'معاينة وتقييم لاعب' => 'Player Assessment',
                'تقرير كشفي مكتوب' => 'Written Scouting Report',
                'معسكر اختبارات' => 'Trial Camp',
                'إلحاق بأكاديمية أو نادي' => 'Academy & Club Placement',
                'تمثيل ووكالة لاعبين' => 'Player Representation',
                'متابعة موسم كامل' => 'Full-Season Tracking',
                'تصوير فيديو احترافي للاعب' => 'Professional Player Reel',
            ],
        ],

        /*
        | Renamed from «الرياضات المستهدفة» on 2026-08-18, and the rename had to
        | land HERE in the same change: this file declares the group by name, so
        | left as it was the next run would mint the old name again as an empty
        | second group and link 550 to it — one list of sports becoming two.
        |
        | «ناشئ موهوب» #551 answers the same eighteen. The heading is neutral
        | because it now reads on both screens: on a scout it is the sports he
        | covers, on a boy the sport he plays. A separate «موهوب فى» holding the
        | identical rows would make the match this whole feature exists for — the
        | scout's sports against the boy's — a join across two vocabularies.
        |
        | The option `name_en` values still say «Scouting — …». They are globally
        | unique and already linked, so renaming eighteen of them buys a tidier
        | English label at the price of a collision hunt; left for a day when
        | something else touches them.
        */
        'الرياضات' => [
            'name_en' => 'Sports',
            'price_role' => 'descriptive',
            'children' => [550, 551],
            'options' => [
                'كرة قدم' => 'Scouting — Football',
                'كرة سلة' => 'Scouting — Basketball',
                'كرة يد' => 'Scouting — Handball',
                'كرة طائرة' => 'Scouting — Volleyball',
                'سباحة' => 'Scouting — Swimming',
                'ألعاب قوى' => 'Scouting — Athletics',
                'تنس' => 'Scouting — Tennis',
                'تنس طاولة' => 'Scouting — Table Tennis',
                'جمباز' => 'Scouting — Gymnastics',
                'ملاكمة' => 'Scouting — Boxing',
                'مصارعة' => 'Scouting — Wrestling',
                'جودو' => 'Scouting — Judo',
                'كاراتيه' => 'Scouting — Karate',
                'تايكوندو' => 'Scouting — Taekwondo',
                'رفع أثقال' => 'Scouting — Weightlifting',
                'مبارزة' => 'Scouting — Fencing',
                'فروسية' => 'Scouting — Equestrian',
                'دراجات' => 'Scouting — Cycling',
            ],
        ],
        'مرافق النادي الرياضي' => [
            'name_en' => 'Club Facilities',
            'price_role' => 'descriptive',
            'children' => [130, 519, 521, 520],  // «نادي صحي» #516 retired 2026-08-14
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
        | Same carriers as the facilities list above. The links already exist —
        | `regroup` moved the options, not the rows — and this entry is what
        | keeps them if the group is ever rebuilt from nothing.
        */
        'خدمات النادي الرياضي' => [
            'name_en' => 'Club Services',
            'price_role' => 'line',
            'children' => [130, 519, 521, 520],  // «نادي صحي» #516 retired 2026-08-14
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

        /*
        | ── «مغسلة سيارات» #46 was priced on the wrong axis, 2026-08-17 ────
        |
        | Six of the seven children of «سيارات» are the vehicle. A driver, a tow
        | truck, a passenger fleet, a haulier — what each of them sells IS the
        | ميني ڤان or the مقطورة, so «مركبات النقل والركاب» is correctly their
        | line and the trade reads straight off it.
        |
        | A car wash is the one that is not. It sells WORK PERFORMED ON a
        | vehicle, and it had only the vehicle: its line was ميكروباص، ربع نقل،
        | سيدان، SUV — which is what the customer ARRIVES in, never what he
        | pays for. Two cars in the same bay, one for a rinse and one for a
        | ceramic coat, were one price by construction.
        |
        | The owner has already curated the vehicle half: on 2026-08-14 he took
        | باص ٥٠ راكب، معدات ثقيلة، جامبو and مقطورة off it — nothing that size
        | fits through a wash bay — and pinned سيدان، SUV، بيك أب. That list is
        | right and stays; it is simply the modifier wearing the line's clothes,
        | and a wash bay really does charge a microbus more than a sedan.
        |
        | So the service becomes the line beside it. Eight rows, and every one
        | of them is a separate figure on the board outside an Egyptian
        | مغسلة — a rinse is not a steam wash and neither is a nano coat.
        */
        'خدمات غسيل السيارات' => [
            'name_en' => 'Car Wash Services',
            'price_role' => 'line',
            'children' => [46],
            'options' => [
                'غسيل خارجي' => 'Exterior Wash',
                'غسيل داخلي وخارجي' => 'Interior & Exterior Wash',
                'غسيل بالبخار' => 'Steam Wash',
                'تلميع وبوليش' => 'Polish & Buffing',
                'تنظيف مقاعد وفرش' => 'Seat & Upholstery Cleaning',
                'معالجة نانو سيراميك' => 'Nano Ceramic Coating',
                'غسيل موتور' => 'Engine Wash',
                'تشميع وتلميع زجاج' => 'Wax & Glass Polish',
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
        521 => ['نظام التعاقد' => ['بالساعة', 'يومي', 'شهري', 'سنوي']],

        /*
         * ── the subscription a pin froze ────────────────────────────────
         *
         * «سنوي» #1447 was minted at 14:54 on 2026-08-11, eighty minutes after
         * the five rows above it. On 2026-08-13 23:22 the owner pinned the
         * contract axis on «ملاعب كرة», «أكاديمية رياضية» and «حمام سباحة» —
         * and pinned exactly the five that existed at 13:34. He recorded no
         * ruling on «سنوي» at all: on «مدرب», where he DID see it, he pinned
         * it and withdrew بالزيارة، بالإقامة and بالمهمة beside it.
         *
         * So this is not an overrule, it is the row he was never shown. An
         * Egyptian sports academy sells an اشتراك سنوي — the season IS its
         * contract — and an annual pool membership is just as ordinary. The
         * other five stay unnamed here because they are his pins and the file
         * should not start re-granting what he might later take off.
         *
         * «ملاعب كرة» is deliberately left out: a five-a-side pitch is sold by
         * the hour and its longest honest contract is the quarterly league
         * block it already carries.
         */
        520 => [
            'نظام التعاقد' => ['سنوي'],

            /*
             * …and who the session is for. «أطفال» is a MODIFIER here, not a
             * shop's department: a karate class for children prices apart from
             * the adult one, which is exactly why the owner pinned it onto
             * «جيم» on 2026-08-16. The academy is the child in this root that
             * is mostly children, and it was the one that could not say so.
             */
            'الجمهور المستهدف' => ['أطفال'],
        ],

        /*
         * The trainer sells a plan as well as an hour.
         *
         * «مدرب» #547 carries all 45 activities and none of «خدمات النادي
         * الرياضي», with no ruling either way — the same silence «سنوي» sat in.
         * «مدرب شخصي» would be circular on this child and «حمام مغربي» is a
         * club's bathhouse, but «استشارة تغذية» is a separate thing a trainer
         * charges separately for, and the platform already knows it: the
         * training module gives a trainer workout AND nutrition plans, so his
         * vocabulary could deliver the second and not price it.
         */
        547 => ['خدمات النادي الرياضي' => ['استشارة تغذية']],

        // «نادي صحي» #516's narrowing of «الأنشطة الرياضية» stood here until
        // the child was retired 2026-08-14 (see the file's top doc). It
        // stands under no root any more — nothing left to narrow.

        /*
         * A driver is booked WITH a vehicle — and the owner answered which
         * vehicles on 2026-08-14 00:33, the other way round from this entry.
         *
         * It used to read «the freight sizes stay with the freight trades — a
         * driver-for-hire is not a haulier», and named the five passenger
         * sizes. He PINNED معدات ثقيلة، جامبو، ربع نقل، ربع نقل صندوق and
         * مقطورة onto it, and WITHDREW «كوتش» — which is the opposite ruling on
         * both ends, and the right one: a سائق in Egypt is hired to drive
         * whatever is standing there, and the man who drives a trailer for a
         * day is exactly this child. What he does not drive is a fifty-seat
         * coach, which comes with its own company.
         *
         * Written down here rather than left to the ledger: the file asked for
         * كوتش on every run and was refused, and asked for nothing else that he
         * had put there.
         */
        85 => [
            'مركبات النقل والركاب' => [
                'باص 50 راكب', 'معدات ثقيلة', 'جامبو',
                'ميكروباص 15', 'ميني باص 25 راكب', 'ميني ڤان 7',
                'ربع نقل', 'ربع نقل صندوق', 'مقطورة',
            ],
        ],
    ],
];
