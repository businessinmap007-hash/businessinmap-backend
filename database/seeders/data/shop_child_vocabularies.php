<?php

/*
|--------------------------------------------------------------------------
| «المحلات أو أونلاين» — the fourteen shops that could not name their stock
|--------------------------------------------------------------------------
| Owner, 2026-08-11: «اكمل».
|
| Sixty-four children, and fifty carried a trade vocabulary already — the
| retail narrowing and the fashion, grocery and furniture remodels had reached
| them. Fourteen had nothing but the five universal axes: a نظارات shop could
| not say whether it sells prescription lenses or sunglasses, a ذهب shop could
| not say it makes wedding bands.
|
| Every one is retail + delivery, so the GOODS rule: these are `modifier`
| groups saying what the shop STOCKS. The priced rows are catalog products.
|
| ── Borrowed and extended, not cloned ─────────────────────────────────────
|
|   مستلزمات كافيهات #37 ← «مستلزمات المقاهي», written hours earlier for
|                          «مستلزمات قهاوى» #66 under شركات. The wholesaler and
|                          the shop stock the same machines.
|   ستائر و ديكور #76    ← «لوازم الستائر», written for «لوازم ستائر» #9.
|   بلاستيك #222         ← «الأكياس والمنتجات البلاستيكية», written for «اكياس
|                          بلاستيك» #221 — EXTENDED with the household rows
|                          rather than given a second list. The group name
|                          already said «والمنتجات»; #221 keeps its bags and
|                          #222 takes the whole thing.
|
| ── One list for two metals ───────────────────────────────────────────────
|
| «ذهب» #127 and «فضة» #257 share «أصناف المجوهرات». What distinguishes them is
| the METAL, and the metal is the child's own name — so the list is the FORMS,
| and both take all of it. Two lists here would have been the same list twice.
*/

return [

    'root' => 'shops-online',

    'name_en_suffix' => 'Shop',

    'groups' => [

        'أصناف المجوهرات' => [
            'name_en' => 'Jewellery Ranges', 'price_role' => 'modifier', 'children' => [127, 257],
            'options' => [
                'خواتم' => 'Rings',
                'دبل زفاف' => 'Wedding Bands',
                'سلاسل' => 'Necklaces',
                'أساور' => 'Bracelets',
                'حلق' => 'Earrings',
                'أطقم' => 'Jewellery Sets',
                'سبائك وجنيهات' => 'Bullion & Coins',
                'مشغولات مرصعة' => 'Gemstone Pieces',
                'تصليح وتلميع' => 'Repair & Polishing',
            ],
        ],

        'أنواع النظارات' => [
            'name_en' => 'Eyewear Ranges', 'price_role' => 'modifier', 'children' => [125],
            'options' => [
                'نظارات طبية' => 'Prescription Glasses',
                'نظارات شمس' => 'Sunglasses',
                'إطارات' => 'Frames',
                'عدسات طبية' => 'Prescription Lenses',
                'عدسات لاصقة' => 'Contact Lenses',
                'نظارات أطفال' => 'Kids Eyewear',
                'نظارات رياضية' => 'Sports Eyewear',
                'إكسسوار وصيانة' => 'Accessories & Servicing',
            ],
        ],

        'أنواع العطور' => [
            'name_en' => 'Fragrance Ranges', 'price_role' => 'modifier', 'children' => [213],
            'options' => [
                'عطور شرقية' => 'Oriental Perfumes',
                'عطور فرنسية' => 'French Perfumes',
                'مسك وعود' => 'Musk & Oud',
                'بخور ومباخر' => 'Incense & Burners',
                'زيوت عطرية' => 'Fragrance Oils',
                'معطرات جو ومنزل' => 'Home Fragrance',
                'تركيب عطور' => 'Bespoke Blending',
                'عبوات وهدايا' => 'Gift Sets',
            ],
        ],

        'أصناف المكملات' => [
            'name_en' => 'Supplement Ranges', 'price_role' => 'modifier', 'children' => [274],
            'options' => [
                'بروتين' => 'Protein',
                'كرياتين' => 'Creatine',
                'أحماض أمينية' => 'Amino Acids',
                'فيتامينات ومعادن' => 'Vitamins & Minerals',
                'حرق دهون' => 'Fat Burners',
                'زيادة وزن' => 'Weight Gainers',
                'مكملات مفاصل' => 'Joint Support',
                'أعشاب طبيعية' => 'Herbal Supplements',
            ],
        ],

        'أقسام المكتبة' => [
            'name_en' => 'Bookshop Sections', 'price_role' => 'modifier', 'children' => [32],
            'options' => [
                'كتب دراسية' => 'Textbooks',
                'روايات وأدب' => 'Fiction & Literature',
                'كتب دينية' => 'Religious Books',
                'كتب أطفال' => 'Children\'s Books',
                'كتب علمية' => 'Science & Technical',
                'كتب مستعملة' => 'Second-hand Books',
                'مجلات ودوريات' => 'Magazines',
                'قرطاسية' => 'Stationery',
            ],
        ],

        'مستلزمات الصيد' => [
            'name_en' => 'Fishing & Hunting Gear', 'price_role' => 'modifier', 'children' => [148],
            'options' => [
                'سنارات وبكر' => 'Rods & Reels',
                'خيوط وطعوم' => 'Line & Bait',
                'شباك صيد' => 'Nets',
                'قوارب ومجاديف' => 'Boats & Paddles',
                'ملابس ومعدات صيد' => 'Clothing & Kit',
                'صيد بري' => 'Game Hunting Gear',
                'أسلحة هوائية' => 'Air Guns',
                'إكسسوار صيد' => 'Accessories',
            ],
        ],

        'أجهزة الألعاب' => [
            'name_en' => 'Gaming Hardware', 'price_role' => 'modifier', 'children' => [226],
            'options' => [
                'أجهزة بلايستيشن' => 'PlayStation Consoles',
                'أجهزة إكس بوكس' => 'Xbox Consoles',
                'أجهزة محمولة' => 'Handheld Consoles',
                'دراعات وأذرع' => 'Controllers',
                'أقراص وألعاب' => 'Games & Discs',
                'إكسسوار جيمنج' => 'Gaming Accessories',
                'كروت اشتراك' => 'Subscription Cards',
                'صيانة أجهزة الألعاب' => 'Console Repair',
            ],
        ],

        'مشتقات التدخين' => [
            'name_en' => 'Tobacco Ranges', 'price_role' => 'modifier', 'children' => [260],
            'options' => [
                'سجائر' => 'Cigarettes',
                'معسل وتبغ' => 'Shisha Tobacco',
                'شيشة ومستلزمات' => 'Shisha & Parts',
                'فحم' => 'Charcoal',
                'ولاعات' => 'Lighters',
                'سجائر إلكترونية' => 'E-Cigarettes',
                'فيب وسوائل' => 'Vape & Liquids',
                'إكسسوار تدخين' => 'Smoking Accessories',
            ],
        ],

        'النباتات ومستلزماتها' => [
            'name_en' => 'Plants & Garden Supplies', 'price_role' => 'modifier', 'children' => [79],
            'options' => [
                'نباتات داخلية' => 'Indoor Plants',
                'نباتات خارجية' => 'Outdoor Plants',
                'شتلات وأشجار' => 'Saplings & Trees',
                'زهور طبيعية' => 'Fresh Flowers',
                'زهور صناعية' => 'Artificial Flowers',
                'أصص وأحواض' => 'Pots & Planters',
                'تربة وأسمدة' => 'Soil & Fertiliser',
                'أدوات زراعة' => 'Garden Tools',
                'تنسيق حدائق' => 'Landscaping',
            ],
        ],

        /*
         * ── two modifiers that are real, and one absence that is not ──────
         *
         * «بن» #63 sells one bean at two prices depending on the roast, and
         * «دواجن» #229 one bird at two depending on whether you take it live.
         * Both are a second answer on one line, which is the whole test.
         *
         * «سوبر ماركت» #272 (16 merchants) is left with none on purpose: it
         * carries FIVE line groups and prices by product, which is the catalog.
         */
        'درجة التحميص والطحن' => [
            'name_en' => 'Roast & Grind', 'price_role' => 'modifier', 'children' => [63],
            'options' => [
                'تحميص فاتح' => 'Light Roast',
                'تحميص وسط' => 'Medium Roast',
                'تحميص غامق' => 'Dark Roast',
                'حبوب كاملة' => 'Whole Bean',
                'مطحون' => 'Ground',
            ],
        ],

        'حالة الدواجن' => [
            'name_en' => 'Poultry Preparation', 'price_role' => 'modifier', 'children' => [229],
            'options' => [
                'حي' => 'Live',
                'مذبوح' => 'Slaughtered',
                'مذبوح ومنظف' => 'Cleaned',
                'مقطّع' => 'Portioned',
                'مجمد' => 'Frozen',
            ],
        ],

        'المصنوعات الخشبية والديكور' => [
            'name_en' => 'Wooden Crafts & Decor', 'price_role' => 'modifier', 'children' => [302],
            'options' => [
                'تحف خشبية' => 'Wooden Ornaments',
                'إطارات وبراويز' => 'Frames',
                'أرفف ووحدات' => 'Shelves & Units',
                'ألعاب خشبية' => 'Wooden Toys',
                'هدايا وتذكارات' => 'Gifts & Souvenirs',
                'نقش وحفر على الخشب' => 'Wood Engraving',
                'مستلزمات ديكور' => 'Decor Pieces',
                'شموع ومباخر' => 'Candles & Burners',
            ],
        ],
    ],

    /*
    | The household half of plastics. «الأكياس والمنتجات البلاستيكية» was
    | written for the BAG factory #221, and its name already promised
    | «والمنتجات» — a plastics SHOP sells the tanks, the crates and the chairs
    | too. Extended rather than cloned; #221 keeps only its bag rows.
    */
    'extend' => [
        'الأكياس والمنتجات البلاستيكية' => [
            'أدوات منزلية بلاستيك' => 'Plastic Housewares',
            'صناديق وحافظات' => 'Crates & Storage Boxes',
            'كراسي وطاولات بلاستيك' => 'Plastic Furniture',
            'خزانات مياه' => 'Water Tanks',
            'مواسير بلاستيك' => 'Plastic Piping',
            'خامات بلاستيك' => 'Raw Plastics',
        ],
    ],

    /*
    | «اكسسوار» #8 carries «التسليم والاستلام» and «الاستبدال والإرجاع» under
    | شركات، مصانع and ملابس — and nothing descriptive under THIS root, which
    | is the one a customer browses. The same per-root omission the chandelier
    | factory had, one root over.
    */
    'mirror_links' => [
        8 => ['التسليم والاستلام', 'الاستبدال والإرجاع', 'الدفع والسداد'],
    ],

    'links' => [
        // The wholesaler and the shop stock the same machines.
        37 => ['مستلزمات المقاهي' => 'all'],

        76 => ['لوازم الستائر' => 'all'],

        222 => ['الأكياس والمنتجات البلاستيكية' => 'all'],
    ],
];
