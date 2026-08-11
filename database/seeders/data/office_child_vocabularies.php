<?php

/*
|--------------------------------------------------------------------------
| What each child of «مكاتب» actually sells
|--------------------------------------------------------------------------
| Owner, 2026-08-11: «هناك اقسام متعددة لخدمات منزلية تحت الاب مكاتب ولكن لا
| يوجد اى خدمة فعليه تحتها … وراجع باقى الابناء تحت الاب مكاتب واضف اليهم ما
| ينقصهم».
|
| Seven of the thirteen children of root 19 could name their trade — محاسبة has
| «تخصصات المحاسبة», محاماه «تخصصات المحاماة», هندسية، ديكور، دعاية وإعلان, and
| منطقة عمل مشتركة gained «مساحات العمل» the same day. **Six could not.** They
| carried «نمط تقديم الخدمة» and a payment group and nothing else: a printing
| house could not say it prints, a security company could not say it guards.
|
| The price test decides the role, as everywhere else: a customer pays for
| «تنظيف خزانات» and for «حراسة فعاليات», so those are `line`. He does not pay
| for «شهري» — it changes what the line costs — so that is `modifier`.
|
| ── What is deliberately NOT here ─────────────────────────────────────────
|
| **«مأذون شرعى» #178.** The owner withdrew every one of its six options by
| hand on 2026-08-10 (`category_child_option_decisions`, source `admin`),
| «فردي» and «فريق عمل» included. A child stripped that deliberately is not a
| gap; adding a vocabulary here would be the seeder overruling him. See
| [seeder-withdrawal-record].
|
| **Payment groups.** «كاش/تقسيط» look missing on محاسبة، هندسية and محاماه, and
| they are not: he has been REMOVING them — from تخليص جمركي (2026-08-10),
| أمن (2026-08-10) and خدمات منزلية (2026-08-11 13:36, thirteen minutes before
| he asked for this). Adding them back is the thing he keeps undoing.
|
| **«إدارة صفحات» gets no list of its own.** It is the social half of
| «دعاية وإعلان» and that child already owns «تخصصات الدعاية والإعلان» #37. A
| second near-identical list is the duplication the food-vocabulary split spent
| a day undoing, so it borrows four of those seven rows instead — the digital
| ones. The physical ones (لافتات، مطبوعات) stay behind, which is the whole
| difference between the two children.
|
| Children standing under two roots — تنسيق حفلات، طباعة، أمن also hang from
| «شركات» — get ONE shared vocabulary (`category_id = 0`). One trade, one
| answer, whichever door the customer came through.
*/

return [

    /*
    | ── new line groups ──────────────────────────────────────────────────
    | group name_ar => [name_en, price_role, [child ids], [option ar => en]]
    */
    'groups' => [

        /*
         * A home-services office is an AGENCY: it sends people to your flat.
         * It is not a craftsman — نقاش، سباك، كهربائي and مبلط are children of
         * «مهن وحرفيين» in their own right, and none of their work is listed
         * here or the same job would be sold twice under two trades.
         */
        'الخدمات المنزلية' => [
            'name_en' => 'Home Services',
            'price_role' => 'line',
            'children' => [144],
            'options' => [
                'تنظيف منازل' => 'House Cleaning',
                'تنظيف مكاتب وشركات' => 'Office Cleaning',
                'تنظيف بعد التشطيب' => 'Post-Construction Cleaning',
                'تنظيف سجاد ومفروشات' => 'Carpet & Upholstery Cleaning',
                'تنظيف خزانات' => 'Water Tank Cleaning',
                'تنظيف واجهات زجاجية' => 'Facade & Glass Cleaning',
                'مكافحة حشرات' => 'Pest Control',
                'تعقيم وتطهير' => 'Disinfection',
                'غسيل وكي ملابس' => 'Laundry & Ironing',
                'عاملة منزلية' => 'Housekeeper',
                'جليسة أطفال' => 'Babysitting',
                'رعاية مسنين' => 'Elderly Care',
                'طباخ منزلي' => 'Home Cook',
                'تنظيم وترتيب المنزل' => 'Home Organising',
            ],
        ],

        /*
         * The planner is paid for what he PROVIDES. «أنواع المناسبات» #29 says
         * which occasion, and it is deliberately not linked here: it is a
         * `line` group for the halls that rent by the occasion, and a child
         * carrying two line groups gives the pricing screen two answers to the
         * same question.
         */
        'خدمات تنظيم الحفلات' => [
            'name_en' => 'Event Planning Services',
            'price_role' => 'line',
            'children' => [70],
            'options' => [
                'تنظيم كامل للحفل' => 'Full Event Management',
                'كوشة وخلفيات' => 'Stage & Backdrops',
                'تنسيق زهور وديكور' => 'Floral & Decor',
                'بوفيه وضيافة' => 'Catering & Hospitality',
                'دي جي وصوتيات' => 'DJ & Sound',
                'إضاءة وشاشات' => 'Lighting & Screens',
                'تصوير فوتوغرافي وفيديو' => 'Photography & Video',
                'فرق وعروض حية' => 'Live Acts',
                'حفلات أطفال' => 'Kids Parties',
                'دعوات ومطبوعات المناسبة' => 'Invitations & Stationery',
                'تنسيق مؤتمرات ومعارض' => 'Conference & Expo Setup',
                'تأجير مستلزمات الحفلات' => 'Event Equipment Rental',
            ],
        ],

        /*
         * A customs broker files papers. The MOVING of the goods — شحن جوي،
         * بحري، بري — is the freight companies' `schedules` vocabulary and is
         * not repeated here, or the same leg is sold by two trades.
         */
        'خدمات التخليص الجمركي' => [
            'name_en' => 'Customs Clearance Services',
            'price_role' => 'line',
            'children' => [77],
            'options' => [
                'تخليص وارد' => 'Import Clearance',
                'تخليص صادر' => 'Export Clearance',
                'فسح جمركي' => 'Customs Release',
                'شهادات منشأ ومطابقة' => 'Origin & Conformity Certificates',
                'بطاقة استيرادية وتسجيل' => 'Import Licence & Registration',
                'تخزين وأرضيات' => 'Bonded Storage',
                'نقل من الميناء' => 'Port Haulage',
                'تأمين على البضائع' => 'Cargo Insurance',
                'استشارات جمركية' => 'Customs Advisory',
                'تظلمات وتسويات جمركية' => 'Customs Disputes',
            ],
        ],

        'خدمات الطباعة' => [
            'name_en' => 'Printing Services',
            'price_role' => 'line',
            'children' => [231],
            'options' => [
                'طباعة أوفست' => 'Offset Printing',
                'طباعة ديجيتال' => 'Digital Printing',
                'طباعة كبيرة وبنرات' => 'Large Format & Banners',
                'كروت شخصية' => 'Business Cards',
                'فواتير ودفاتر' => 'Invoices & Notebooks',
                'كتب ومجلات' => 'Books & Magazines',
                'ستيكرز ولاصقات' => 'Stickers & Labels',
                'طباعة على الملابس' => 'Garment Printing',
                'طباعة على الهدايا' => 'Gift Printing',
                'تغليف وتجليد' => 'Binding & Lamination',
                'حفر وقص ليزر' => 'Laser Cutting & Engraving',
            ],
        ],

        /*
         * Manpower first, because that is what a guarding company sells. The
         * systems half — cameras, alarms — is «أمن وسلامة» #254 under
         * «تكنولوجيا»; the two overlap in the market and the platform keeps
         * them apart, so only the rows a guarding contract actually includes
         * are here.
         */
        'خدمات الأمن والحراسة' => [
            'name_en' => 'Security & Guarding Services',
            'price_role' => 'line',
            'children' => [253],
            'options' => [
                'حراسة مقرات ومنشآت' => 'Premises Guarding',
                'حراسة عقارات سكنية' => 'Residential Guarding',
                'تأمين فعاليات ومناسبات' => 'Event Security',
                'مرافق شخصي' => 'Close Protection',
                'أمن مولات ومطاعم' => 'Retail & Venue Security',
                'نقل أموال' => 'Cash In Transit',
                'غرفة مراقبة ومتابعة' => 'Monitoring Room',
                'دوريات متحركة' => 'Mobile Patrols',
                'تفتيش وبوابات' => 'Screening & Gates',
                'تدريب أفراد أمن' => 'Guard Training',
            ],
        ],
    ],

    /*
    | ── options added to a group that already exists ─────────────────────
    | «نظام الاشتراك» #374 was created for the coworking desks and asks exactly
    | the question a domestic contract asks: how often, and for how long. A
    | maid by the visit and a maid living in are two prices for one line, which
    | is the definition of a modifier. Reused rather than cloned under another
    | name; the two children link different subsets of it.
    */
    'extend' => [
        'نظام الاشتراك' => [
            'بالزيارة' => 'Per Visit',
            'بالإقامة' => 'Live-in',
        ],
    ],

    /*
    | ── links into groups that already exist ─────────────────────────────
    | child id => [group name_ar => [option name_ar, …]]
    */
    'links' => [
        // The contract shapes a home-services office actually offers. «بالساعة»
        // and «ربع سنوي» are the desk's, not the maid's.
        144 => [
            'نظام الاشتراك' => ['بالزيارة', 'يومي', 'أسبوعي', 'شهري', 'بالإقامة'],
        ],

        // The digital four of «تخصصات الدعاية والإعلان». A page manager does
        // not print a banner.
        205 => [
            'تخصصات الدعاية والإعلان' => [
                'تسويق رقمي وسوشيال ميديا',
                'إعلانات ممولة',
                'تصميم جرافيك',
                'تصوير وإنتاج',
            ],
        ],
    ],
];
