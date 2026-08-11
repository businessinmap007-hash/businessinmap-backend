<?php

/*
|--------------------------------------------------------------------------
| What each child of «شركات» does or deals in
|--------------------------------------------------------------------------
| Owner, 2026-08-11: «انتقل الى الاب شركات».
|
| Seventy children, and 53 could already name their trade — most of them
| because they are the SAME child rows the factory pass had just given a
| vocabulary to. «شركات» inherited the work: one trade, one answer, whichever
| root the customer came through.
|
| **Seventeen were mute, and they split in two.** This root is not «مكاتب» and
| not «مصانع» — it is BOTH, so the completion rule is applied per child, not
| per root:
|
|   9 service companies  carry booking + offers and no retail. The service IS
|                        the priced row, so `line` — the offices rule.
|   8 goods traders      carry retail + delivery. Their priced rows are catalog
|                        products, so `modifier` — the factory rule.
|
| «مقاولات» #72 alone carries **71 merchants**, the largest mute child found in
| this whole sweep: seventy-one contractors and not one could say whether it
| pours concrete or fits out an apartment.
|
| ── Borrowed, not cloned ──────────────────────────────────────────────────
|
|   برمجيات #261   ← «خدمات البرمجة والتطوير», whole. A software COMPANY and
|                    «برمجة» under تكنولوجيا are the same trade.
|   رخام #173      ← «أنواع الرخام والجرانيت», whole. #174 «رخام وجرانيت» is a
|                    separate child under مصانع and مهن; they are not merged
|                    here, only handed the same words.
|   ألمونتال #17   ← the ALUMINIUM rows of «أنواع الأبواب والشبابيك». It fits no
|                    wooden door and no manual shutter.
|   رحلات #285     ← the trip rows of «خدمات السياحة والسفر» below. A trip
|                    operator issues no visa and books no hotel.
|   تحويل أموال #283 ← the transfer rows of «خدمات الصرافة والتحويل». It does
|                    not trade currency over a counter.
|
| ── The question that was open, now settled (2026-08-12) ──────────────────
|
| Whether «قطع غيار» #263 is really the car trade, and should borrow
| «نوع قطع الغيار» #260 with its own list retired.
|
| **It should not — the two lists are different AXES, not two versions of one.**
|
|   #260 «نوع قطع الغيار»     ميكانيكا، فرامل، فتيس، زجاج سيارات، عوادم…
|                             → WHICH SYSTEM of a car. Held by #44 alone.
|   #433 قطع الغيار حسب الآلة  سيارات، معدات ثقيلة، أجهزة منزلية، مصاعد…
|                             → WHICH MACHINE. Held by #263 alone.
|
| «قطع غيار سيارات» is one of nine rows on #263's list, not its whole trade:
| #44 is the car specialist, #263 the any-machine wholesaler, and under شركات
| both are right. Neither list is retired.
|
| Two rows DID come off #263's list, because they were not machines and each
| duplicated an axis it already carries: «قطع مستعملة» is
| «حالة المنتج / مستعمل», and «قطع مستوردة» is «نطاق التعامل / إستيراد». Two
| places to say one thing is two places to say it inconsistently. The narrowing
| is in `child_option_scopes.php`; the rows themselves are not deleted.
|
| And the group was renamed, because «أنواع قطع الغيار» beside
| «نوع قطع الغيار» is one letter apart for two different questions — which is
| what made the axes look like duplicates in the first place.
*/

return [

    'root' => 'companies',

    'name_en_suffix' => 'Company',

    /*
     * One letter apart from «نوع قطع الغيار» and asking a different question,
     * which is how the two came to look like duplicates of each other. The new
     * name says the axis out loud.
     */
    'rename' => [
        'أنواع قطع الغيار' => ['قطع الغيار حسب الآلة', 'Spare Parts by Machine'],
    ],

    'groups' => [

        /*
        | ── the nine service companies: `line` ───────────────────────────
        */

        'أعمال المقاولات' => [
            'name_en' => 'Contracting Works', 'price_role' => 'line', 'children' => [72],
            'options' => [
                'أعمال خرسانية' => 'Concrete Works',
                'مباني ومحارة' => 'Masonry & Plastering',
                'تشطيبات متكاملة' => 'Turnkey Fit-out',
                'أعمال كهروميكانيكا' => 'MEP Works',
                'عزل مائي وحراري' => 'Waterproofing & Insulation',
                'واجهات وكلادينج' => 'Facades & Cladding',
                'ترميم وتدعيم' => 'Restoration & Strengthening',
                'أعمال هدم' => 'Demolition',
                'محطات ومنشآت صناعية' => 'Industrial Plants',
                'إدارة مشروعات' => 'Project Management',
            ],
        ],

        'أعمال البنية التحتية' => [
            'name_en' => 'Infrastructure Works', 'price_role' => 'line', 'children' => [152],
            'options' => [
                'طرق ورصف' => 'Roads & Paving',
                'صرف صحي' => 'Sewerage',
                'شبكات مياه' => 'Water Networks',
                'شبكات كهرباء' => 'Power Networks',
                'شبكات اتصالات' => 'Telecom Networks',
                'كباري وأنفاق' => 'Bridges & Tunnels',
                'حفر وتسوية' => 'Excavation & Grading',
                'محطات معالجة' => 'Treatment Plants',
                'إنارة طرق' => 'Street Lighting',
            ],
        ],

        'أنواع التأمين' => [
            'name_en' => 'Insurance Lines', 'price_role' => 'line', 'children' => [153],
            'options' => [
                'تأمين سيارات' => 'Motor Insurance',
                'تأمين طبي' => 'Medical Insurance',
                'تأمين حياة' => 'Life Insurance',
                'تأمين ممتلكات' => 'Property Insurance',
                'تأمين ضد الحريق' => 'Fire Insurance',
                'تأمين بحري وشحن' => 'Marine & Cargo Insurance',
                'تأمين سفر' => 'Travel Insurance',
                'تأمين مسؤولية' => 'Liability Insurance',
                'تأمينات الشركات' => 'Corporate Insurance',
            ],
        ],

        /*
         * Strategy, research and reach — deliberately NOT the advertising list.
         * «تخصصات الدعاية والإعلان» is what an agency PRODUCES (a logo, a
         * banner, a shoot); this is what a marketing company decides and runs.
         * The one row they would share, digital marketing, is left to the
         * agency: a marketing company sells the campaign, not the post.
         */
        'خدمات التسويق' => [
            'name_en' => 'Marketing Services', 'price_role' => 'line', 'children' => [177],
            'options' => [
                'أبحاث ودراسات سوق' => 'Market Research',
                'خطط واستراتيجيات تسويق' => 'Marketing Strategy',
                'إدارة علامة تجارية' => 'Brand Management',
                'إدارة حملات ترويجية' => 'Campaign Management',
                'علاقات عامة' => 'Public Relations',
                'توزيع وتغطية' => 'Distribution & Coverage',
                'تسويق بالعمولة' => 'Affiliate Marketing',
                'دراسات جدوى' => 'Feasibility Studies',
                'تدريب فرق البيع' => 'Sales Training',
            ],
        ],

        'خدمات الصرافة والتحويل' => [
            'name_en' => 'Exchange & Transfer Services', 'price_role' => 'line', 'children' => [187],
            'options' => [
                'صرافة عملات' => 'Currency Exchange',
                'شراء وبيع عملات أجنبية' => 'Foreign Currency Trading',
                'تحويلات محلية' => 'Domestic Transfers',
                'تحويلات دولية' => 'International Transfers',
                'استلام حوالات' => 'Remittance Collection',
                'محافظ إلكترونية' => 'E-Wallets',
                'دفع فواتير' => 'Bill Payment',
                'شحن رصيد' => 'Top-up Services',
            ],
        ],

        'خدمات السياحة والسفر' => [
            'name_en' => 'Travel & Tourism Services', 'price_role' => 'line', 'children' => [279],
            'options' => [
                'حجز طيران' => 'Flight Booking',
                'حجز فنادق' => 'Hotel Booking',
                'برامج سياحية' => 'Tour Packages',
                'رحلات داخلية' => 'Domestic Trips',
                'رحلات خارجية' => 'International Trips',
                'رحلات سفاري وبرية' => 'Safari & Desert Trips',
                'رحلات بحرية' => 'Cruises',
                'حج وعمرة' => 'Hajj & Umrah',
                'تأشيرات' => 'Visa Services',
                'نقل ومواصلات سياحية' => 'Tourist Transport',
                'تأمين سفر' => 'Travel Cover',
            ],
        ],

        /*
        | ── the eight goods traders: `modifier` ──────────────────────────
        | Their priced rows are catalog products. These say what they stock.
        */

        'لوازم الستائر' => [
            'name_en' => 'Curtain Supplies', 'price_role' => 'modifier', 'children' => [9],
            'options' => [
                'أقمشة ستائر' => 'Curtain Fabrics',
                'قضبان وسكك' => 'Rails & Poles',
                'إكسسوار ستائر' => 'Curtain Accessories',
                'ستائر رول' => 'Roller Blinds',
                'ستائر معدنية' => 'Venetian Blinds',
                'بليسيه' => 'Pleated Blinds',
                'شيفون وتول' => 'Sheer & Voile',
                'بطانات وتبطين' => 'Linings',
            ],
        ],

        'الأنتيكات والتحف' => [
            'name_en' => 'Antiques & Objets', 'price_role' => 'modifier', 'children' => [21],
            'options' => [
                'تحف نحاسية' => 'Brassware',
                'أنتيكات خشبية' => 'Wooden Antiques',
                'لوحات فنية' => 'Paintings',
                'تماثيل ومجسمات' => 'Statues & Figurines',
                'ساعات قديمة' => 'Antique Clocks',
                'فضيات' => 'Silverware',
                'سجاد عتيق' => 'Antique Rugs',
                'مقتنيات نادرة' => 'Rare Collectibles',
            ],
        ],

        'مستلزمات المقاهي' => [
            'name_en' => 'Café Supplies', 'price_role' => 'modifier', 'children' => [66],
            'options' => [
                'ماكينات قهوة' => 'Coffee Machines',
                'مطاحن بن' => 'Coffee Grinders',
                'شيشة ومستلزماتها' => 'Shisha & Accessories',
                'أكواب وفناجين' => 'Cups & Glassware',
                'طاولات وكراسي' => 'Tables & Chairs',
                'خامات مشروبات' => 'Beverage Ingredients',
                'مستهلكات تقديم' => 'Serving Consumables',
            ],
        ],

        'أعمال التبريد والتكييف' => [
            'name_en' => 'HVAC & Refrigeration', 'price_role' => 'modifier', 'children' => [240],
            'options' => [
                'تكييف سبليت' => 'Split Units',
                'تكييف مركزي' => 'Central Air Conditioning',
                'تشيلرات' => 'Chillers',
                'ثلاجات ومجمدات عرض' => 'Display Fridges & Freezers',
                'غرف تبريد' => 'Cold Rooms',
                'شبكات ودكت' => 'Ducting',
                'قطع غيار تبريد' => 'HVAC Spare Parts',
                'تركيب وصيانة' => 'Installation & Maintenance',
            ],
        ],

        /*
         * WHICH MACHINE the parts are for — a different question from #44's
         * «نوع قطع الغيار», which is which SYSTEM of a car. See the note at the
         * top: neither borrows the other.
         *
         * «قطع مستوردة» and «قطع مستعملة» are still rows of this group (nothing
         * here is deleted) but are no longer offered to #263 — they answer
         * «نطاق التعامل» and «حالة المنتج», which it already carries.
         */
        'قطع الغيار حسب الآلة' => [
            'name_en' => 'Spare Parts by Machine', 'price_role' => 'modifier', 'children' => [263],
            'options' => [
                'قطع غيار سيارات' => 'Automotive Spares',
                'قطع غيار معدات ثقيلة' => 'Heavy Equipment Spares',
                'قطع غيار أجهزة منزلية' => 'Appliance Spares',
                'قطع غيار مكن صناعي' => 'Industrial Machinery Spares',
                'قطع غيار مصاعد' => 'Lift Spares',
                'قطع غيار تبريد وتكييف' => 'HVAC Spares',
                'قطع غيار دراجات' => 'Motorcycle Spares',
            ],
        ],

        'الأدوات المكتبية' => [
            'name_en' => 'Office Supplies', 'price_role' => 'modifier', 'children' => [270],
            'options' => [
                'ورق وطباعة' => 'Paper & Print Media',
                'أدوات كتابة' => 'Writing Instruments',
                'ملفات وتنظيم' => 'Files & Organisation',
                'أحبار وتونر' => 'Ink & Toner',
                'أجهزة مكتبية صغيرة' => 'Small Office Machines',
                'مستلزمات مدرسية' => 'School Supplies',
                'أثاث مكتبي خفيف' => 'Light Office Furniture',
            ],
        ],
    ],

    /*
    | Every child appears ONCE here, however many groups it takes. A duplicate
    | array key is silent — PHP keeps the last and the earlier entry simply
    | never happens — and 261, 283 and 285 each wanted two.
    */
    'links' => [
        // A software COMPANY and «برمجة» under تكنولوجيا are one trade.
        261 => [
            'خدمات البرمجة والتطوير' => 'all',
            'نوع العملاء' => 'all',
        ],

        // #174 «رخام وجرانيت» keeps its own child row; only the words are shared.
        173 => ['أنواع الرخام والجرانيت' => 'all'],

        /*
         * «استيراد وتصدير» #150 is booking-only — a service company — and
         * carried only «أصناف المنتجات الغذائية», which says what it TRADES
         * IN and never what it does. The customs broker's list is the work.
         */
        150 => [
            'خدمات التخليص الجمركي' => [
                'تخليص وارد', 'تخليص صادر', 'شهادات منشأ ومطابقة',
                'بطاقة استيرادية وتسجيل', 'تخزين وأرضيات', 'نقل من الميناء',
                'تأمين على البضائع', 'استشارات جمركية',
            ],
        ],

        // The aluminium rows only. It fits no wooden door and no manual shutter.
        17 => [
            'أنواع الأبواب والشبابيك' => [
                'ألومنيوم',
                'أبواب ونوافذ سحب',
                'واجهات زجاجية (سيكوريت)',
                'شيش وحصيرة',
                'سواتر ومظلات',
                'شبك حماية وناموسية',
                'كوالين ومقابض وإكسسوارات',
            ],
        ],

        // A trip operator issues no visa and books no hotel.
        285 => [
            'خدمات السياحة والسفر' => [
                'رحلات داخلية',
                'رحلات خارجية',
                'رحلات سفاري وبرية',
                'رحلات بحرية',
                'برامج سياحية',
                'نقل ومواصلات سياحية',
            ],
            'نوع العملاء' => 'all',
        ],

        // It moves money; it does not trade currency over a counter.
        283 => [
            'خدمات الصرافة والتحويل' => [
                'تحويلات محلية',
                'تحويلات دولية',
                'استلام حوالات',
                'محافظ إلكترونية',
                'دفع فواتير',
                'شحن رصيد',
            ],
            'نوع العملاء' => 'all',
        ],

        /*
         * «نوع العملاء», built for «مكاتب» hours earlier. These nine are B2B
         * service companies in exactly the sense that root is — a contractor
         * who builds for factories, an insurer who covers government bodies —
         * and each stands under «شركات» alone, so a shared row reaches nobody
         * it should not.
         */
        72 => ['نوع العملاء' => 'all'],
        152 => ['نوع العملاء' => 'all'],
        153 => ['نوع العملاء' => 'all'],
        177 => ['نوع العملاء' => 'all'],
        187 => ['نوع العملاء' => 'all'],
        279 => ['نوع العملاء' => 'all'],
    ],
];
