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
| **Nothing on «مأذون شرعى» #178 was re-added.** He withdrew all six of its
| options by hand on 2026-08-10 — «كاش», «تقسيط», «فردي», «فريق عمل»,
| «أونلاين», «خاص». Read them: every one belongs to the two GENERIC groups.
| He removed the noise, and he never removed a trade line because the child
| had none. So «خدمات المأذون الشرعي» below is not the seeder overruling him;
| the six he took off stay off, and the withdrawal record is consulted on every
| link so they cannot come back. He asked for this himself on 2026-08-11:
| «هل هناك مهام للمأذون الشرعى تضاف الى مكتبه».
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

    'root' => 'offices',

    'name_en_suffix' => 'Office',

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

        /*
         * ── the axis the whole root was missing ──────────────────────────
         *
         * «مكاتب» is where the platform's B2B trades live, and not one of its
         * thirteen children could say WHO it works for. The platform's own
         * shared axes are all goods-shaped — «الاستبدال والإرجاع» (86
         * children), «التسليم والاستلام» (113), «حالة المنتج» (87) — and none
         * of them says anything about an accountant. «الجمهور المستهدف» #94
         * looks like the one until you read it: حريمي، رجالي، أطفال. It is
         * what a clothes shop dresses.
         *
         * So: an accountant who does factories, a lawyer who does companies, a
         * security firm that does government sites. `descriptive`, because it
         * narrows a search and does not by itself change a price.
         *
         * Given to all thirteen. A مأذون will tick «أفراد» and nothing else,
         * and that is the axis working, not the axis misapplied.
         */
        'نوع العملاء' => [
            'name_en' => 'Client Types',
            'price_role' => 'descriptive',
            'children' => [10, 11, 62, 70, 77, 78, 123, 144, 167, 178, 205, 231, 253],
            'options' => [
                'أفراد' => 'Individuals',
                'شركات ومؤسسات' => 'Companies',
                'مصانع' => 'Factories',
                'جهات حكومية' => 'Government Bodies',
                'جمعيات ومنظمات' => 'NGOs & Associations',
            ],
        ],

        /*
         * The registrar's office. Six acts, and they are acts of DOCUMENTATION
         * — that is what separates a مأذون from a lawyer: «تخصصات المحاماة» has
         * «أحوال شخصية وأسرة», which is litigating a family matter, while these
         * are the register entries themselves.
         *
         * A general notary's work (توكيلات، توثيق عقود) is the شهر العقاري's and
         * is not here; the مأذون's authority is the marriage register.
         */
        'خدمات المأذون الشرعي' => [
            'name_en' => 'Marriage Registrar Services',
            'price_role' => 'line',
            'children' => [178],
            'options' => [
                'عقد قران' => 'Marriage Contract',
                'عقد زواج أجانب' => 'Foreign Nationals Marriage',
                'توثيق طلاق' => 'Divorce Registration',
                'إثبات رجعة' => 'Reconciliation Registration',
                'قائمة منقولات زوجية' => 'Marital Property Inventory',
                'استخراج قسيمة زواج' => 'Marriage Certificate Copy',
            ],
        ],

        /*
         * Where the contract is signed, and it is the registrar's other price.
         * A مأذون who comes to the house or to the hall charges for coming; the
         * act is the same act. Line + modifier, the same shape as the coworking
         * office and its secretary — and the reason it is not three lines.
         */
        'مكان العقد' => [
            'name_en' => 'Signing Location',
            'price_role' => 'modifier',
            'children' => [178],
            'options' => [
                'بالمكتب' => 'At the Office',
                'بالمنزل' => 'At Home',
                'بالقاعة أو الفندق' => 'At the Venue',
            ],
        ],
    ],

    /*
    | ── a group renamed ──────────────────────────────────────────────────
    | «نظام الاشتراك» was born on 2026-08-11 for the coworking desks and by the
    | end of the same day it was answering for a maid, a lawyer and a guarding
    | contract. Its question was never subscription: it is **on what basis is
    | this bought** — بالساعة، بالزيارة، بالمهمة، شهري — and a lawyer on a
    | «نظام اشتراك» reads as nonsense in the admin.
    |
    | Renamed rather than cloned. A second group repeating شهري/سنوي/بالساعة is
    | the duplication that cost a day in the food lists, and one honest name is
    | cheap today and expensive next month.
    */
    'rename' => [
        'نظام الاشتراك' => ['نظام التعاقد', 'Engagement Basis'],
    ],

    /*
    | ── options added to a group that already exists ─────────────────────
    | The two the desks did not need. «بالمهمة» is what every professional
    | office answers — an accountant per filing, a lawyer per case, a decorator
    | per project — and «سنوي» is the retainer beneath it.
    */
    'extend' => [
        'نظام التعاقد' => [
            'بالزيارة' => 'Per Visit',
            'بالإقامة' => 'Live-in',
            'بالمهمة' => 'Per Assignment',
            'سنوي' => 'Annual',
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
            'نظام التعاقد' => ['بالزيارة', 'يومي', 'أسبوعي', 'شهري', 'بالإقامة'],
        ],

        // The digital four of «تخصصات الدعاية والإعلان». A page manager does
        // not print a banner. Its engagement basis is in the same entry on
        // purpose: a duplicate array key here is silent — PHP keeps the last
        // one and the earlier list simply never happens.
        205 => [
            'تخصصات الدعاية والإعلان' => [
                'تسويق رقمي وسوشيال ميديا',
                'إعلانات ممولة',
                'تصميم جرافيك',
                'تصوير وإنتاج',
            ],
            // إدارة صفحة اشتراك بطبعها
            'نظام التعاقد' => ['شهري', 'ربع سنوي', 'سنوي'],
        ],

        /*
         * ── the engagement basis, per trade ──────────────────────────────
         *
         * Only where the SAME line genuinely prices two ways. A مأذون is paid
         * per act and a printing house per job, so neither is here: a modifier
         * with one possible value asks a question that has no second answer,
         * which is noise on the pricing screen, not an axis.
         */
        10 => ['نظام التعاقد' => ['بالمهمة', 'شهري', 'سنوي']],           // محاسب مقيد شهريًا
        11 => ['نظام التعاقد' => ['بالمهمة', 'شهري']],                    // حملة ≠ إدارة مستمرة
        77 => ['نظام التعاقد' => ['بالمهمة', 'شهري']],                    // شحنة ≠ عقد مستورد
        78 => ['نظام التعاقد' => ['بالمهمة', 'بالساعة']],                 // مشروع ≠ ساعة استشارة
        123 => ['نظام التعاقد' => ['بالمهمة', 'بالساعة', 'شهري']],        // «إشراف على التنفيذ» شهري
        167 => ['نظام التعاقد' => ['بالمهمة', 'شهري', 'سنوي']],           // بالقضية ≠ مستشار الشركة
        253 => ['نظام التعاقد' => ['بالمهمة', 'شهري', 'سنوي']],           // تأمين حفل ≠ عقد حراسة
    ],
];
