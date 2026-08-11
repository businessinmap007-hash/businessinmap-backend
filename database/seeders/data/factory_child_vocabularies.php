<?php

/*
|--------------------------------------------------------------------------
| What each child of «مصانع» deals in
|--------------------------------------------------------------------------
| Owner, 2026-08-11: «انتقل الى الاب مصانع».
|
| ── This root is NOT «مكاتب», and the completion rule is different ────────
|
| The offices root was finished by giving every child a `line` group, because
| a service IS the priced row: a customer pays for «تنظيف خزانات». **A factory
| does not sell a phrase.** Nobody buys «طوب أحمر» the words — they buy a
| catalog product, and `catalog_products` is where the priced rows live (986 of
| them today). So the shape here is the one «ماركات السيارات» and «أنواع
| الأجهزة الكهربائية» already use: a `modifier` that says WHAT THE TRADE DEALS
| IN. It narrows a search and qualifies a price; it is not the price.
|
| Applying the offices rule here would have produced 26 line groups competing
| with the catalog for the same job.
|
| ── The measure that matters on a goods root ──────────────────────────────
|
| Every child of «مصانع» already carries five axes — حالة المنتج، الدفع
| والسداد، التسليم والاستلام، الاستبدال والإرجاع، نطاق التعامل. They are
| universal and say nothing about the trade. Measured against what is LEFT,
| **26 of the 44 children could not name a single thing they make**: an أخشاب
| factory with 8 merchants could not say it works in زان or MDF, a أدوات تجميل
| factory with 15 could not say whether it makes lipstick or shampoo.
|
| ── Borrowed, not cloned ──────────────────────────────────────────────────
|
| One of the 26 needs no list of its own:
|   - «سيفتى ومقاومة حرائق» #250 borrows the FIRE rows of «أنظمة الأمن
|     والسلامة» #388, built for «أمن وسلامة» hours earlier. It takes the fire
|     half and leaves the cameras and access control — a fire-equipment factory
|     installs no intercom.
|
| **«طباعة مواد تعبئة وتغليف» #232 was going to borrow «تعبئة وتغليف
| ومستلزمات» #11 and that was wrong.** `child_option_scopes.php` has declared
| `232 => []` against that group since 2026-08-08 — the first declared empty in
| the file, written precisely because an add-only seeder kept handing all
| eleven rows back. The owner's reason is one line: **it PRINTS packaging, it
| does not SELL it**; #204 is the sibling that stocks the cups and the foil.
| So it gets its own list of what a packaging printer actually does, and the
| declaration stands untouched.
| And «صيني ومستلزمات بيت» #145 links «الصيني والخزف» beside its own list: a
| household-goods trade sells china too, and #228's list is already the answer.
|
| ── Per-root, and this is the first use of it ─────────────────────────────
|
| Two axes belong to the FACTORY and not to the trade, so they are written with
| `category_id = 23` rather than 0. Every child here also stands under «شركات»
| or «المحلات» — «آثاث» under four roots — and asking a furniture SHOP for its
| minimum order quantity is nonsense. CategoryChildOptionScope was built for
| exactly this and its own docblock uses this exact example.
*/

return [

    'root' => 'factories',

    'name_en_suffix' => 'Factory',

    'groups' => [

        /*
        | ── the two axes that make a factory a factory ────────────────────
        */

        'نظام التصنيع' => [
            'name_en' => 'Manufacturing Basis',
            'price_role' => 'modifier',
            'scope' => 'root',
            // Every child of the root: it is the question the root exists to
            // ask, and the one thing that separates the maker from the seller.
            'children' => 'all',
            'options' => [
                'إنتاج جاهز من المخزون' => 'From Stock',
                'تصنيع حسب الطلب' => 'Made to Order',
                'تصنيع بعلامة العميل' => 'Private Label',
                'تجميع وتوريد' => 'Assembly & Supply',
                'تصنيع لدى الغير' => 'Contract Manufacturing',
            ],
        ],

        'الحد الأدنى للطلب' => [
            'name_en' => 'Minimum Order',
            'price_role' => 'descriptive',
            'scope' => 'root',
            'children' => 'all',
            'options' => [
                'بدون حد أدنى' => 'No Minimum',
                'كرتونة' => 'By Carton',
                'باليت' => 'By Pallet',
                'طن' => 'By Tonne',
                'حاوية' => 'By Container',
            ],
        ],

        /*
        | ── what each trade deals in ──────────────────────────────────────
        | Shared, because a brick factory and a brick wholesaler deal in the
        | same bricks. The trade is the trade under whichever root.
        */

        'أنواع الطوب' => [
            'name_en' => 'Brick Types', 'price_role' => 'modifier', 'children' => [34],
            'options' => [
                'طوب أحمر' => 'Red Brick', 'طوب أسمنتي' => 'Cement Brick',
                'طوب طفلي' => 'Clay Brick', 'طوب حراري' => 'Fire Brick',
                'بلوك خرساني' => 'Concrete Block', 'طوب زجاجي' => 'Glass Block',
                'إنترلوك' => 'Interlock',
                // Three the first pass missed. «طوب أبيض» is the sand-lime
                // block, a different kiln and a different price from the red;
                // «طوب خفاف» is the insulating block a roof is built with; and
                // an interlock press makes kerbstones and paving off the same
                // line, which is what its customer actually orders with it.
                'طوب أبيض (سيليكات)' => 'Sand-lime Brick',
                'طوب خفاف عازل' => 'Lightweight Insulating Block',
                'بردورات وبلاط أرصفة' => 'Kerbstones & Paving',
            ],
        ],

        'مستلزمات النجارة' => [
            'name_en' => 'Carpentry Fittings', 'price_role' => 'modifier', 'children' => [51],
            'options' => [
                'مفصلات' => 'Hinges', 'مجارى وسكك' => 'Drawer Runners',
                'أقفال وكوالين' => 'Locks', 'مقابض' => 'Handles',
                'غراء وصمغ' => 'Adhesives', 'مسامير وبراغي' => 'Screws & Nails',
                'إكسسوار مطابخ' => 'Kitchen Fittings', 'شرائح حواف' => 'Edge Banding',
            ],
        ],

        'أنواع السجاد' => [
            'name_en' => 'Carpet Types', 'price_role' => 'modifier', 'children' => [52],
            'options' => [
                'سجاد مشجر' => 'Patterned Rugs', 'سجاد سادة' => 'Plain Rugs',
                'سجاد شيرازي' => 'Shirazi Rugs', 'سجاد صوف' => 'Wool Rugs',
                'موكيت' => 'Wall-to-Wall Carpet', 'كليم' => 'Kilim',
                'سجاد أطفال' => 'Kids Rugs', 'دعاسات' => 'Door Mats',
            ],
        ],

        'مواد البناء الأساسية' => [
            'name_en' => 'Basic Building Materials', 'price_role' => 'modifier', 'children' => [55],
            'options' => [
                'أسمنت بورتلاندي' => 'Portland Cement', 'أسمنت مقاوم' => 'Sulphate-Resistant Cement',
                'أسمنت أبيض' => 'White Cement', 'مونة جاهزة' => 'Ready Mortar',
                'جبس' => 'Gypsum', 'جير' => 'Lime',
                'رمل وزلط' => 'Sand & Gravel', 'خرسانة جاهزة' => 'Ready-Mix Concrete',
            ],
        ],

        'أنواع أجهزة الكمبيوتر' => [
            'name_en' => 'Computer Hardware', 'price_role' => 'modifier', 'children' => [69],
            'options' => [
                'لابتوب' => 'Laptops', 'كمبيوتر مكتبي' => 'Desktops',
                'شاشات' => 'Monitors', 'طابعات وماسحات' => 'Printers & Scanners',
                'سيرفرات' => 'Servers', 'قطع ومكونات' => 'Components',
                'أجهزة شبكات' => 'Networking Hardware', 'تابلت' => 'Tablets',
                'إكسسوارات كمبيوتر' => 'Computer Accessories',
            ],
        ],

        'أصناف مستحضرات التجميل' => [
            'name_en' => 'Cosmetics Ranges', 'price_role' => 'modifier', 'children' => [73],
            'options' => [
                'مكياج' => 'Make-up', 'عناية بالبشرة' => 'Skincare',
                'عناية بالشعر' => 'Haircare', 'عطور' => 'Fragrance',
                'عناية بالأظافر' => 'Nail Care', 'عناية بالجسم' => 'Body Care',
                'مستحضرات طبيعية' => 'Natural Cosmetics', 'أدوات ومعدات تجميل' => 'Beauty Tools',
            ],
        ],

        'أنواع الزجاج' => [
            'name_en' => 'Glass Types', 'price_role' => 'modifier', 'children' => [126],
            'options' => [
                'زجاج سيكوريت' => 'Tempered Glass', 'زجاج دبل' => 'Double Glazing',
                'زجاج مزخرف' => 'Patterned Glass', 'مرايا' => 'Mirrors',
                'زجاج عاكس' => 'Reflective Glass', 'زجاج ملون' => 'Tinted Glass',
                'واجهات زجاجية' => 'Glass Facades', 'زجاج مقاوم للحريق' => 'Fire-Rated Glass',
            ],
        ],

        'الأدوات الصحية' => [
            'name_en' => 'Sanitary Ware', 'price_role' => 'modifier', 'children' => [138],
            'options' => [
                'أطقم حمامات' => 'Bathroom Suites', 'خلاطات' => 'Mixers & Taps',
                'أحواض' => 'Basins', 'بانيوهات' => 'Bathtubs',
                'كابينة شاور' => 'Shower Cabins', 'سيفونات' => 'Cisterns',
                'مواسير ووصلات' => 'Pipes & Fittings', 'سخانات' => 'Water Heaters',
                'إكسسوار حمام' => 'Bathroom Accessories',
            ],
        ],

        'مستلزمات المنزل' => [
            'name_en' => 'Household Goods', 'price_role' => 'modifier', 'children' => [145],
            'options' => [
                'أواني طهي' => 'Cookware', 'أدوات مائدة' => 'Tableware',
                'تخزين وحفظ' => 'Storage & Containers', 'أدوات مطبخ' => 'Kitchen Tools',
                'مفارش سفرة' => 'Table Linen', 'أطقم تقديم' => 'Serving Sets',
                'أدوات تنظيف منزلية' => 'Cleaning Tools',
            ],
        ],

        'الخراطيم والوصلات' => [
            'name_en' => 'Hoses & Couplings', 'price_role' => 'modifier', 'children' => [146],
            'options' => [
                'خراطيم هيدروليك' => 'Hydraulic Hoses', 'خراطيم هواء' => 'Air Hoses',
                'خراطيم مياه' => 'Water Hoses', 'خراطيم وقود' => 'Fuel Hoses',
                'خراطيم صناعية' => 'Industrial Hoses', 'وصلات ومحابس' => 'Couplings & Valves',
                'كلبسات' => 'Clamps',
            ],
        ],

        'المفاتيح والتوزيع الكهربائي' => [
            'name_en' => 'Switchgear & Wiring', 'price_role' => 'modifier', 'children' => [159],
            'options' => [
                'مفاتيح كهرباء' => 'Switches', 'بريزات' => 'Sockets',
                'لوحات توزيع' => 'Distribution Boards', 'قواطع' => 'Circuit Breakers',
                'كابلات وأسلاك' => 'Cables & Wiring', 'مواسير وعلب كهرباء' => 'Conduits & Boxes',
                'مفاتيح ذكية' => 'Smart Switches',
            ],
        ],

        'أنواع الرخام والجرانيت' => [
            'name_en' => 'Marble & Granite', 'price_role' => 'modifier', 'children' => [174],
            'options' => [
                'رخام مصري' => 'Egyptian Marble', 'رخام مستورد' => 'Imported Marble',
                'جرانيت' => 'Granite', 'كوارتز' => 'Quartz',
                'حجر هاشمي' => 'Hashemi Stone', 'حجر فرعوني' => 'Pharaonic Stone',
                'أسطح مطابخ' => 'Kitchen Worktops', 'درج وأرضيات' => 'Stairs & Flooring',
            ],
        ],

        'أنواع المراتب' => [
            'name_en' => 'Mattress Types', 'price_role' => 'modifier', 'children' => [180],
            'options' => [
                'مرتبة سوست' => 'Spring Mattress', 'مرتبة إسفنج' => 'Foam Mattress',
                'مرتبة طبية' => 'Orthopaedic Mattress', 'مرتبة لاتكس' => 'Latex Mattress',
                'مراتب أطفال' => 'Kids Mattress', 'قواعد سرير' => 'Bed Bases',
                'مخدات' => 'Pillows',
            ],
        ],

        'المستلزمات الطبية' => [
            'name_en' => 'Medical Supplies', 'price_role' => 'modifier', 'children' => [182],
            'options' => [
                'مستهلكات طبية' => 'Disposables', 'أجهزة قياس' => 'Diagnostic Devices',
                'كراسي متحركة' => 'Wheelchairs', 'أسرّة طبية' => 'Hospital Beds',
                'مستلزمات جراحة' => 'Surgical Supplies', 'مستلزمات أسنان' => 'Dental Supplies',
                'مستلزمات معامل' => 'Laboratory Supplies', 'أكسجين وتنفس' => 'Oxygen & Respiratory',
                'إسعافات أولية' => 'First Aid',
            ],
        ],

        'الحدايد والبويات' => [
            'name_en' => 'Hardware & Paints', 'price_role' => 'modifier', 'children' => [207],
            'options' => [
                'بويات ودهانات' => 'Paints', 'ورق حائط' => 'Wallpaper',
                'عدد يدوية' => 'Hand Tools', 'عدد كهربائية' => 'Power Tools',
                'مسامير ومواسير' => 'Fixings & Pipes', 'أقفال ومفصلات' => 'Locks & Hinges',
                'سلالم' => 'Ladders', 'مواد عزل' => 'Insulation Materials',
            ],
        ],

        'المواد الدوائية' => [
            'name_en' => 'Pharmaceutical Materials', 'price_role' => 'modifier', 'children' => [214],
            'options' => [
                'خامات دوائية' => 'Active Ingredients', 'مكملات وفيتامينات' => 'Supplements',
                'مستحضرات عشبية' => 'Herbal Preparations', 'مواد تعقيم' => 'Sterilisers',
                'مواد تغليف دوائي' => 'Pharma Packaging', 'كيماويات معملية' => 'Lab Chemicals',
            ],
        ],

        'الأكياس والمنتجات البلاستيكية' => [
            'name_en' => 'Plastic Bags & Products', 'price_role' => 'modifier', 'children' => [221],
            'options' => [
                'أكياس تسوق' => 'Shopping Bags', 'أكياس قمامة' => 'Refuse Sacks',
                'أكياس فاكيوم' => 'Vacuum Bags', 'أكياس شرنك' => 'Shrink Bags',
                'رول بلاستيك' => 'Plastic Rolls', 'شنط مطبوعة' => 'Printed Bags',
                'فوم وأطباق' => 'Foam Trays',
            ],
        ],

        'الصيني والخزف' => [
            'name_en' => 'China & Porcelain', 'price_role' => 'modifier', 'children' => [228, 145],
            'options' => [
                'أطقم شاي وقهوة' => 'Tea & Coffee Sets', 'أطقم عشاء' => 'Dinner Sets',
                'بورسلين' => 'Porcelain', 'خزف مزخرف' => 'Decorated Ceramics',
                'فخار' => 'Pottery', 'تحف خزفية' => 'Ceramic Ornaments',
                'أكواب ومجات' => 'Mugs & Cups',
            ],
        ],

        'مستلزمات المطاعم' => [
            'name_en' => 'Restaurant Equipment', 'price_role' => 'modifier', 'children' => [247],
            'options' => [
                'معدات مطابخ' => 'Kitchen Equipment', 'ثلاجات عرض' => 'Display Fridges',
                'أفران' => 'Ovens', 'شوايات' => 'Grills',
                'عجانات وخلاطات' => 'Mixers & Dough Machines', 'أدوات تقديم' => 'Serving Ware',
                'عربات خدمة' => 'Service Trolleys', 'مستهلكات تغليف' => 'Takeaway Packaging',
                'زي عاملين' => 'Staff Uniforms',
            ],
        ],

        'أنواع الحديد' => [
            'name_en' => 'Steel Products', 'price_role' => 'modifier', 'children' => [262],
            'options' => [
                'حديد تسليح' => 'Rebar', 'حديد زوايا' => 'Angle Iron',
                'حديد مربعات' => 'Square Sections', 'مواسير حديد' => 'Steel Pipes',
                'صاج' => 'Steel Sheet', 'شينيه' => 'Channel Sections',
                'حديد شدات' => 'Formwork Steel', 'أسلاك ربط' => 'Tie Wire',
            ],
        ],

        'أنواع الإسفنج' => [
            'name_en' => 'Foam Types', 'price_role' => 'modifier', 'children' => [266],
            'options' => [
                'إسفنج مراتب' => 'Mattress Foam', 'إسفنج أثاث' => 'Upholstery Foam',
                'إسفنج ضغط عالي' => 'High-Density Foam', 'إسفنج ميموري' => 'Memory Foam',
                'إسفنج عزل' => 'Insulation Foam', 'إسفنج تغليف' => 'Packaging Foam',
            ],
        ],

        'أصناف لعب الأطفال' => [
            'name_en' => 'Toy Ranges', 'price_role' => 'modifier', 'children' => [280],
            'options' => [
                'ألعاب تعليمية' => 'Educational Toys', 'عرائس ودمى' => 'Dolls & Plush',
                'سيارات ومركبات' => 'Toy Vehicles', 'ألعاب خارجية' => 'Outdoor Toys',
                'بازل وتركيب' => 'Puzzles & Building', 'ألعاب إلكترونية' => 'Electronic Toys',
                'ألعاب رضع' => 'Baby Toys', 'ألعاب خشبية' => 'Wooden Toys',
            ],
        ],

        /*
         * Owner, 2026-08-11: «من المفترض ان هناك انواع اخشاب طبيعى وصناعى».
         * Both halves are named, and the order says which is which: the first
         * five grow, the rest are pressed.
         */
        'أنواع الأخشاب' => [
            'name_en' => 'Timber Types', 'price_role' => 'modifier', 'children' => [301],
            'options' => [
                // طبيعي
                'زان' => 'Beech', 'أرو' => 'Pine',
                'موسكي' => 'Whitewood', 'سويد' => 'Swedish Redwood',
                'خشب معالج' => 'Treated Timber',
                // صناعي
                'MDF' => 'MDF', 'HDF' => 'HDF',
                'كونتر' => 'Plywood', 'أبلاكاش' => 'Blockboard',
                'لاتيه' => 'Laminboard', 'حبيبي (شيبورد)' => 'Chipboard',
                'قشرة خشب' => 'Veneer',
            ],
        ],

        /*
         * Neither timber nor stone, and sold beside both — «بديل الخشب» (WPC),
         * «بديل الرخام» (UV sheet) and «ڤيوتك» panels. A separate list because
         * a row inside «أنواع الأخشاب» would call a PVC sheet a wood, and a
         * marble yard carries these without carrying one plank.
         */
        'بدائل الخشب والرخام' => [
            'name_en' => 'Wood & Marble Alternatives', 'price_role' => 'modifier', 'children' => [301, 174],
            'options' => [
                'بديل خشب WPC' => 'WPC Wood Alternative',
                'بديل رخام UV' => 'UV Marble Alternative',
                'ألواح ڤيوتك' => 'Viotech Panels',
                'ألواح PVC' => 'PVC Panels',
                'ديكينج خارجي' => 'Outdoor Decking',
                'فوم بورد' => 'Foam Board',
                'كلادينج' => 'Cladding Panels',
            ],
        ],

        /*
         * What a packaging PRINTER does, which is not what a packaging
         * supplier stocks — see the note at the top about the declared empty
         * that caught the difference.
         */
        'طباعة العبوات والتغليف' => [
            'name_en' => 'Packaging Printing', 'price_role' => 'modifier', 'children' => [232],
            'options' => [
                'طباعة على الكرتون' => 'Carton Printing',
                'طباعة على الأكياس' => 'Bag Printing',
                'طباعة على اللفائف' => 'Roll & Film Printing',
                'طباعة ستيكرز ولاصقات' => 'Label Printing',
                'طباعة على العلب والأكواب' => 'Cup & Tin Printing',
                'طباعة فليكسو' => 'Flexo Printing',
                'طباعة روتوجرافير' => 'Rotogravure',
                'تصميم عبوات' => 'Packaging Design',
                'كليشيهات وأسطوانات' => 'Plates & Cylinders',
            ],
        ],

        'أنواع الأصواف والخيوط' => [
            'name_en' => 'Wool & Yarn', 'price_role' => 'modifier', 'children' => [303],
            'options' => [
                'صوف طبيعي' => 'Natural Wool', 'صوف صناعي' => 'Synthetic Wool',
                'خيوط قطن' => 'Cotton Yarn', 'خيوط أكريليك' => 'Acrylic Yarn',
                'خيوط تريكو' => 'Knitting Yarn', 'وبر' => 'Fleece',
                'حشو ألياف' => 'Fibre Filling',
            ],
        ],
    ],

    /*
    | ── the seven that could name their trade EVERYWHERE BUT HERE ─────────
    |
    | Found 2026-08-11 by auditing every root at once. «نجف» carries the
    | furniture vocabulary under «شركات»; «أقمشة» carries the fashion one under
    | «المحلات», «معارض» AND «شركات»; «مفروشات» under three roots. Not one of
    | them carried it under «مصانع».
    |
    | **The chandelier FACTORY could not say it makes chandeliers while the
    | chandelier wholesaler next door could** — same child row, same trade, and
    | the factory is the one that makes them. Every one of these links is
    | per-root, written by older seeders that named the roots they cared about,
    | and مصانع was never one of them. No declared empty and no decision row
    | stands against any of the seven, so the absence is an omission.
    |
    | Mirrored rather than restated: the seeder copies the option ids the child
    | ALREADY holds in that group under another root. Three of the seven are
    | narrowed by `child_option_scopes.php` — «نجف» to a single row of the
    | furniture list — and copying the set preserves the narrowing, where
    | naming the group would hand back everything it was cut down from.
    |
    | «نوع التعامل» is deliberately not mirrored for «مفروشات»: it was granted
    | to the SHOWROOM minutes earlier and a furniture factory part-exchanges
    | nothing.
    */
    /*
    | ── «اكسسوار» #8: WITHDRAWN BY THE OWNER, and the record stands ────────
    |
    | On 2026-08-11 he asked for this child («ناخد ابن فى كل مرة»), then for it
    | to be narrowed («ضيقها له»). Both were done: five axes mirrored from the
    | roots it already answered them under, and the twelve-row clothing line cut
    | to three.
    |
    | At 20:17 he withdrew **all seventeen** by hand — the mirrored payment,
    | returns, scope, condition and audience rows, AND the three fashion rows
    | the narrowing had kept, «اكسسوارات» included
    | (`category_child_option_decisions`, source `admin`).
    |
    | That is a complete answer, not a partial one: the accessories factory
    | answers «أنواع الإكسسوارات» and the universal axes it already had, and
    | nothing else. The entries are removed rather than left here, because the
    | seeder consults the withdrawal record and would refuse them every run —
    | a file that keeps proposing what the owner has refused is a file that
    | reports a lie once a run.
    |
    | ⚠ Note for the next pass: the withdrawal record is keyed by CHILD, not by
    | root. Withdrawing «شنط وحقائب» from #8 takes it off the clothing shop as
    | well as the factory. `prune_links` in the seeder is what expresses a
    | per-ROOT narrowing, and it is still there for whoever needs it next.
    */

    'mirror_links' => [
        /*
         * «أجهزة رياضية» #24 already answers everything a factory is asked —
         * «أنواع الأجهزة الرياضية» (15 rows), نظام التصنيع، الحد الأدنى للطلب،
         * حالة المنتج and the delivery/payment/scope axes. The ONE group it
         * holds as a shop, a showroom and a wholesaler but not as a factory is
         * «الاستبدال والإرجاع» — a treadmill goes back to whoever built it.
         *
         * That group is also one of the six the owner withdrew from «اكسسوار»
         * #8 an hour before this was written. Asked whether that was about
         * accessories or about factories in general, he answered: **about
         * accessories alone**. So the axis stands here, and the uneven spread
         * across the root (16 of 44 answer «الدفع والسداد», 9 «نطاق التعامل»)
         * is sediment from older seeders to be evened out child by child — in
         * the ADD direction — not a rule that factories answer less.
         */
        /*
         * The eight below are the repair half of the 2026-08-11 22:45 bulk
         * save (see FactoryBulkSaveRevertSeeder). It withdrew each child's own
         * trade list UNDER مصانع and handed it the doors list instead. Every
         * one of them still says the same words as a shop, a showroom or a
         * wholesaler — the factory scope alone was emptied — so the mirror is
         * exactly the right tool: it copies the ids the child ALREADY holds and
         * so cannot widen anything the scope file had narrowed.
         */
        8 => ['أنواع الإكسسوارات'],                          // اكسسوار
        44 => ['ماركات السيارات'],                          // قطع غيار سيارات
        60 => ['موضة وعناية شخصية'],                       // ملابس جاهزة
        88 => ['أنواع الأجهزة الكهربائية'],               // أجهزة كهربائية
        116 => ['أثاث وتشطيب منزلي', 'طراز الأثاث'],      // آثاث
        168 => ['موضة وعناية شخصية'],                      // جلود وشنط وأحذية
        204 => ['تعبئة وتغليف ومستلزمات'],                 // مواد تعبئة وتغليف

        24 => ['الاستبدال والإرجاع', 'أنواع الأجهزة الرياضية'], // أجهزة رياضية

        /*
         * «طوب» #34 stands under مصانع and شركات and answers three more axes
         * as the WHOLESALER than as the FACTORY. Two of the three are the
         * factory's too: a pallet delivered as the wrong type goes back
         * (الاستبدال والإرجاع), and a kiln sells retail, wholesale and for
         * export (نطاق التعامل).
         *
         * «حالة المنتج» is deliberately NOT mirrored. Its two rows are
         * جديد · مستعمل, and a brick factory only ever fires new — used brick
         * is a demolition trade, not a kiln's. A modifier with one possible
         * answer is noise on the pricing screen, not an axis.
         */
        34 => ['الاستبدال والإرجاع', 'نطاق التعامل'],      // طوب
        56 => ['أثاث وتشطيب منزلي', 'طراز الأثاث'],       // نجف
        83 => ['أقسام المنزل والعناية'],                    // منظفات
        95 => ['موضة وعناية شخصية', 'الجمهور المستهدف'],   // أقمشة
        101 => ['أقسام الطازج واللحوم'],                    // أسماك
        115 => ['أثاث وتشطيب منزلي', 'طراز الأثاث'],       // مفروشات
        158 => ['بنود المنيو', 'مواصفات المنتج الغذائي'],  // عصائر
        210 => ['بنود المنيو', 'بنود المخبوزات والحلويات'], // حلويات
    ],

    'links' => [
        /*
         * The fire half of «أنظمة الأمن والسلامة», built hours earlier for
         * «أمن وسلامة» #254. A fire-equipment factory installs no intercom and
         * no attendance terminal, so the manpower-adjacent rows stay behind —
         * the same narrowing «إدارة صفحات» got from the advertising list.
         */
        250 => [
            'أنظمة الأمن والسلامة' => [
                'أنظمة إنذار الحريق',
                'أنظمة إطفاء الحريق',
                'طفايات ومعدات إطفاء',
                'كاشفات دخان وغاز',
                'عقود صيانة وفحص دوري',
                'استشارات وتراخيص السلامة',
            ],
        ],
    ],
];
