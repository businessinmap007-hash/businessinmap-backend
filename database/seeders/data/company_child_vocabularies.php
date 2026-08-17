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
|
| ── The borrow that became a merge (2026-08-16) ───────────────────────────
|
| «تحويل أموال» #283 used to stand here, lent the transfer rows of «خدمات
| الصرافة والتحويل» and denied the two counter rows: it moves money, it does
| not trade currency. That is a true sentence about a trade and it was the
| wrong shape — the difference between the two children WAS those two rows,
| and a difference of two rows is an option, not a child.
|
| Both stood under «شركات» and nowhere else, both carried this same group
| whose own name joins the two words, both sold through booking and
| business_offers, and between them they had one merchant. Folded into #187,
| renamed «صرافة وتحويل أموال». A remittance shop now ticks six rows and
| leaves «صرافة عملات» alone, which says the same thing per MERCHANT instead
| of per trade.
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

        /*
         * ⚠ «لا تدمج لوازم ستائر وستائر وديكور فهم بندين مختلفين» — owner,
         * 2026-08-12, refusing a merge this list's shape invites.
         *
         * «لوازم ستائر» #9 supplies the PARTS — rails, linings, sheer by the
         * metre — and «ستائر و ديكور» #76 sells the finished curtain and hangs
         * it. Their option sets look identical to an audit that only compares
         * option ids, which is exactly how the merge was proposed. Do not.
         */
        'لوازم الستائر' => [
            'name_en' => 'Curtain Supplies', 'price_role' => 'line', 'children' => [9],
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
            'name_en' => 'Antiques & Objets', 'price_role' => 'line', 'children' => [21],
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
            'name_en' => 'Café Supplies', 'price_role' => 'line', 'children' => [66],
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
            'name_en' => 'HVAC & Refrigeration', 'price_role' => 'line', 'children' => [240],
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
        /*
         * Three children the 23:41 save left with nothing to say ANYWHERE, so
         * there was nothing to mirror back. Each had carried a single stray row
         * of «مستلزمات المزارع» — a lift company does not sell feed troughs;
         * that was sediment, not a vocabulary — so restoring the row would
         * restore a mistake. They get the words their trade actually uses.
         *
         * `line`, all three: they carry booking and menu under شركات and no
         * retail, so the priced row is the JOB — a lift supplied and installed,
         * a cabin delivered — not a catalog product.
         */
        'أنظمة المصاعد والسلالم' => [
            'name_en' => 'Lift & Escalator Systems', 'price_role' => 'line', 'children' => [90],
            'options' => [
                'مصاعد ركاب' => 'Passenger Lifts',
                'مصاعد بضائع' => 'Goods Lifts',
                'مصاعد بانوراما' => 'Panoramic Lifts',
                'مصاعد سيارات' => 'Car Lifts',
                'سلالم كهربائية' => 'Escalators',
                'ممرات متحركة' => 'Moving Walkways',
                'تحديث وتجديد مصاعد' => 'Lift Modernisation',
                'عقود صيانة دورية' => 'Lift Maintenance Contracts',
            ],
        ],

        'الكرفانات والوحدات الجاهزة' => [
            'name_en' => 'Caravans & Portable Units', 'price_role' => 'line', 'children' => [47],
            'options' => [
                'كرفان سكني' => 'Living Caravan',
                'كرفان مكتبي' => 'Office Caravan',
                'غرف حراسة ومداخل' => 'Guard Cabins',
                'دورات مياه متنقلة' => 'Portable Toilets',
                'مخازن ومستودعات جاهزة' => 'Prefab Stores',
                'بيوت وشاليهات جاهزة' => 'Prefab Homes & Chalets',
                'تأجير كرفانات' => 'Caravan Rental',
            ],
        ],

        'معدات السوبر ماركت' => [
            'name_en' => 'Supermarket Equipment', 'price_role' => 'line', 'children' => [273],
            'options' => [
                'ثلاجات وفاترينات عرض' => 'Display Fridges & Cabinets',
                'غرف تبريد وتجميد' => 'Cold & Freezer Rooms',
                'أرفف وجندولات' => 'Shelving & Gondolas',
                'كاونتر كاشير' => 'Checkout Counters',
                'موازين وطابعات باركود' => 'Scales & Label Printers',
                'عربات وسلال تسوق' => 'Trolleys & Baskets',
                'تركيب وصيانة معدات' => 'Equipment Installation & Service',
            ],
        ],

        /*
         * The axis the parts trade actually prices on, and neither child had
         * it. «فرامل تويوتا» is not one price: أصلي وكيل and تجاري are the same
         * part, the same brand and the same system at a multiple of each other,
         * which is more than «ماركات السيارات» or «نوع قطع الغيار» moves it.
         *
         * A factory answers it too — it makes تجاري, or مُجدَّد, or أصلي under
         * contract. «نظام التصنيع / تصنيع بعلامة العميل» says who owns the
         * brand on the box, not what grade the buyer is holding.
         *
         * No «مستعمل» row: «حالة المنتج» owns جديد · مستعمل, and the two rows
         * that came off #263 came off for exactly that reason. «أصلي مستورد»
         * is not «نطاق التعامل / إستيراد» either — one is where the PART came
         * from, the other whether the BUSINESS imports.
         */
        'درجة قطعة الغيار' => [
            'name_en' => 'Spare Part Grade', 'price_role' => 'modifier', 'children' => [44, 263],
            'options' => [
                'أصلي وكيل' => 'Genuine (Agent)',
                'أصلي مستورد' => 'Genuine (Imported)',
                'تجاري' => 'Aftermarket',
                'مُجدَّد' => 'Remanufactured',
            ],
        ],

        /*
        | `line` since 2026-08-16, and this file's own price_role had to move
        | with option_price_roles.php or the two seeders overwrite each other on
        | every run — the vocabulary seeder writes the role it declares here and
        | the authority corrects it afterwards, so a disagreement is a flip-flop
        | nobody sees.
        |
        | «قطع غيار» #263 held this group and NOTHING that priced, so the
        | reader's promotion rule lifted both its modifiers and offered «أصلي
        | وكيل» as a thing to buy. This list IS what the any-machine wholesaler
        | sells, so it is the line, and «درجة قطعة الغيار» goes back to
        | qualifying it. See the block in option_price_roles.php.
        */
        'قطع الغيار حسب الآلة' => [
            'name_en' => 'Spare Parts by Machine', 'price_role' => 'line', 'children' => [263],
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
            'name_en' => 'Office Supplies', 'price_role' => 'line', 'children' => [270],
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
    | The repair half of the 2026-08-11 23:41 bulk save (see
    | BulkPickerSlipRevertSeeder). It pinned «أنواع الأجهزة الرياضية» onto
    | SIXTY-NINE children of شركات and withdrew what each of them was saying, so
    | a contractor sold treadmills and could not say «أعمال خرسانية».
    |
    | Every child below still says the right words as a shop, a factory or a
    | showroom — only the شركات scope was emptied — so the mirror is the exact
    | tool: it copies the ids the child ALREADY holds, and therefore cannot
    | widen anything `child_option_scopes.php` had narrowed.
    */
    'mirror_links' => [
        8 => ['أنواع الإكسسوارات'],                          // اكسسوار
        11 => ['تخصصات الدعاية والإعلان'],                  // دعاية وإعلان
        50 => ['أنواع الأبواب والشبابيك'],                  // باب وشباك
        56 => ['أثاث وتشطيب منزلي', 'طراز الأثاث'],        // نجف
        60 => ['موضة وعناية شخصية'],                        // ملابس جاهزة
        83 => ['أقسام المنزل والعناية'],                    // منظفات
        // «أجهزة كهربائية» #88 is not mirrored: its list must be SHARED across
        // its four roots — see BulkPickerSlipRevertSeeder::SHARED_AGAIN.
        95 => ['موضة وعناية شخصية', 'الجمهور المستهدف'],   // أقمشة
        114 => ['أقسام الطازج واللحوم', 'مواصفات المنتج الغذائي'], // فواكة
        115 => ['أثاث وتشطيب منزلي', 'طراز الأثاث'],       // مفروشات
        116 => ['أثاث وتشطيب منزلي', 'طراز الأثاث'],       // آثاث
        158 => ['بنود المنيو', 'مواصفات المنتج الغذائي'],  // عصائر
        168 => ['موضة وعناية شخصية'],                       // جلود وشنط وأحذية
        170 => ['مستلزمات المزارع'],                        // مواشي
        // «حلويات» #210 was mirrored here and is gone: it was detached from
        // «شركات» on 2026-08-16 («حذف من الشركات الابن مفاتيح - حلويات»), and
        // a mirror writes rows AGAINST THIS ROOT. Left in the list it wrote
        // five option rows naming a root the child had just left — reachable
        // by nothing, and the exact debris a detachment exists to clear.
        292 => ['أقسام الطازج واللحوم', 'مواصفات المنتج الغذائي'], // خضروات
    ],

    /*
    | Every child appears ONCE here, however many groups it takes. A duplicate
    | array key is silent — PHP keeps the last and the earlier entry simply
    | never happens — and 261, 283 and 285 each wanted two.
    */
    'links' => [
        /*
         * «مواد غذائية ومنظفات» #110 (8 merchants) held the whole twenty-row
         * food range and lost it in the same save. It carries `retail`, so the
         * catalog holds its priced rows and this stays a modifier — the
         * «ماركات السيارات» shape, saying what the wholesaler DEALS IN.
         */
        110 => ['أصناف المنتجات الغذائية' => 'all'],

        // A software COMPANY and «برمجة» under تكنولوجيا are one trade.
        261 => [
            'خدمات البرمجة والتطوير' => 'all',
            'نوع العملاء' => 'all',
            // 2026-08-16, with «برمجة» #233 under «تكنولوجيا»: it is one trade
            // and it answers the same under both roots. A software house is
            // quoted for the build and kept on a retainer, and neither half had
            // a word — «أمن» #253 two entries down has had this axis since the
            // offices pass while its own twin under تكنولوجيا did not.
            'نظام التعاقد' => ['بالمهمة', 'شهري', 'سنوي'],
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

        /*
         * «ألمونتال» #17 had the seven aluminium rows of the door list, on the
         * reading that it fits no wooden door and no manual shutter. The owner
         * corrected the reading on 2026-08-12: «هو لبيع قطاعات الالمونتال نفسها
         * وليس الشباك والباب». It sells the extrusion to the workshop that makes
         * the window. Its list is «قطاعات ومنتجات الألومنيوم» in
         * `trade_vocabularies.php`, and the door list is a declared empty for it
         * in `child_option_scopes.php`.
         *
         * Left as a note rather than deleted quietly: this entry re-granted the
         * seven rows on every run and would have undone the narrowing.
         */

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

        /*
         * «تحويل أموال» #283 stood here with the transfer rows and without the
         * two counter rows, on the reading that it moves money and does not
         * trade currency. It was folded into #187 on 2026-08-16 and the child
         * is gone — see the note at the head of this file.
         *
         * The narrowing it expressed is gone with it, and correctly: a shop
         * that only sends remittances now ticks the six transfer rows and
         * leaves «صرافة عملات» alone, which is the same statement made in the
         * place that can also be true of one merchant and false of the next.
         */

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

    /*
    |--------------------------------------------------------------------------
    | 2026-08-17 — three trades that go silent when you browse «شركات»
    |--------------------------------------------------------------------------
    | Found reviewing «مكاتب», and the children are the ones that stand under
    | both roots. «دعاية وإعلان» #11، «طباعة» #231 and «أمن» #253 each answer
    | «نمط تقديم الخدمة» under مكاتب and answer NOTHING under شركات. It is the
    | same child row and the same firm; only the storefront differs.
    |
    | It is not a ruling. Every withdrawal these three carry in that group is
    | scoped to root 19 — «خاص» off #11 and #231, «أونلاين» off #253, all on
    | 2026-08-10 — and there is no scope-22 decision on the group for any of
    | them. And `child_option_groups.php` DECLARES the axis for all three:
    | 'companies:11', 'companies:231' and 'companies:253' are each `$fieldWork`,
    | which is `service_mode` + `payment_terms`.
    |
    | ── Why a declared bundle never arrived ───────────────────────────────────
    |
    | Two halves, and neither is wrong on its own.
    |
    | `CategoryChildOptionScope::syncFor()` grants at `category_id = $rootId`:
    | anything the owner ADDS with a root in hand becomes a row for that root
    | alone. That is exactly right — it is what lets «آثاث» mean one thing under
    | ورش and another under معارض.
    |
    | `ChildOptionGroupsSeeder::applyChildTargets()` then asks whether the child
    | already holds the bundle's options with `where('child_id')->whereIn(
    | 'option_id', $managed)` — **no `category_id` filter**. A root-19 row
    | answers yes, `$toAdd` comes back empty, and the declaration for شركات can
    | never be delivered. The row written under one root MASKS the grant under
    | the other.
    |
    | Left alone rather than fixed there: teaching that seeder about scope would
    | change what it writes for every one of the 247 children it manages, which
    | is not a change to make while chasing one axis on three of them. It is
    | worth knowing that it can only ever see a child, never a child under a
    | root.
    |
    | ── The four are named, and never `'all'` ─────────────────────────────────
    |
    | The GROUP holds seven rows, not four: «سيارة بسائق» and «سيارة بدون سائق»
    | belong to the car-hire trades and «زيارة منزلية» to the workshops that
    | come to you. `'all'` was tried here first and handed an advertising agency
    | a chauffeured car — the same trap «الغرف» sets in the property file. What
    | `child_option_groups.php` declares for these children is `service_mode`,
    | and `service_mode` is those four ids.
    |
    | The ledger then does the narrowing on its own:
    | `ChildOptionDecisions::blockedByChild()` is ROOT-AGNOSTIC, so a withdrawal
    | under مكاتب refuses the grant under شركات too. Naming all four lands each
    | child on exactly the three rows it already shows under مكاتب — #11 and
    | #231 without «خاص», #253 without «أونلاين» — and says so in one list
    | instead of three.
    |
    | ── What is NOT copied ────────────────────────────────────────────────────
    |
    | «نظام التعاقد» on «تنسيق حفلات» #70 and «طباعة» #231, which is also root-19
    | only. He was in the شركات screen for #70 on 2026-08-16 at 17:27 — he
    | withdrew «خاص» there — and pinned #11's and #253's contract rows under root
    | 22 two minutes earlier in the same sitting. He handled that axis under
    | شركات and left those two as they are.
    */
    'root_links' => [
        11 => ['نمط تقديم الخدمة' => ['فردي', 'أونلاين', 'خاص', 'فريق عمل']],   // دعاية وإعلان
        231 => ['نمط تقديم الخدمة' => ['فردي', 'أونلاين', 'خاص', 'فريق عمل']],  // طباعة
        253 => ['نمط تقديم الخدمة' => ['فردي', 'أونلاين', 'خاص', 'فريق عمل']],  // أمن
    ],
];
