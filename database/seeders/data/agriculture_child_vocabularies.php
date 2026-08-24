<?php

/*
|--------------------------------------------------------------------------
| «زراعية وحيوانية» — bulk goods priced by the unit they are sold in
|--------------------------------------------------------------------------
| Four of the six children reported with no modifier are bulk trades: تقاوي،
| أسمدة، أعلاف، حبوب وغلال. The same fertiliser is one price by the sack and
| another by the tonne, and that is a genuine second answer rather than an
| invented one.
|
| «الحد الأدنى للطلب» under مصانع looks like this and is not: it says how
| little you may buy, `descriptive`, and never changes the rate.
|
| «مزارع سمكية» #102 and «أرانب» #236 are left out. They sell live stock by the
| head or by weight and the catalog product already carries that; neither has a
| second rate for one line.
*/

return [

    'root' => 'agriculture-and-animals',

    'name_en_suffix' => 'Agri',

    /*
    | ── the farm cluster, 2026-08-12 ────────────────────────────────────────
    |
    | Seven children shared «مستلزمات المزارع» and nothing else, and that group
    | is three rows: «مستلزمات زراعية»، «ماشية وطيور»، «معدات ومستلزمات». They
    | are not a vocabulary — they restate the child's own name. «معدات مزارع
    | دواجن» answering «ماشية وطيور» + «معدات ومستلزمات» tells a customer
    | nothing at all about what is on the shelf.
    |
    | Three lists, because there are three trades hiding in the seven:
    |
    |   #12                     farm MACHINERY — a tractor, a pump, a sprayer
    |   #171، #230، #235        livestock HOUSING & equipment — cages, feeders
    |   #170، #236، #102        the ANIMALS themselves
    |
    | The owner merged them on the same day: #230 and #235 folded into #171, and
    | #236 into #170, because once the animal is a ROW the three children were
    | one. Only the keepers are named below — a `children` list that still names
    | a folded child hands the vocabulary back to a row no root can reach, on
    | every single run.
    |
    | All three are `line`. These children carry `menu` and `delivery` and no
    | `retail` — there is no catalog behind them, so the type IS what the
    | customer pays for, the same reading «أنواع الأبواب والشبابيك» gets.
    |
    | The three grab-bag rows are declared empty for all seven in
    | `child_option_scopes.php`: a child that can now name its trade should not
    | also be asked whether it deals in «معدات ومستلزمات».
    */
    'groups' => [
        'الآلات والمعدات الزراعية' => [
            'name_en' => 'Agricultural Machinery', 'price_role' => 'line', 'children' => [12],
            'options' => [
                'جرارات زراعية' => 'Tractors',
                'محاريث ومعدات حرث' => 'Ploughs & Tillage',
                'آلات بذر وزراعة' => 'Seeders & Planters',
                'حصادات ودراسات' => 'Harvesters & Threshers',
                'مضخات ومواتير ري' => 'Irrigation Pumps',
                'أنظمة ري بالتنقيط والرش' => 'Drip & Sprinkler Systems',
                'رشاشات مبيدات' => 'Crop Sprayers',
                'مقطورات ومعدات نقل' => 'Trailers & Handling',
                'معدات فرز وتدريج المحاصيل' => 'Grading & Sorting Equipment',
                'صوب زراعية ومستلزماتها' => 'Greenhouses & Supplies',
                'عدد وأدوات يدوية زراعية' => 'Agricultural Hand Tools',
                'قطع غيار وصيانة معدات' => 'Machinery Spares & Service',
            ],
        ],

        /*
         * One list, and after the merge one child holding all of it — the
         * per-animal narrowing that lived here for a day is kept as a comment
         * in `child_option_scopes.php` for whoever splits them again.
         */
        'معدات وتجهيزات المزارع' => [
            'name_en' => 'Livestock Farm Equipment', 'price_role' => 'line', 'children' => [171], // the three merged 2026-08-12
            'options' => [
                'بطاريات وأقفاص تربية' => 'Cages & Battery Systems',
                'حضانات وفقاسات' => 'Incubators & Hatchers',
                'معالف ومشارب' => 'Feeders & Drinkers',
                'أنظمة تهوية ومراوح' => 'Ventilation & Fans',
                'أنظمة تدفئة وتبريد' => 'Heating & Cooling Systems',
                'سيلوهات وخزانات أعلاف' => 'Feed Silos & Tanks',
                'ماكينات خلط وطحن أعلاف' => 'Feed Mixers & Mills',
                'أنظمة حلابة' => 'Milking Systems',
                'مستلزمات بيطرية ولقاحات' => 'Veterinary Supplies & Vaccines',
                'أدوات نظافة وتطهير' => 'Cleaning & Disinfection',
                'شبك وأسوار حظائر' => 'Pens, Mesh & Fencing',
                'أنظمة تحكم ومراقبة' => 'Farm Control & Monitoring',
            ],
        ],

        /*
         * What the producer actually sells, narrowed per child in the scope
         * file: cattle AND rabbits to #170 after the merge, fish to #102. One
         * group because it is one question — «what do you raise».
         */
        'أنواع الثروة الحيوانية والسمكية' => [
            'name_en' => 'Livestock & Fish Stock', 'price_role' => 'line', 'children' => [170, 102], // «أرانب» folded into #170
            'options' => [
                'أبقار' => 'Cattle',
                'جاموس' => 'Buffalo',
                'أغنام' => 'Sheep',
                'ماعز' => 'Goats',
                'عجول تسمين' => 'Fattening Calves',
                'جمال' => 'Camels',
                'خيول' => 'Horses',
                'أرانب تربية' => 'Breeding Rabbits',
                'أرانب تسمين' => 'Fattening Rabbits',
                'أسماك بلطي' => 'Tilapia',
                'أسماك بوري' => 'Mullet',
                'قراميط' => 'Catfish',
                'أسماك زينة' => 'Ornamental Fish',
                'زريعة وإصبعيات' => 'Fry & Fingerlings',
            ],
        ],

        /*
         * «تقاوي وأسمدة ومبيدات» #14 after the 2026-08-12 merge — and the merge
         * closed a gap rather than only removing a row: «مبيدات» had no child
         * anywhere on the platform, so a pesticide dealer had nowhere to
         * register and no word to register with.
         */
        'مستلزمات المحاصيل' => [
            'name_en' => 'Crop Inputs', 'price_role' => 'line', 'children' => [14],
            'options' => [
                'تقاوي وبذور' => 'Seeds',
                'شتلات' => 'Seedlings',
                'أسمدة كيماوية' => 'Chemical Fertiliser',
                'أسمدة عضوية' => 'Organic Fertiliser',
                'أسمدة ورقية ومخصبات' => 'Foliar Feeds',
                'مبيدات حشرية' => 'Insecticides',
                'مبيدات فطرية' => 'Fungicides',
                'مبيدات أعشاب' => 'Herbicides',
                'منظمات نمو' => 'Growth Regulators',
                'بيتموس وبيئات زراعة' => 'Peat & Growing Media',
                'أدوات رش ومكافحة' => 'Spraying & Control Gear',
            ],
        ],

        /*
         * #114 «خضار وفاكهة» joined on 2026-08-15, and it is the child that
         * needs this group most.
         *
         * Its old vocabulary was the supermarket counter list, and when that
         * came off — it said «خضار وفاكهة» back at a trader who deals in
         * nothing else — the produce varieties above replaced it as the LINE
         * and nothing replaced the price axis. A crop with no unit is half an
         * answer: an exporter quotes strawberries by the tonne, a wholesaler
         * sells them by the crate, and the same mango is three prices before
         * anyone has named a variety.
         */
        'وحدة البيع' => [
            'name_en' => 'Selling Unit', 'price_role' => 'modifier', 'children' => [14, 107, 128, 114], // «أسمدة» folded into #14 on 2026-08-12
            'options' => [
                'بالكيلو' => 'Per Kilo',
                'بالشيكارة' => 'Per Sack',
                'بالطن' => 'Per Tonne',
                'بالأردب' => 'Per Ardeb',
                'بالعبوة' => 'Per Pack',
            ],
        ],

        /*
        | ── the last child of the root still answering the grab-bag ────────
        |
        | «أعلاف» #107, reviewed 2026-08-16 with the rest of the root. Its LINE
        | was «مستلزمات المزارع» — two words, «مستلزمات زراعية» and «ماشية
        | وطيور» — which is the grab-bag the other six children were taken off
        | in August, and it was left on deliberately: at the time #107 had no
        | vocabulary of its own and «مستلزمات زراعية» was at least true.
        |
        | It is not true enough to price on. A feed merchant is asked one
        | question before any other — WHICH FEED — and could not answer it: not
        | which animal it is for, and not what it is made of. Its «وحدة البيع»
        | modifier (بالطن · بالشيكارة) had nothing underneath it, the same
        | emptiness «حالة الدواجن» and «وحدة البيع» were sitting over on دواجن
        | and حبوب وغلال.
        |
        | Two axes in one list, because that is how the trade is actually
        | quoted: the finished ration is named by the animal it feeds, and the
        | raw material is named by what it is. A feed mill sells both.
        */
        'أنواع الأعلاف' => [
            'name_en' => 'Animal Feed', 'price_role' => 'line', 'children' => [107],
            'options' => [
                // العلف المصنّع، باسم الحيوان
                'أعلاف دواجن' => 'Poultry Feed',
                'أعلاف مواشي' => 'Cattle Feed',
                'أعلاف أغنام وماعز' => 'Sheep & Goat Feed',
                'أعلاف أرانب' => 'Rabbit Feed',
                'أعلاف أسماك' => 'Fish Feed',
                'أعلاف خيول' => 'Horse Feed',
                'أعلاف طيور زينة' => 'Cage Bird Feed',
                // الخامات، باسم المادة
                'فول صويا' => 'Soybean Meal',
                'كسب' => 'Oilseed Cake',
                'دريس وبرسيم' => 'Hay & Clover',
                'تبن' => 'Straw',
                'سيلاج' => 'Silage',
                'مولاس' => 'Molasses',
                'مركزات وإضافات علفية' => 'Feed Concentrates & Additives',
                'فيتامينات ومعادن' => 'Vitamins & Minerals',
            ],
        ],

        /*
        | ── the bird, and the grain, 2026-08-16 ────────────────────────────
        |
        | «حبوب وغلال - دواجن الخيارات بها هى خيارات السوبر ماركت وليست انواع
        | الحبوب الحقيقة ولا الدواجن من فراخ وسمان وبط وحمام الخ».
        |
        | The same fault «خضار وفاكهة» had two days ago, on the two children
        | beside it. Their `line` was a supermarket AISLE:
        |
        |   دواجن #229      answered «أقسام الطازج واللحوم» — أجبان، فسيخ، رنجة،
        |                   ألبان وبيض، خضار وفاكهة. A poultry trader offering
        |                   herring and cheese, and unable to say «بط».
        |   حبوب وغلال #128 answered «أقسام البقالة الجافة» with two words —
        |                   «مواد غذائية» and «مكرونات وأرز وحبوب». A grain
        |                   merchant whose whole trade is one shelf label.
        |
        | An aisle is where a SHOPPER finds a thing; these two children sell the
        | thing itself, by the tonne, to traders and exporters. Both already
        | carry the right price axis — «وحدة البيع» on the grain, «حالة الدواجن»
        | on the bird — so only the line was missing, and the modifier is what
        | proves the line belongs: «بط حي» and «بط مذبوح ومنظف» are two prices
        | of one row, which is exactly what a modifier is for.
        |
        | `line` and shared (not root-scoped): «دواجن» sits under «المحلات أو
        | أونلاين» as well, and a poultry shop sells the same birds a poultry
        | wholesaler does.
        |
        | The aisle groups themselves are untouched — they are the supermarket's
        | own vocabulary and five markets answer them correctly. What changes is
        | that these two children are no longer asked. Declared empty for them
        | in `child_option_scopes.php`.
        */
        'أنواع الدواجن والطيور' => [
            'name_en' => 'Poultry & Fowl', 'price_role' => 'line', 'children' => [229],
            'options' => [
                'فراخ بيضاء' => 'White Broiler Chicken',
                'فراخ بلدي' => 'Baladi Chicken',
                'فراخ ساسو' => 'Sasso Chicken',
                'بط' => 'Duck',
                'وز' => 'Goose',
                'رومي' => 'Turkey',
                'حمام' => 'Pigeon',
                'سمان' => 'Quail',
                'كتاكيت' => 'Day-old Chicks',
                'بيض مائدة' => 'Table Eggs',
                'بيض تفريخ' => 'Hatching Eggs',
            ],
        ],

        /*
         * Grain, pulse and oilseed — the three things «حبوب وغلال» means in
         * Egypt. Milled output belongs here too: a grain merchant sells the
         * wheat and the flour and the bran that come out of it, at three
         * different prices.
         *
         * Feed is deliberately absent — «أعلاف» #107 is its own child and has
         * its own trade.
         */
        'أنواع الحبوب والغلال' => [
            'name_en' => 'Grains & Cereals', 'price_role' => 'line', 'children' => [128],
            'options' => [
                'قمح' => 'Wheat',
                'ذرة صفراء' => 'Yellow Corn',
                'ذرة بيضاء' => 'White Corn',
                'أرز شعير' => 'Paddy Rice',
                'أرز أبيض' => 'White Rice',
                'شعير' => 'Barley',
                'شوفان' => 'Oats',
                'فول' => 'Fava Beans',
                'عدس' => 'Lentils',
                'حمص' => 'Chickpeas',
                'لوبيا' => 'Cowpeas',
                'فاصوليا جافة' => 'Dry Beans',
                'سمسم' => 'Sesame',
                'عباد شمس' => 'Sunflower Seeds',
                'كتان' => 'Flaxseed',
                'فول سوداني' => 'Peanuts',
                'دقيق' => 'Flour',
                'ردة ونخالة' => 'Bran',
            ],
        ],

        /*
        | ── what a produce trader actually deals in, 2026-08-14 ─────────────
        |
        | «خضار وفاكهه فى زراعية وحيوانية يجب ان يكون لها خيارات منجو فراولة
        | طماطم بطاطس وهكذا لان هى للتجار فى جميع اصناف الخضار والفاكهة وايضا
        | للتصدير والاستيراد».
        |
        | #114 answered «أقسام الطازج واللميوم» — the supermarket counter list,
        | which says «خضار وفاكهة» back at itself. A trader is not a counter: he
        | deals in a mango season and a potato contract, and an importer or
        | exporter is asked which crop before anything else.
        |
        | `line`, because the crop IS the priced thing — there is no catalog
        | behind #114 and a tonne of strawberries and a tonne of onions are not
        | one rate.
        |
        | Egypt's real trade and export staples first — oranges, potatoes,
        | onions, garlic, grapes, strawberries, mango, pomegranate, dates and
        | beans are what actually leave the country — then the rest of the
        | domestic basket.
        */
        /*
        | ── split in two, 2026-08-24 ────────────────────────────────────────
        |
        | «فواكة تحتها كل الفواكة» — المالك. The list was already two lists: the
        | «// فواكه» and «// خضار» comments below were doing the separating,
        | which is a separation a comment cannot make. It matters now because
        | the option GROUP is the SECTION of an arranged menu
        | (App\Services\Menu\MenuOutline), so one group meant one section of
        | forty-five bands and no «فاكهة» to put the fruit under.
        |
        | `ProduceAisleSplitSeeder` moved the rows; this file follows, or the
        | next run of the vocabulary seeder writes forty-five of them back into
        | the emptied parent. Both keep #114 and both stay `line` — the crop IS
        | the priced thing, and a tonne of strawberries and a tonne of onions
        | are not one rate.
        */
        'الفواكه' => [
            'name_en' => 'Fruit', 'price_role' => 'line', 'children' => [114],
            'options' => [
                'مانجو' => 'Mango',
                'فراولة' => 'Strawberry',
                'عنب' => 'Grapes',
                'برتقال' => 'Orange',
                'يوسفي' => 'Mandarin',
                'ليمون' => 'Lemon',
                'موز' => 'Banana',
                'تفاح' => 'Apple',
                'جوافة' => 'Guava',
                'رمان' => 'Pomegranate',
                'بلح وتمر' => 'Dates',
                'تين' => 'Figs',
                'خوخ' => 'Peach',
                'مشمش' => 'Apricot',
                'بطيخ' => 'Watermelon',
                'شمام وكانتلوب' => 'Melon & Cantaloupe',
                'كمثرى' => 'Pear',
                'جريب فروت' => 'Grapefruit',
            ],
        ],

        'الخضروات' => [
            'name_en' => 'Vegetables', 'price_role' => 'line', 'children' => [114],
            'options' => [
                'طماطم' => 'Tomato',
                'بطاطس' => 'Potato',
                'بصل' => 'Onion',
                'ثوم' => 'Garlic',
                'خيار' => 'Cucumber',
                'فلفل ألوان' => 'Bell Pepper',
                'فلفل حار' => 'Chilli',
                'باذنجان' => 'Aubergine',
                'كوسة' => 'Courgette',
                'جزر' => 'Carrot',
                'بامية' => 'Okra',
                'فاصوليا خضراء' => 'Green Beans',
                'بازلاء' => 'Peas',
                'لوبيا' => 'Cowpeas',
                'ملوخية' => 'Molokhia',
                'سبانخ' => 'Spinach',
                'خس' => 'Lettuce',
                'كرنب' => 'Cabbage',
                'قرنبيط' => 'Cauliflower',
                'بروكلي' => 'Broccoli',
                'بنجر' => 'Beetroot',
                'لفت وفجل' => 'Turnip & Radish',
                'بطاطا' => 'Sweet Potato',
                'قرع عسلي' => 'Pumpkin',
                'خرشوف' => 'Artichoke',
                'ذرة' => 'Corn',
                'أعشاب وورقيات' => 'Herbs & Greens',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | The three units a live animal is quoted in
    |--------------------------------------------------------------------------
    | «وحدة البيع» went to the four bulk CROP traders and stopped there, and its
    | own note above says why it matters: «a crop with no unit is half an
    | answer». The livestock half of this root is in exactly that position, and
    | worse — the five rows it would have inherited do not fit it at all. Nobody
    | sells a buffalo «بالأردب».
    |
    | Egypt quotes a live animal three ways the crop list cannot say:
    |
    |   بالرأس   a cow, a buffalo, a goat, a duck — the unit of the whole animal
    |   بالطبق   eggs, thirty to the tray, which is how every egg price is given
    |   بالألف   fingerlings and day-old chicks, quoted per thousand and never
    |            per kilo — «الألف صوص» and «الألف زريعة» are the trade's words
    |
    | Minted with `extend` and handed out in `links`, deliberately: adding them
    | to the group's own `children` would offer a fertiliser merchant a price
    | per head. The crop traders keep the five they have.
    */
    'extend' => [
        'وحدة البيع' => [
            'بالرأس' => 'Per Head',
            'بالطبق' => 'Per Tray',
            'بالألف' => 'Per Thousand',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Borrowed, not written twice
    |--------------------------------------------------------------------------
    | «ذرة صفراء» and «ردة ونخالة» are the two raw materials the feed merchant
    | and the grain merchant both deal in — the mill buys the maize and sells
    | the bran that comes back out of it. They live in «أنواع الحبوب والغلال»,
    | and #107 reaches them there rather than getting a second row with the same
    | word on it. Same move «رحلات ومراكب» makes on «خدمات السياحة والسفر»:
    | duplicating a word across two groups is how a customer ends up narrowing
    | twice for one thing.
    */
    'links' => [
        107 => [
            'أنواع الحبوب والغلال' => ['ذرة صفراء', 'ردة ونخالة'],
        ],

        /*
         * A fish farm quotes the pond by the kilo, the wholesale lot by the
         * tonne, and the fry by the thousand. Nothing else in the group is a
         * fish price — a sack and an ardeb are dry measures.
         */
        102 => ['وحدة البيع' => ['بالكيلو', 'بالطن', 'بالألف']],

        /*
         * Livestock is bought per head and settled on the scale — «بالرأس» is
         * the deal and «بالكيلو» is the live weight it is priced off. The
         * merchant's own tick decides which he quotes on.
         */
        170 => ['وحدة البيع' => ['بالرأس', 'بالكيلو']],

        /*
         * Poultry is the one child that needs all four: birds per head, meat
         * on the scale, table eggs by the tray, day-old chicks by the thousand.
         * Its «حالة الدواجن» modifier (حي / مذبوح / مقطّع) already says WHAT
         * state it is in and never how much of it — the two are the two halves
         * of one quote.
         */
        229 => ['وحدة البيع' => ['بالرأس', 'بالكيلو', 'بالطبق', 'بالألف']],
    ],
];
