<?php

/*
|--------------------------------------------------------------------------
| «مهن وحرفيين» — the largest debt on the platform
|--------------------------------------------------------------------------
| Owner, 2026-08-11: «ابدأ».
|
| Twenty-four of the twenty-seven crafts carried «الدفع والسداد» and «نمط تقديم
| الخدمة» and nothing else — **121 merchants**. A نقاش with 35 of them could not
| say what he paints; a سباك with 9 could not say he unblocks a drain. Only
| «كوافير» and «صيانة اجهزة منزلية» had a vocabulary.
|
| Every one of the 24 is booking-only — no retail, no menu — so the OFFICES
| rule applies: the job IS the priced row, and these are `line` groups. A
| customer pays for «تسليك مجاري» exactly the way he pays for «تنظيف خزانات».
|
| ── Nine borrowed, none cloned ────────────────────────────────────────────
|
| The workshop remodel had already written most of these words. A حداد and a
| «ورشة معادن» do the same work at different scales, and the platform should
| not hold the word «لحام» twice.
|
|   نجار موبيليا #49  ← «تخصصات ورش الأثاث», whole
|   منجد #287         ← its upholstery rows
|   أويمجى #299       ← its carving rows. `name_en` is **Wood Carving**, and
|                       #302 already has «حفر وأويما» — the trade name is
|                       colloquial and the English column is what read it.
|   استرجي #300       ← its finishing rows. `name_en` is **Wood Painter**.
|   حداد #259         ← «تخصصات ورش المعادن», the smithing rows
|   صيانة تكيف #15    ← «تخصصات ورش الأجهزة», the cooling rows
|   خدمات نظافة #58   ← «الخدمات المنزلية», the cleaning rows — NOT the nanny,
|                       the cook or the live-in housekeeper, which are the
|                       agency's business and not a cleaning crew's
|   فني الوميتال #18  ← the aluminium rows of «أنواع الأبواب والشبابيك»
|   نجار تنده #26     ← its awning rows. `name_en` is **Awnings worker**.
|
| ── Seven new groups, four of them shared by a family of trades ───────────
|
| «جبسيوم بورد», «جبس وديكورات», «جبس وكرانيش» and «جي أر سي» are four children
| of one craft, and each takes a different slice of one list rather than four
| lists that repeat each other. Same for the four building trades and the two
| flooring trades. The slices are what keep them apart: a مبيض محارة lays no
| foundation and a باركيه fitter lays no ceramic.
*/

return [

    'root' => 'professions',

    'name_en_suffix' => 'Craft',

    'groups' => [

        /*
         * Options created here and linked BELOW, per child, because these four
         * groups are each shared by a family of trades that take different
         * slices. An empty `children` creates the vocabulary without granting
         * it to anyone.
         */

        'أعمال الجبس والأسقف' => [
            'name_en' => 'Gypsum & Ceiling Works', 'price_role' => 'line', 'children' => [],
            'options' => [
                'جبس بورد' => 'Gypsum Board',
                'أسقف معلقة' => 'Suspended Ceilings',
                'كرانيش' => 'Cornices',
                'ديكورات جبسية' => 'Gypsum Decor',
                'إضاءة مخفية' => 'Cove Lighting',
                'أعمدة وقواطع' => 'Columns & Partitions',
                'جي أر سي' => 'GRC Elements',
                'واجهات جي أر سي' => 'GRC Facades',
                'ترميم جبس' => 'Gypsum Repair',
            ],
        ],

        'أعمال البناء والمحارة' => [
            'name_en' => 'Masonry & Plastering Works', 'price_role' => 'line', 'children' => [],
            'options' => [
                'مباني طوب' => 'Brickwork',
                'محارة وبياض' => 'Plastering',
                'خرسانات' => 'Concrete Works',
                'أساسات' => 'Foundations',
                'واجهات حجرية' => 'Stone Facades',
                'حجر هاشمي' => 'Hashemi Stone Work',
                'نحت وتشكيل' => 'Carving & Shaping',
                'تكسير وإزالة' => 'Demolition & Removal',
                'ترميم مباني' => 'Building Restoration',
                /*
                 * 2026-08-12. «مبيض محارة» and «تكسير ونحت» each answered with
                 * TWO rows, and the shortage was in this list rather than in
                 * either trade: a plasterer's day is بؤج وأوتار before it is
                 * محارة, and half the demolition jobs on a finished building
                 * are a core drill, not a hammer.
                 */
                'محارة جبس (بلاستر)' => 'Gypsum Plastering',
                'بؤج وأوتار' => 'Plaster Screeds & Guides',
                'طرطشة ورشة' => 'Spatter Dash Coat',
                'قص وتخريم خرسانة' => 'Concrete Cutting & Coring',
            ],
        ],

        'أعمال الأرضيات' => [
            'name_en' => 'Flooring Works', 'price_role' => 'line', 'children' => [],
            'options' => [
                'سيراميك وبورسلين' => 'Ceramic & Porcelain',
                'رخام وجرانيت' => 'Marble & Granite Fitting',
                'وزر وحوائط' => 'Skirting & Wall Tiling',
                'باركيه' => 'Parquet Fitting',
                'HDF' => 'HDF Flooring',
                'أرضيات فينيل' => 'Vinyl Flooring',
                'إبوكسي' => 'Epoxy Flooring',
                'نجيل صناعي' => 'Artificial Turf',
                'ترميم أرضيات' => 'Floor Repair',
            ],
        ],

        'أعمال الستائر والتنجيد' => [
            'name_en' => 'Curtain & Upholstery Works', 'price_role' => 'line', 'children' => [75],
            'options' => [
                'تفصيل ستائر' => 'Made-to-Measure Curtains',
                'تركيب ستائر' => 'Curtain Fitting',
                'ستائر رول وبليسيه' => 'Roller & Pleated Blinds',
                'تنجيد كنب' => 'Sofa Upholstery',
                'تنجيد كراسي' => 'Chair Upholstery',
                'تغيير أقمشة' => 'Re-covering',
                'ترميم مفروشات' => 'Furnishing Repair',
            ],
        ],

        'أعمال الكهرباء' => [
            'name_en' => 'Electrical Works', 'price_role' => 'line', 'children' => [89],
            'options' => [
                'تأسيس كهرباء' => 'First-Fix Wiring',
                'تمديدات وتوصيلات' => 'Wiring & Connections',
                'لوحات وقواطع' => 'Boards & Breakers',
                'إنارة وتركيب وحدات' => 'Lighting Installation',
                'كشف وإصلاح أعطال' => 'Fault Finding & Repair',
                'مولدات ومنظمات' => 'Generators & Stabilisers',
                'أنظمة منزل ذكي' => 'Smart Home Systems',
                'تأريض ووقاية' => 'Earthing & Protection',
            ],
        ],

        'أعمال السباكة' => [
            'name_en' => 'Plumbing Works', 'price_role' => 'line', 'children' => [227],
            'options' => [
                'تأسيس سباكة' => 'First-Fix Plumbing',
                'تسليك مجاري' => 'Drain Unblocking',
                'كشف تسريبات' => 'Leak Detection',
                'تركيب أطقم حمامات' => 'Sanitary Ware Fitting',
                'سخانات' => 'Water Heaters',
                'مواسير ومحابس' => 'Pipes & Valves',
                'مضخات ورفع مياه' => 'Pumps & Water Lifting',
                'صيانة أعطال' => 'Repairs',
            ],
        ],

        'أعمال الدهانات' => [
            'name_en' => 'Painting Works', 'price_role' => 'line', 'children' => [206],
            'options' => [
                'دهانات بلاستيك' => 'Emulsion Painting',
                'دهانات زيت' => 'Oil Painting',
                'ورق حائط' => 'Wallpapering',
                'ستوكو وتأثيرات' => 'Stucco & Effects',
                'رش دوكو' => 'Spray Finishing',
                'دهان واجهات' => 'Facade Painting',
                'معجون وتجهيز حوائط' => 'Filling & Preparation',
                'عزل قبل الدهان' => 'Sealing & Priming',
            ],
        ],

        'أعمال الدش والاستقبال' => [
            'name_en' => 'Satellite & Reception Works', 'price_role' => 'line', 'children' => [251],
            'options' => [
                'تركيب دش' => 'Dish Installation',
                'ضبط وبرمجة رسيفر' => 'Receiver Tuning',
                'دش مركزي للعمارات' => 'Central Dish Systems',
                'شبكات استقبال' => 'Distribution Networks',
                'تركيب رسيفرات' => 'Receiver Fitting',
                'صيانة أعطال الاستقبال' => 'Reception Repairs',
            ],
        ],
    ],

    /*
    | Every child appears ONCE, however many groups it takes.
    */
    'links' => [

        // ── the four gypsum trades, one list, four slices ────────────────
        132 => ['أعمال الجبس والأسقف' => ['جبس بورد', 'أسقف معلقة', 'أعمدة وقواطع', 'إضاءة مخفية', 'ترميم جبس']],
        134 => ['أعمال الجبس والأسقف' => ['كرانيش', 'ديكورات جبسية', 'ترميم جبس']],
        133 => ['أعمال الجبس والأسقف' => ['جبس بورد', 'أسقف معلقة', 'كرانيش', 'ديكورات جبسية', 'إضاءة مخفية', 'ترميم جبس']],
        129 => ['أعمال الجبس والأسقف' => ['جي أر سي', 'واجهات جي أر سي', 'ديكورات جبسية']],

        // ── the four building trades ─────────────────────────────────────
        147 => ['أعمال البناء والمحارة' => ['مباني طوب', 'محارة وبياض', 'خرسانات', 'أساسات']],
        220 => ['أعمال البناء والمحارة' => [
            'محارة وبياض', 'ترميم مباني',
            // 2026-08-12: the three rows a plasterer's day actually runs on.
            'محارة جبس (بلاستر)', 'بؤج وأوتار', 'طرطشة ورشة',
        ]],
        179 => ['أعمال البناء والمحارة' => ['واجهات حجرية', 'حجر هاشمي', 'نحت وتشكيل', 'ترميم مباني']],
        // Half the demolition jobs on a finished building are a core drill.
        80 => ['أعمال البناء والمحارة' => ['تكسير وإزالة', 'نحت وتشكيل', 'قص وتخريم خرسانة']],

        // ── the two flooring trades ──────────────────────────────────────
        106 => ['أعمال الأرضيات' => ['سيراميك وبورسلين', 'رخام وجرانيت', 'وزر وحوائط', 'ترميم أرضيات']],
        208 => ['أعمال الأرضيات' => ['باركيه', 'HDF', 'أرضيات فينيل', 'إبوكسي', 'نجيل صناعي', 'ترميم أرضيات']],

        // ── the nine borrowings ──────────────────────────────────────────

        // A carpenter is a furniture workshop with one bench.
        49 => ['تخصصات ورش الأثاث' => 'all'],

        287 => ['تخصصات ورش الأثاث' => ['تنجيد', 'كنب وركنات', 'ترميم وإصلاح أثاث']],

        // «Wood Carving». The trade name is colloquial; name_en read it.
        // أرابيسك ومشربية added 2026-08-12 — the headline job of the trade,
        // and nothing in the list could say it.
        299 => ['تخصصات ورش الأثاث' => ['حفر وأويما', 'ديكورات خشبية', 'أرابيسك ومشربية']],

        // «Wood Painter». Finishing is دوكو and ورنيش and تعتيق before it is
        // «دهان»; the two rows it had named neither.
        300 => ['تخصصات ورش الأثاث' => [
            'دهان وتلميع أثاث', 'ترميم وإصلاح أثاث',
            'رش دوكو', 'ورنيش وسيلر', 'تعتيق وباتينا',
        ]],

        259 => [
            'تخصصات ورش المعادن' => [
                'حدادة', 'لحام', 'كريتال وشبابيك حديد',
                'مشغولات حديد ودرابزين', 'قص وثني معادن',
            ],
        ],

        15 => ['تخصصات ورش الأجهزة' => ['صيانة تبريد وتكييف', 'شحن فريون', 'تركيب أجهزة', 'صيانة دورية']],

        /*
         * The CLEANING rows only. «جليسة أطفال», «طباخ منزلي» and «عاملة
         * منزلية» belong to the agency that places household staff — «خدمات
         * منزلية» #144 under مكاتب — and a cleaning crew is not that trade.
         */
        58 => [
            'الخدمات المنزلية' => [
                'تنظيف منازل', 'تنظيف مكاتب وشركات', 'تنظيف بعد التشطيب',
                'تنظيف سجاد ومفروشات', 'تنظيف خزانات', 'تنظيف واجهات زجاجية',
                'تعقيم وتطهير', 'مكافحة حشرات',
            ],
        ],

        18 => [
            'أنواع الأبواب والشبابيك' => [
                'ألومنيوم', 'أبواب ونوافذ سحب', 'واجهات زجاجية (سيكوريت)',
                'شيش وحصيرة', 'شبك حماية وناموسية', 'كوالين ومقابض وإكسسوارات',
            ],
        ],

        // «Awnings worker».
        26 => ['أنواع الأبواب والشبابيك' => [
            'سواتر ومظلات', 'شيش وحصيرة',
            // 2026-08-12: the trade is named «تنده» and could not say it.
            'تندات ومظلات قماش', 'بيرجولات', 'مظلات انتظار سيارات',
        ]],

        /*
         * ── two that had a vocabulary on the WRONG axis ──────────────────
         *
         * Both were served by the goods passes and both are booking-only
         * crafts here, so what they hold is a `modifier` describing stock
         * where the priced row is the JOB.
         *
         * «صيانة اجهزة منزلية» #22 carries «أنواع الأجهزة الكهربائية» — which
         * appliance — and needed the workshop's list of what is DONE to it.
         * «رخام وجرانيت» #174 carries «أنواع الرخام والجرانيت», the stone,
         * and needed the fitting work. Both keep the modifier: which appliance
         * and which stone still qualify the price.
         */
        22 => ['تخصصات ورش الأجهزة' => 'all'],

        174 => ['أعمال الأرضيات' => ['رخام وجرانيت', 'وزر وحوائط', 'ترميم أرضيات']],
    ],
];
