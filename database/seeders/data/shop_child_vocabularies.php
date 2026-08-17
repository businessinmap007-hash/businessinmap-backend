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
|                          ⚠ SHARING A LIST IS NOT BEING ONE TRADE. The two
|                          came up as a merge candidate on 2026-08-12 — same
|                          root, identical option set — and the owner refused:
|                          «لا تدمج لوازم ستائر وستائر وديكور فهم بندين
|                          مختلفين». #9 sells the FITTINGS (rails, rings,
|                          brackets); #76 sells and hangs the curtain. Do not
|                          propose it again.
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

        /*
        |----------------------------------------------------------------------
        | 2026-08-17 — the counter that is also a cash machine
        |----------------------------------------------------------------------
        | Owner: «إضافة سحب وإيداع المحفظة الالكترونية وانيستا باى لمحلات
        | الموبايلات والسوبر ماركت والمنيا ماركت».
        |
        | These three are where Egypt actually banks. The mobile shop and the
        | grocery on the corner are the agent counter for فودافون كاش and its
        | rivals: you hand cash over and it lands in the wallet, or you show the
        | code and take cash out. InstaPay is the same errand a layer up. It is
        | the reason a customer walks into THAT shop rather than the one beside
        | it, and none of the three had a word for it.
        |
        | ── Why a new group and not «خدمات الصرافة والتحويل» ──────────────────
        |
        | That group already holds «محافظ إلكترونية» and would have been the
        | cheap answer. It is the wrong home: it is a `line` — the priced list
        | of a صرافة — and its heading would read «خدمات الصرافة والتحويل» on a
        | supermarket's screen. A grocer does not run a bureau de change; he
        | does two errands beside the till.
        |
        | ── Why descriptive ──────────────────────────────────────────────────
        |
        | The price test decides it. A `line` is the thing bought, and these
        | shops' priced lists are groceries and handsets — a withdrawal is not a
        | row beside the rice. Nor is there a merchant-set rate for a modifier
        | to move: the commission on a wallet cash-out is the operator's, not
        | the shop's. It is a FACILITY, and its whole value is the search —
        | «محل بيسحب فودافون كاش قريب مني» is a question the platform could not
        | answer.
        |
        | Two rows, the two he named. «شحن رصيد» is not restated: #186 already
        | prices it as «شرائح وشحن رصيد» in its own line group, and «دفع فواتير»
        | stays with the صرافة list until he says otherwise.
        |
        | «هايبر ماركت» #149 (6 merchants) is deliberately NOT here. It is the
        | same errand and arguably the same trade, and he named three children —
        | reported rather than assumed.
        */
        'الخدمات المالية بالمحل' => [
            'name_en' => 'In-Store Financial Services',
            'price_role' => 'descriptive',
            'children' => [186, 272, 185],   // موبيلات و اكسسوار، سوبر ماركت، مني ماركت
            'options' => [
                'سحب وإيداع محفظة إلكترونية' => 'E-Wallet Cash In & Out',
                'انستا باي' => 'InstaPay',
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Five shops that were answering somebody else's question, 2026-08-16
        |----------------------------------------------------------------------
        | Found by reviewing the root the way «زراعية وحيوانية» was reviewed.
        | Each of these is `line`: what the shop sells IS what it prices, and
        | the catalog behind it is too thin to be the price list — a glasses
        | shop has seven products in the entire platform catalog, a locksmith
        | six. The rule this file opened with («these are modifier groups; the
        | priced rows are catalog products») is the one the owner overturned on
        | the same day — see the note in `option_price_roles.php`.
        */

        /*
        | «موبيلات و اكسسوار» #186 answered «أنواع الإكسسوارات» — the FASHION
        | accessories list it shares with «اكسسوار» #8: شنط، أحزمة، نظارات
        | شمس. A phone shop was being asked whether it sells handbags, and had
        | no word for a phone at all.
        |
        | Two axes, the same shape «قطع غيار سيارات» uses: the DEVICE is the
        | line and the BRAND qualifies it, because a Samsung screen and a
        | Xiaomi screen are the same job at two prices. Models are deliberately
        | not enumerated — «آيفون ١٥ برو ماكس» is a catalog product, and a list
        | that must be edited every autumn is a list nobody maintains.
        */
        'أجهزة الموبايل وملحقاتها' => [
            'name_en' => 'Mobile Devices & Accessories', 'price_role' => 'line', 'children' => [186],
            'options' => [
                'موبايل جديد' => 'New Handset',
                'موبايل مستعمل' => 'Used Handset',
                'تابلت' => 'Tablet',
                'ساعة ذكية' => 'Smart Watch',
                'سماعات' => 'Headphones & Earbuds',
                'شواحن وكابلات' => 'Chargers & Cables',
                'باور بانك' => 'Power Banks',
                'جرابات وكفرات' => 'Cases & Covers',
                'اسكرين وحماية' => 'Screen Protectors',
                'كروت ذاكرة' => 'Memory Cards',
                'إكسسوار موبايل للسيارة' => 'In-car Mobile Accessories',
                'صيانة وبرمجة' => 'Repair & Software',
                'شرائح وشحن رصيد' => 'SIM Cards & Top-up',
            ],
        ],

        'ماركات الموبيلات' => [
            'name_en' => 'Mobile Brands', 'price_role' => 'modifier', 'children' => [186],
            'options' => [
                'سامسونج' => 'Samsung',
                'آيفون' => 'iPhone',
                'شاومي' => 'Xiaomi',
                'ريدمي' => 'Redmi',
                'أوبو' => 'Oppo',
                'ريلمي' => 'Realme',
                'فيفو' => 'Vivo',
                'إنفينكس' => 'Infinix',
                'تكنو' => 'Tecno',
                'هواوي' => 'Huawei',
                'هونر' => 'Honor',
                'نوكيا' => 'Nokia',
                'ون بلس' => 'OnePlus',
                'موتورولا' => 'Motorola',
            ],
        ],

        /*
        | «مفاتيح» #159 — «فى المحلات واونلاين مفاتيح هو المحل الذي يقوم بتصليح
        | الكوالين او نسخ المفاتيح» (owner, 2026-08-16).
        |
        | It answered «المفاتيح والتوزيع الكهربائي» — switches, sockets,
        | distribution boards, circuit breakers. That is the SWITCHGEAR trade,
        | and it is the right list for #159 under «شركات» and «مصانع» where the
        | same name means an electrical wholesaler. Under «المحلات» it means the
        | man on the corner who cuts a key and changes a lock, and he had no
        | word for anything he does.
        |
        | Root-scoped for exactly that reason: one child row, two trades, and
        | `root_links` is what says so. The switchgear list is untouched.
        |
        | Mostly a SERVICE list, which is correct — this shop sells labour with
        | a part in it, and «نسخ مفتاح» is priced as one thing.
        */
        'خدمات المفاتيح والأقفال' => [
            'name_en' => 'Keys & Locksmithing', 'price_role' => 'line',
            'scope' => 'root', 'children' => [159],
            'options' => [
                'نسخ مفاتيح' => 'Key Cutting',
                'مفاتيح سيارات وريموت' => 'Car Keys & Remotes',
                'برمجة مفتاح سيارة' => 'Car Key Programming',
                'تصليح كوالين' => 'Lock Repair',
                'تركيب كوالين' => 'Lock Fitting',
                'كالون باب خشب' => 'Wooden Door Locks',
                'كالون باب حديد' => 'Steel Door Locks',
                'كوالين شبابيك ودرف' => 'Window & Sash Locks',
                'كالون خزنة' => 'Safe Locks',
                'أقفال إلكترونية وبصمة' => 'Electronic & Fingerprint Locks',
                'فتح أبواب وسيارات' => 'Door & Car Opening',
                'ضبة وسلندر' => 'Cylinders & Latches',
            ],
        ],

        /*
        | «ستائر و ديكور» #76 — «المفروض به انواع الستائر وليس لوازم الستائر»
        | (owner, 2026-08-16).
        |
        | It was borrowing «لوازم الستائر» from «لوازم ستائر» #9 — rails, rings,
        | brackets, cords. The two were ruled separate trades on 2026-08-12
        | («لا تدمج لوازم ستائر وستائر وديكور فهم بندين مختلفين») and this is
        | the other half of that ruling finally arriving: #9 sells the FITTINGS,
        | #76 sells and hangs the CURTAIN. Sharing the fittings list left #76
        | with no word for a curtain at all.
        |
        | The borrow is withdrawn in `child_option_scopes.php`; #9 keeps its own
        | list untouched.
        */
        'أنواع الستائر والديكور' => [
            'name_en' => 'Curtains & Decor', 'price_role' => 'line', 'children' => [76],
            'options' => [
                'ستائر بلاك آوت' => 'Blackout Curtains',
                'ستائر رول' => 'Roller Blinds',
                'ستائر زيبرا' => 'Zebra Blinds',
                'ستائر عمودية' => 'Vertical Blinds',
                'ستائر خشبية' => 'Wooden Blinds',
                'ستائر معدنية / شيش حصيرة' => 'Metal & Slat Blinds',
                'ستائر رومانية' => 'Roman Blinds',
                'ستائر شيفون وأورجانزا' => 'Sheer Curtains',
                'ستائر مخمل' => 'Velvet Curtains',
                'ستائر أطفال' => 'Kids Curtains',
                'ستائر حمام ومطبخ' => 'Bath & Kitchen Curtains',
                'ستائر كهربائية بريموت' => 'Motorised Curtains',
                'ورق حائط' => 'Wallpaper',
                'ديكورات جبس' => 'Gypsum Decor',
                'تفصيل وتركيب ستائر' => 'Made-to-measure & Fitting',
            ],
        ],

        /*
        | «عصائر» #158 — «يحتاج اضافات مثلا عصير قصب وتمر وسوبيا وسلطة فواكه
        | والعصائر الفريش» (owner, 2026-08-16).
        |
        | Its whole line was «مشروبات ساخنة» and «مشروبات باردة» — two menu
        | bands, which is a HEADING and not a drink. The owner ruled this child
        | a kitchen on 2026-08-10 («عصائر مطبخ»), and a kitchen is priced by
        | what it pours.
        */
        'أصناف العصائر والمشروبات' => [
            'name_en' => 'Juices & Drinks', 'price_role' => 'line', 'children' => [158],
            'options' => [
                'عصير قصب' => 'Sugarcane Juice',
                'عصير تمر' => 'Date Juice',
                'سوبيا' => 'Sobia',
                'سلطة فواكه' => 'Fruit Salad',
                'عصائر فريش' => 'Fresh Juices',
                'عصير مانجو' => 'Mango Juice',
                'عصير برتقال' => 'Orange Juice',
                'عصير فراولة' => 'Strawberry Juice',
                'عصير جوافة' => 'Guava Juice',
                'عصير ليمون بالنعناع' => 'Lemon & Mint',
                'عصير رمان' => 'Pomegranate Juice',
                'تمر هندي وخروب' => 'Tamarind & Carob',
                'عرقسوس' => 'Liquorice',
                'كركديه' => 'Hibiscus',
                'سحلب' => 'Sahlab',
                'ميلك شيك' => 'Milkshake',
                'سموذي' => 'Smoothie',
                'كوكتيل فواكه' => 'Fruit Cocktail',
                'آيس كوفي ومشروبات مثلجة' => 'Iced Coffee & Frappé',
            ],
        ],

        /*
        | «حلويات» #210 — «بنود حلويات تحتاج اضافات مثل تورته وجاتوه واكلير وما
        | الى ذلك» (owner, 2026-08-16).
        |
        | It answered the shared bakery counter — مخبوزات، فطائر، وافل، آيس كريم،
        | حلويات وشوكولاتة — five headings covering both a bakery and a sweet
        | shop. What it could not name was a single sweet: no cake, no gateau,
        | no éclair, and none of the Egyptian counter either.
        |
        | Its own list, so the shared counter can stay exactly as the bakeries
        | and markets use it.
        */
        'أصناف الحلويات والجاتوه' => [
            'name_en' => 'Sweets & Gateaux', 'price_role' => 'line', 'children' => [210],
            'options' => [
                'تورتة' => 'Cakes',
                'جاتوه' => 'Gateaux',
                'إكلير' => 'Éclairs',
                'تشيز كيك' => 'Cheesecake',
                'كب كيك' => 'Cupcakes',
                'بيتي فور' => 'Petit Fours',
                'بسكويت وكحك' => 'Biscuits & Kahk',
                'بسبوسة وهريسة' => 'Basbousa',
                'كنافة' => 'Kunafa',
                'بقلاوة' => 'Baklava',
                'زلابية ولقمة القاضي' => 'Lokmet El-Qadi',
                'أم علي' => 'Om Ali',
                'مهلبية وأرز بلبن' => 'Mahalabia & Rice Pudding',
                'كريم كراميل' => 'Crème Caramel',
                'حلاوة وطحينة' => 'Halva & Tahini Sweets',
                'شوكولاتة وبونبون' => 'Chocolate & Bonbons',
                'حلويات شرقية' => 'Oriental Sweets',
                'حلويات دايت وخالية من السكر' => 'Diet & Sugar-free',
            ],
        ],

        'أصناف المجوهرات' => [
            'name_en' => 'Jewellery Ranges', 'price_role' => 'line', 'children' => [127, 257],
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
            'name_en' => 'Eyewear Ranges', 'price_role' => 'line', 'children' => [125],
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
            'name_en' => 'Fragrance Ranges', 'price_role' => 'line', 'children' => [213],
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
            'name_en' => 'Supplement Ranges', 'price_role' => 'line', 'children' => [274],
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
            'name_en' => 'Bookshop Sections', 'price_role' => 'line', 'children' => [32],
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
            'name_en' => 'Fishing & Hunting Gear', 'price_role' => 'line', 'children' => [148],
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
            'name_en' => 'Gaming Hardware', 'price_role' => 'line', 'children' => [226],
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
            'name_en' => 'Tobacco Ranges', 'price_role' => 'line', 'children' => [260],
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
            'name_en' => 'Plants & Garden Supplies', 'price_role' => 'line', 'children' => [79],
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
        /*
        | ── four shops that were answering with a neighbour's list ─────────
        |
        | Found 2026-08-12 by the merge audit, and none of them was a merge:
        | each read as a twin of the shop next door only because it had
        | borrowed that shop's vocabulary for want of one.
        |
        |   #87  أدوات كهربائية    «أنواع الأجهزة الكهربائية» — it sells a drill,
        |                          not a fridge. name_en had said «Electric
        |                          Tools» all along.
        |   #264 قطع غيار أجهزة    the same list, which for IT is right — «which
        |                          machine» — but it could never say WHICH PART.
        |                          Same two-axis shape as «قطع غيار سيارات».
        |   #42  زيت سيارات        «ماركات السيارات» and nothing else: it could
        |                          say which cars and never which oil.
        |   #249 جنوط وكاوتش       likewise — no size, no season, no rim.
        |
        | All four `modifier`: they carry retail, so the priced rows are catalog
        | products and these say what is on the shelf.
        */
        'العدد والأدوات الكهربائية' => [
            'name_en' => 'Power & Hand Tools Range', 'price_role' => 'line', 'children' => [87],
            'options' => [
                'شنيور ومثقاب' => 'Drills',
                'صاروخ وجلاخة' => 'Angle Grinders',
                'مناشير كهربائية' => 'Power Saws',
                'مفكات كهربائية' => 'Power Screwdrivers',
                'هيلتي ومعدات هدم' => 'Demolition Hammers',
                'كمبروسر هواء' => 'Air Compressors',
                'ماكينات لحام' => 'Welding Machines',
                'مولدات كهرباء' => 'Generators',
                'مضخات مياه' => 'Water Pumps',
                'موازين ليزر وقياس' => 'Laser & Measuring Tools',
                'عدد يدوية' => 'Hand Tools',
                'لقم وشفرات وإكسسوارات' => 'Bits, Blades & Accessories',
            ],
        ],

        'قطع غيار الأجهزة المنزلية' => [
            'name_en' => 'Appliance Part Types', 'price_role' => 'line', 'children' => [264],
            'options' => [
                'كمبروسر تبريد' => 'Cooling Compressors',
                'مواتير ومراوح' => 'Motors & Fans',
                'بوردات إلكترونية' => 'Control Boards',
                'ثرموستات وحساسات' => 'Thermostats & Sensors',
                'عناصر تسخين' => 'Heating Elements',
                'مضخات تصريف' => 'Drain Pumps',
                'سيور وبكر' => 'Belts & Pulleys',
                'جلد أبواب وحشوات' => 'Door Seals & Gaskets',
                'فلاتر وخراطيم' => 'Filters & Hoses',
                'ريموتات ولوحات مفاتيح' => 'Remotes & Keypads',
                'فريون وغازات تبريد' => 'Refrigerant Gases',
            ],
        ],

        'أنواع الزيوت والسوائل' => [
            'name_en' => 'Oils & Fluids', 'price_role' => 'line', 'children' => [42],
            'options' => [
                'زيت محرك' => 'Engine Oil',
                'زيت فتيس' => 'Gearbox Oil',
                'زيت فرامل' => 'Brake Fluid',
                'زيت باور' => 'Power Steering Fluid',
                'مياه تبريد (كولنت)' => 'Coolant',
                'شحوم' => 'Greases',
                'إضافات ومحسنات' => 'Additives',
                'فلاتر زيت' => 'Oil Filters',
                'فلاتر هواء' => 'Air Filters',
                'فلاتر بنزين' => 'Fuel Filters',
                'فلاتر تكييف' => 'Cabin Filters',
            ],
        ],

        'الإطارات والجنوط' => [
            'name_en' => 'Tyres & Rims', 'price_role' => 'line', 'children' => [249],
            'options' => [
                'إطارات ملاكي' => 'Passenger Tyres',
                'إطارات دفع رباعي' => '4x4 Tyres',
                'إطارات نقل وحافلات' => 'Truck & Bus Tyres',
                'إطارات كل الفصول' => 'All-season Tyres',
                'إطارات شتوية' => 'Winter Tyres',
                'إطارات مجددة' => 'Retreaded Tyres',
                'جنوط ألومنيوم' => 'Alloy Rims',
                'جنوط حديد' => 'Steel Rims',
                'صواميل وأغطية جنوط' => 'Nuts & Wheel Covers',
                'بلوف ووزنات ترصيص' => 'Valves & Balance Weights',
                'خدمة ترصيص وتوازن' => 'Balancing Service',
                'ضبط زوايا (ترصيص)' => 'Wheel Alignment',
            ],
        ],

        'درجة التحميص والطحن' => [
            'name_en' => 'Roast & Grind', 'price_role' => 'modifier', 'children' => [63],
            'options' => [
                'تحميص فاتح' => 'Light Roast',
                'تحميص وسط' => 'Medium Roast',
                'تحميص غامق' => 'Dark Roast',
                'حبوب كاملة' => 'Whole Bean',
                'مطحون' => 'Ground',
                /*
                 * Owner, 2026-08-12. The two rows an Egyptian coffee counter is
                 * actually asked for, and neither is a roast DEGREE: «محوج» is
                 * ground with cardamom and «سبيشيال» is the house blend. They
                 * belong on this axis all the same — they are the other two
                 * answers to «which one do you want», and each is a different
                 * price for the same bean, which is what the group is for.
                 */
                'محوج' => 'Cardamom Blend',
                'سبيشيال' => 'Special Blend',
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
            'name_en' => 'Wooden Crafts & Decor', 'price_role' => 'line', 'children' => [302],
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

        // #76's borrow of «لوازم الستائر» ended 2026-08-16 — it has «أنواع
        // الستائر والديكور» now, and the fittings belong to #9. Removed here
        // AND declared empty in `child_option_scopes.php`: this line only stops
        // the seeder re-granting, the scope file is what takes it back off.

        /*
         * «نجف و تحف» #57 is TWO trades in one name and only ever said one of
         * them — and not even that: its single row was «تابلوه». Its lighting
         * half is «أنواع النجف والإضاءة», written 2026-08-12; the antiques half
         * is borrowed whole from «أنتيكات وتحف» #21, which is the same stock.
         * Its retail branch already named both shelves — chandeliers_lighting
         * AND antiques_artifacts — so the wiring was ahead of the words.
         */
        57 => ['الأنتيكات والتحف' => 'all'],

        222 => ['الأكياس والمنتجات البلاستيكية' => 'all'],
    ],
];
