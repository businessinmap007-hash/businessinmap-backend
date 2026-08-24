<?php

/*
|--------------------------------------------------------------------------
| «أصناف المنتجات الغذائية» becomes thirteen sections, and every section
| gets the words a price hangs on
|--------------------------------------------------------------------------
| المالك، 2026-08-24:
|
|   «لدينا مجموعة خيارات أصناف المنتجات الغذائية قم بمراجعة فروعها، يوجد بها
|    مثلاً حبوب وغلال ولدينا بالفعل مجموعة للحبوب والغلال بأنواعهم واللحوم
|    بأنواعها والدواجن بأنواعها. إذا كان هناك بند مثل زيوت وسمن اعمل مجموعة
|    لها وأضف فروعها، وبعد اكتمال كل فروع أصناف المنتجات الغذائية نلغيها
|    ونضيف المجموعات إلى السوبر ماركت والهايبر والميني ماركت.»
|
| ── What #285 actually was ────────────────────────────────────────────────
|
| Twenty rows — «زيوت وسمن», «بهارات وتوابل», «معلبات» — carried by five
| children and priced by nobody. Every one of them is a SHELF, not a thing:
| «زيوت وسمن» is not something a customer buys, «زيت ذرة» is. It is the same
| finding as «لحوم ودواجن» the day before — a counter with nothing behind it —
| twenty times over.
|
| Seven of the twenty already had their words one group over, written for the
| trades that sell them:
|
|     حبوب وبقوليات · أرز · دقيق  →  أنواع الحبوب والغلال   (extended below)
|     ألبان وأجبان                →  أنواع الألبان والأجبان
|     لحوم ودواجن مجمدة           →  أنواع اللحوم + أنواع الدواجن والطيور
|     أسماك ومأكولات بحرية        →  أنواع الأسماك والمأكولات البحرية
|     مخبوزات معبأة               →  أنواع المخبوزات
|
| The other thirteen had nothing, and are the `groups` below.
|
| ── Why the parent is retired rather than emptied ─────────────────────────
|
| The produce split MOVED its rows out and left the group standing empty. This
| one cannot: a shelf name is not a variety, so «زيوت وسمن» has nowhere to go —
| the group «أنواع الزيوت والسمن» REPLACES it. So the twenty rows stay where
| they are and the group is switched off, which keeps the record of what the
| shelves were called and takes them out of every picker
| (`MerchantOfferingVocabulary` filters on `option_groups.is_active`).
|
| Nothing is lost by doing it: on the day this was written the twenty carried
| zero merchant ticks, zero `business_service_prices` rows and zero
| `offering_options`. They were never priced because they never could be.
|
| ── Fruit: one row per variety, because the price is per variety ──────────
|
| «البرتقال يكون كل نوع فى اختيار: برتقال سكري - برتقال أبو سرة - برتقال بلدي…
|  لأن المانجو أنواع كتيرة فالأفضل يكون كل نوع منفرد يُسعّر ويكون له كمية حتى
|  يختار منها العميل ما يناسبه.»
|
| A variety is not a modifier here, and that is the whole reason: مانجو عويس
| and مانجو زبدية are different prices AND different stock. `available_quantity`
| lives on `menu_items`, which is the LINE — so a variety that is only a
| modifier can never run out on its own. One row per variety is the only shape
| that lets a customer be told «عويس نفد، والزبدية موجودة».
|
| The generic row stays beside them: a stall that sells «برتقال» full stop is
| still a stall, and taking the word away would empty a merchant's own tick.
*/

return [

    'source_group' => 'أصناف المنتجات الغذائية',

    /*
    | An option that names two vegetables cannot be priced, for the same reason
    | a shelf cannot: they are two prices. «فجل» is added below as its own row.
    */
    'rename_options' => [
        'الخضروات' => [
            'لفت وفجل' => ['لفت', 'Turnip'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | The thirteen shelves that became lists
    |--------------------------------------------------------------------------
    | All `line`: each row is a thing a customer pays for, by a unit, with a
    | quantity. Every one of them is named in data/option_price_roles.php too —
    | a group missing from that file is pushed back to `descriptive` on the next
    | OptionPriceRolesSeeder run and quietly leaves the pricing screen.
    */
    'groups' => [

        'أنواع المكرونة' => [
            'name_en' => 'Pasta Varieties',
            'options' => [
                'مكرونة اسباجيتي' => 'Spaghetti',
                'مكرونة قلم' => 'Penne',
                'مكرونة كوع' => 'Elbow Macaroni',
                'مكرونة صدفية' => 'Shell Pasta',
                'مكرونة فارفالي' => 'Farfalle',
                'مكرونة لسان عصفور' => 'Orzo',
                'شعرية' => 'Vermicelli',
                'لازانيا' => 'Lasagne Sheets',
                'نودلز سريعة التحضير' => 'Instant Noodles',
                'كسكسي' => 'Couscous',
                'مكرونة قمح كامل' => 'Wholewheat Pasta',
            ],
        ],

        // The branch the owner named by hand: «إذا كان هناك بند مثل زيوت وسمن».
        'أنواع الزيوت والسمن' => [
            'name_en' => 'Cooking Oil & Ghee Varieties',
            'options' => [
                'زيت ذرة' => 'Corn Oil',
                'زيت عباد الشمس' => 'Sunflower Oil',
                'زيت خليط' => 'Blended Cooking Oil',
                'زيت نخيل' => 'Palm Oil',
                'زيت صويا' => 'Soybean Oil',
                'زيت زيتون' => 'Olive Oil',
                'زيت سمسم' => 'Sesame Oil',
                'زيت جوز الهند' => 'Coconut Oil',
                'زيت بذرة الكتان' => 'Flaxseed Oil',
                'سمن بلدي' => 'Baladi Ghee',
                'سمن نباتي' => 'Vegetable Ghee',
                'طحينة' => 'Tahini',
            ],
        ],

        'أنواع السكر والمحليات' => [
            'name_en' => 'Sugar & Sweetener Varieties',
            'options' => [
                'سكر أبيض' => 'White Sugar',
                'سكر بني' => 'Brown Sugar',
                'سكر بودرة' => 'Icing Sugar',
                'سكر نبات' => 'Rock Sugar',
                'محليات صناعية' => 'Artificial Sweeteners',
                'جلوكوز' => 'Glucose Syrup',
            ],
        ],

        'أنواع البهارات والتوابل' => [
            'name_en' => 'Spice & Seasoning Varieties',
            'options' => [
                'ملح طعام' => 'Table Salt',
                'فلفل أسود' => 'Black Pepper',
                'فلفل أبيض' => 'White Pepper',
                'شطة مطحونة' => 'Chilli Powder',
                'بابريكا' => 'Paprika',
                'كمون' => 'Cumin',
                'كزبرة ناشفة' => 'Dried Coriander',
                'كركم' => 'Turmeric',
                'قرفة' => 'Cinnamon',
                'قرنفل' => 'Cloves',
                'حبهان' => 'Cardamom',
                'جوزة الطيب' => 'Nutmeg',
                'ورق لورا' => 'Bay Leaves',
                'زنجبيل مطحون' => 'Ground Ginger',
                'بهارات مشكلة' => 'Mixed Spices',
                'زعتر' => 'Thyme',
                'شمر' => 'Fennel Seeds',
                'ينسون' => 'Anise',
                'حلبة' => 'Fenugreek',
                'مستكة' => 'Mastic',
                'زعفران' => 'Saffron',
            ],
        ],

        'أنواع المعلبات' => [
            'name_en' => 'Canned Food Varieties',
            'options' => [
                'تونة معلبة' => 'Canned Tuna',
                'سردين معلب' => 'Canned Sardines',
                'فول معلب' => 'Canned Fava Beans',
                'حمص معلب' => 'Canned Chickpeas',
                'فاصوليا معلبة' => 'Canned Beans',
                'بازلاء معلبة' => 'Canned Peas',
                'ذرة معلبة' => 'Canned Sweetcorn',
                'مشروم معلب' => 'Canned Mushrooms',
                'طماطم معلبة' => 'Canned Tomatoes',
                'صلصة طماطم' => 'Tomato Paste',
                'لحوم معلبة' => 'Canned Meat',
                'لانشون' => 'Luncheon Meat',
                'خضار مشكل معلب' => 'Canned Mixed Vegetables',
                'حليب مكثف' => 'Condensed Milk',
            ],
        ],

        /*
        | Olives sit HERE and not in «أنواع المعلبات»: an Egyptian grocer keeps
        | them in the pickle barrel beside the لفت, not on the tin shelf.
        */
        'أنواع المخللات والخل' => [
            'name_en' => 'Pickle & Vinegar Varieties',
            'options' => [
                'خل أبيض' => 'White Vinegar',
                'خل تفاح' => 'Apple Cider Vinegar',
                'خل بلسمك' => 'Balsamic Vinegar',
                'مخلل خيار' => 'Pickled Cucumber',
                'مخلل لفت' => 'Pickled Turnip',
                'مخلل جزر' => 'Pickled Carrots',
                'ليمون مخلل' => 'Pickled Lemon',
                'فلفل مخلل' => 'Pickled Peppers',
                'طرشي مشكل' => 'Mixed Pickles',
                'زيتون أخضر' => 'Green Olives',
                'زيتون أسود' => 'Black Olives',
            ],
        ],

        'أنواع الصلصات والشوربات' => [
            'name_en' => 'Sauce & Soup Varieties',
            'options' => [
                'كاتشب' => 'Ketchup',
                'مايونيز' => 'Mayonnaise',
                'خردل' => 'Mustard',
                'صلصة باربكيو' => 'BBQ Sauce',
                'صويا صوص' => 'Soy Sauce',
                'صلصة حارة' => 'Hot Sauce',
                'صلصة بيتزا' => 'Pizza Sauce',
                'صلصة مكرونة' => 'Pasta Sauce',
                'دريسنج سلطة' => 'Salad Dressing',
                'مكعبات مرقة' => 'Stock Cubes',
                'شوربة سريعة التحضير' => 'Instant Soup',
            ],
        ],

        /*
        | «حلاوة طحينية» is NOT here — «حلاوة وطحينة» is already a row in «أصناف
        | الحلويات والجاتوه», and `options.name_en` is unique platform-wide: a
        | second one would have been written as «Halva (2)», which is not a food.
        */
        'أنواع العسل والمربى' => [
            'name_en' => 'Honey & Jam Varieties',
            'options' => [
                'عسل نحل' => 'Bee Honey',
                'عسل أسود' => 'Cane Molasses',
                'مربى فراولة' => 'Strawberry Jam',
                'مربى مشمش' => 'Apricot Jam',
                'مربى تين' => 'Fig Jam',
                'مربى برتقال' => 'Orange Marmalade',
                'مربى مشكل' => 'Mixed Fruit Jam',
                'زبدة فول سوداني' => 'Peanut Butter',
                'كريمة شوكولاتة' => 'Chocolate Spread',
                'دبس رمان' => 'Pomegranate Molasses',
            ],
        ],

        /*
        | «درجة التحميص والطحن» beside this one is a MODIFIER — a coffee
        | merchant's «فاتح/غامق» crossed with the bean. This is the shelf list a
        | grocer prices: a packet of tea is a line, its roast is not.
        */
        'أنواع الشاي والقهوة' => [
            'name_en' => 'Tea & Coffee Varieties',
            'options' => [
                'شاي فتلة' => 'Tea Bags',
                'شاي ناعم' => 'Loose Black Tea',
                'شاي أخضر' => 'Green Tea',
                'شاي بالأعشاب' => 'Herbal Tea',
                'بن محوج' => 'Cardamom Coffee',
                'بن سادة' => 'Plain Ground Coffee',
                'قهوة سريعة الذوبان' => 'Instant Coffee',
                'كابتشينو' => 'Cappuccino Mix',
                'كاكاو' => 'Cocoa Powder',
            ],
        ],

        'أنواع المكسرات والتسالي' => [
            'name_en' => 'Nut & Snack Varieties',
            'options' => [
                'لب سوري' => 'Roasted Sunflower Seeds',
                'لب أبيض' => 'Pumpkin Seeds',
                'سوداني محمص' => 'Roasted Peanuts',
                'بندق' => 'Hazelnuts',
                'عين جمل' => 'Walnuts',
                'كاجو' => 'Cashews',
                'لوز' => 'Almonds',
                'فستق' => 'Pistachios',
                'مكسرات مشكلة' => 'Mixed Nuts',
                'حمص الشام' => 'Roasted Chickpeas',
                'ترمس' => 'Lupini Beans',
                'زبيب' => 'Raisins',
                'تين مجفف' => 'Dried Figs',
                'قمر الدين' => 'Apricot Leather',
                'شيبسي' => 'Potato Chips',
                'بوشار' => 'Popcorn',
            ],
        ],

        /*
        | The patisserie counter «أصناف الحلويات والجاتوه» is what a shop MAKES.
        | This is what a grocer stocks in a wrapper — two trades, two lists, and
        | the only row that could read the same («بسكويت») is priced by the
        | packet here and by the kilo there.
        */
        'أنواع الحلويات المعبأة' => [
            'name_en' => 'Packaged Confectionery Varieties',
            'options' => [
                'شوكولاتة ألواح' => 'Chocolate Bars',
                'بونبون' => 'Boiled Sweets',
                'علكة' => 'Chewing Gum',
                'بسكويت معبأ' => 'Packaged Biscuits',
                'ويفر' => 'Wafers',
                'كيك معبأ' => 'Packaged Cake',
                'مصاصات' => 'Lollipops',
                'جيلي' => 'Jelly Sweets',
                'مارشميلو' => 'Marshmallow',
                'شوكولاتة للطبخ' => 'Cooking Chocolate',
            ],
        ],

        'أنواع أغذية الأطفال' => [
            'name_en' => 'Baby Food Varieties',
            'options' => [
                'لبن أطفال' => 'Infant Formula',
                'حبوب أطفال' => 'Baby Cereal',
                'بيوريه فواكه' => 'Fruit Puree',
                'بيوريه خضار' => 'Vegetable Puree',
                'بسكويت أطفال' => 'Baby Biscuits',
                'عصائر أطفال' => 'Baby Juice',
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | «نظّف أقسام المنزل والعناية» — المالك، 2026-08-24
        |----------------------------------------------------------------------
        | The last of the five aisle drawers, and the one that is not food at
        | all: منظفات، عناية شخصية، منتجات أطفال، مستلزمات حيوانات أليفة، فحم،
        | أدوات منزلية. Six words, the same disease — «منظفات» is an aisle you
        | walk to, «مسحوق غسيل ٣ كيلو» is what leaves the shop.
        |
        | ⚠ «أدوات منزلية» is NOT here. «مستلزمات المنزل» #404 already exists —
        | أواني طهي، أدوات مائدة، تخزين وحفظ — written for «صيني ومستلزمات
        | بيت». It is BORROWED and extended below, not cloned: a second
        | housewares list would be this taxonomy's oldest disease, said with
        | pots.
        */

        'أنواع المنظفات' => [
            'name_en' => 'Detergent Varieties',
            'options' => [
                'مسحوق غسيل' => 'Washing Powder',
                'صابون غسيل' => 'Laundry Bar Soap',
                'منعم أقمشة' => 'Fabric Softener',
                'سائل غسيل أطباق' => 'Dishwashing Liquid',
                'مسحوق غسالة أطباق' => 'Dishwasher Powder',
                'صابون سائل' => 'Liquid Hand Soap',
                'كلور' => 'Bleach',
                'مطهر أرضيات' => 'Floor Disinfectant',
                'منظف زجاج' => 'Glass Cleaner',
                'مزيل دهون' => 'Degreaser',
                'ملمع أثاث' => 'Furniture Polish',
                'معطر جو' => 'Air Freshener',
                'مبيد حشري منزلي' => 'Household Insecticide',
                'أكياس قمامة' => 'Bin Bags',
                'إسفنج وليف' => 'Sponges & Scourers',
                'قفازات تنظيف' => 'Cleaning Gloves',
            ],
        ],

        /*
        | «أصناف مستحضرات التجميل» #401 is the cosmetics SHOP — makeup, a trade
        | of its own. This is the supermarket shelf: what a household replaces
        | every month, by the bottle.
        */
        'أصناف العناية الشخصية' => [
            'name_en' => 'Personal Care Ranges',
            'options' => [
                'شامبو' => 'Shampoo',
                'بلسم شعر' => 'Hair Conditioner',
                'زيت شعر' => 'Hair Oil',
                'صبغة شعر' => 'Hair Dye',
                'صابون استحمام' => 'Bath Soap',
                'جل استحمام' => 'Shower Gel',
                'معجون أسنان' => 'Toothpaste',
                'فرشاة أسنان' => 'Toothbrush',
                'غسول فم' => 'Mouthwash',
                'مزيل عرق' => 'Deodorant',
                'كريم مرطب' => 'Moisturiser',
                'واقي شمس' => 'Sunscreen',
                'شفرات حلاقة' => 'Razors',
                'كريم حلاقة' => 'Shaving Cream',
                'مزيل شعر' => 'Hair Removal Cream',
                'فوط صحية' => 'Sanitary Pads',
                'مناديل ورقية' => 'Tissues',
                'قطن وأعواد أذن' => 'Cotton Wool & Swabs',
            ],
        ],

        /*
        | The other half of a baby's shelf. «أنواع أغذية الأطفال» above is what
        | the child eats; this is everything else, and the two are two prices on
        | two different aisles — a pharmacy sells the nappies and not the cereal.
        */
        'مستلزمات الأطفال' => [
            'name_en' => 'Baby Care Supplies',
            'options' => [
                'حفاضات' => 'Nappies',
                'مناديل مبللة' => 'Baby Wipes',
                'شامبو أطفال' => 'Baby Shampoo',
                'صابون أطفال' => 'Baby Soap',
                'بودرة أطفال' => 'Baby Powder',
                'كريم تسلخات' => 'Nappy Rash Cream',
                'ببرونة' => 'Feeding Bottle',
                'مصاصة' => 'Pacifier',
                'جهاز تعقيم ببرونات' => 'Bottle Steriliser',
            ],
        ],

        /*
        | «أنواع الأعلاف» #581 is the FARM — طن أعلاف دواجن. A pet is not
        | livestock and a tin of cat food is not a tonne of feed.
        */
        'مستلزمات الحيوانات الأليفة' => [
            'name_en' => 'Pet Supplies',
            'options' => [
                'طعام قطط' => 'Cat Food',
                'طعام كلاب' => 'Dog Food',
                'طعام طيور' => 'Bird Food',
                'طعام أسماك زينة' => 'Ornamental Fish Food',
                'رمل قطط' => 'Cat Litter',
                'أقفاص وبيوت' => 'Cages & Kennels',
                'أحواض أسماك' => 'Aquariums',
                'أطواق ومقاود' => 'Collars & Leads',
                'ألعاب حيوانات' => 'Pet Toys',
                'شامبو حيوانات' => 'Pet Shampoo',
                'مستلزمات نظافة الحيوانات' => 'Pet Grooming Supplies',
            ],
        ],

        /*
        | «فحم» was one aisle row and it is a whole counter in an Egyptian
        | grocery: the shisha coal, the barbecue coal and the firewood are three
        | prices, and the matches and the candles sit on the same shelf because
        | they are the same errand.
        */
        'أنواع الفحم والوقود المنزلي' => [
            'name_en' => 'Charcoal & Household Fuel',
            'options' => [
                'فحم خشب' => 'Wood Charcoal',
                'فحم مضغوط' => 'Briquette Charcoal',
                'فحم شيشة' => 'Shisha Charcoal',
                'حطب' => 'Firewood',
                'سبيرتو' => 'Methylated Spirit',
                /*
                 * ⚠ «ولاعات» is NOT written here. The word already exists once
                 * on the platform, in «مشتقات التدخين» — the tobacconist's
                 * list — and `options.name_en` is unique platform-wide, so a
                 * second one would be minted as «Lighters (2)».
                 *
                 * Left out rather than duplicated: one word, one row is the
                 * rule that three «Tilapia» were written to teach. A grocer who
                 * wants the lighter takes the existing row by hand; borrowing
                 * the whole tobacco list to reach it would hand him سجائر.
                 */
                'كبريت' => 'Matches',
                'شمع' => 'Candles',
                'وقود مواقد' => 'Stove Fuel',
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | «نظّف أقسام الطازج واللحوم وبنود المخبوزات» — المالك، 2026-08-24
        |----------------------------------------------------------------------
        | The last two drawers, and I had argued for keeping them: both are work
        | somebody DOES on the premises — a counter is weighed, a bakery is
        | baked — rather than a shelf a packet sits on. The owner disagreed, and
        | reading their eleven rows against what now exists, he is right:
        |
        |     أجبان · ألبان وبيض        →  أنواع الألبان والأجبان
        |     أسماك ومأكولات بحرية طازجة →  أنواع الأسماك والمأكولات البحرية
        |     لحوم ودواجن               →  أنواع اللحوم + أنواع الدواجن والطيور
        |     خضار وفاكهة               →  الفواكه + الخضروات
        |     مخبوزات                   →  أنواع المخبوزات
        |     حلويات وشوكولاتة          →  أصناف الحلويات والجاتوه (kitchens)
        |                                  + أنواع الحلويات المعبأة (grocers)
        |     وافل · آيس كريم           →  MOVED into أصناف الحلويات والجاتوه
        |     سلطة فواكة                →  «سلطة فواكه» already stands in
        |                                  «أصناف العصائر والمشروبات», and this
        |                                  spelling reached no child at all.
        |
        | Ten of the eleven were already sayable somewhere a merchant can put a
        | price on. «مجمدات» was the one that was not — hence the list below.
        |
        | ⚠ «وافل» and «آيس كريم» are MOVED, not retired: they are two things a
        | kitchen makes and sells, and `regroup` in shop_child_vocabularies.php
        | carries their existing links with them. «مخابز» keeps its waffle.
        */

        /*
        | The frozen shop's own words. It had four counters and a fridge —
        | «مجمدات» naming itself — and the varieties behind them (اللحوم،
        | الأسماك، الألبان) are all FRESH. Nothing on the platform could say
        | «بانيه» or «بطاطس مجمدة», which is most of what the trade sells.
        */
        'أنواع المجمدات' => [
            'name_en' => 'Frozen Food Varieties',
            'options' => [
                'خضار مجمد' => 'Frozen Vegetables',
                'بطاطس مجمدة' => 'Frozen Potatoes',
                'بازلاء مجمدة' => 'Frozen Peas',
                'فواكه مجمدة' => 'Frozen Fruit',
                'مأكولات بحرية مجمدة' => 'Frozen Seafood',
                'بانيه' => 'Breaded Chicken Fillet',
                'ستربس' => 'Chicken Strips',
                'ناجتس' => 'Chicken Nuggets',
                'برجر مجمد' => 'Frozen Burgers',
                'كفتة مجمدة' => 'Frozen Kofta',
                'سجق مجمد' => 'Frozen Sausage',
                'سمبوسك مجمد' => 'Frozen Sambousek',
                'عجائن ورقائق مجمدة' => 'Frozen Pastry & Sheets',
                'بيتزا مجمدة' => 'Frozen Pizza',
            ],
        ],

        /*
        | «أصناف العصائر والمشروبات» is the JUICE BAR — «عصير قصب», «سموذي»,
        | made and sold in a cup. This is the fridge: a bottle with a barcode.
        */
        'أنواع المشروبات المعبأة' => [
            'name_en' => 'Packaged Beverage Varieties',
            'options' => [
                'مياه معدنية' => 'Mineral Water',
                'مياه فوارة' => 'Sparkling Water',
                'مشروبات غازية' => 'Soft Drinks',
                'عصائر معبأة' => 'Packaged Juice',
                'مشروبات طاقة' => 'Energy Drinks',
                'مشروبات رياضية' => 'Sports Drinks',
                'شراب مركز' => 'Squash Concentrate',
                'آيس تي' => 'Iced Tea',
                'مشروب شعير' => 'Malt Beverage',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rows added to lists that already existed
    |--------------------------------------------------------------------------
    */
    'extend' => [

        /*
        | «أرز» and «دقيق» and «حبوب وبقوليات» were three shelves of #285 and
        | this is the one list all three point at. It was written for the FARM —
        | «أردب قمح» — and the pantry rows it was missing are the milled ones.
        */
        'أنواع الحبوب والغلال' => [
            'أرز بسمتي' => 'Basmati Rice',
            'دقيق فاخر' => 'Refined Flour',
            'دقيق بر' => 'Wholemeal Flour',
            'دقيق ذرة' => 'Corn Flour',
            'سميد' => 'Semolina',
            'نشا' => 'Corn Starch',
            'برغل' => 'Bulgur',
            'فريك' => 'Freekeh',
            'خميرة وبيكنج بودر' => 'Yeast & Baking Powder',
        ],

        /*
        |----------------------------------------------------------------------
        | الفواكه — the varieties, and the fruits that were missing
        |----------------------------------------------------------------------
        | The generic row stays: a stall that says «برتقال» and prices it once
        | is a real stall, and «مراجعة المنيو» will show him the varieties under
        | the same section the day he wants them.
        |
        | The varieties are the ones an Egyptian fruit seller shouts by name.
        | Anything he would not say — a cultivar code, an export grade — is not
        | here, because a word nobody prices is a longer picker and nothing else.
        */
        'الفواكه' => [
            // برتقال
            'برتقال بلدي' => 'Baladi Orange',
            'برتقال أبو سرة' => 'Navel Orange',
            'برتقال سكري' => 'Sukkari Orange',
            'برتقال صيفي' => 'Valencia Orange',
            'برتقال دم' => 'Blood Orange',
            // مانجو — «المانجو أنواع كتيرة» ‹ owner
            'مانجو عويس' => 'Owais Mango',
            'مانجو زبدية' => 'Zebda Mango',
            'مانجو فص' => 'Fass Mango',
            'مانجو تيمور' => 'Timour Mango',
            'مانجو صديقة' => 'Sedeeka Mango',
            'مانجو هندي' => 'Hindi Mango',
            'مانجو كيت' => 'Keitt Mango',
            'مانجو ناعومي' => 'Naomi Mango',
            'مانجو سكري' => 'Sukkari Mango',
            'مانجو مسك' => 'Mesk Mango',
            // عنب
            'عنب بناتي' => 'Banati Grapes',
            'عنب أحمر' => 'Red Grapes',
            'عنب أسود' => 'Black Grapes',
            'عنب فليم' => 'Flame Grapes',
            'عنب بدون بذر' => 'Seedless Grapes',
            // تفاح
            'تفاح أحمر' => 'Red Apples',
            'تفاح أخضر' => 'Green Apples',
            'تفاح جولدن' => 'Golden Apples',
            'تفاح بلدي' => 'Baladi Apples',
            // بلح وتمر
            'بلح زغلول' => 'Zaghloul Dates',
            'بلح سماني' => 'Samani Dates',
            'بلح أمهات' => 'Amhat Dates',
            'بلح برحي' => 'Barhi Dates',
            'تمر سيوي' => 'Siwi Dates',
            'تمر مجدول' => 'Medjool Dates',
            'عجوة' => 'Date Paste',
            // يوسفي · ليمون
            'يوسفي بلدي' => 'Baladi Mandarin',
            'يوسفي أفندي' => 'Efendi Mandarin',
            'ليمون بلدي' => 'Baladi Lime',
            'ليمون أضاليا' => 'Adalia Lemon',
            'ليمون أصفر' => 'Yellow Lemon',
            // جوافة · رمان · موز · بطيخ
            'جوافة بلدي' => 'Baladi Guava',
            'جوافة عسلي' => 'Honey Guava',
            'رمان بناتي' => 'Banati Pomegranate',
            'رمان أسواني' => 'Aswani Pomegranate',
            'رمان مانفلوطي' => 'Manfalouti Pomegranate',
            'موز بلدي' => 'Baladi Bananas',
            'موز مستورد' => 'Imported Bananas',
            'بطيخ بلدي' => 'Baladi Watermelon',
            'بطيخ بدون بذر' => 'Seedless Watermelon',
            // …and the fruits the list simply did not have
            'أناناس' => 'Pineapple',
            'أفوكادو' => 'Avocado',
            'كيوي' => 'Kiwi',
            'بابايا' => 'Papaya',
            'جوز الهند' => 'Coconut',
            'توت' => 'Mulberry',
            'برقوق' => 'Plums',
            'كرز' => 'Cherries',
            'نكتارين' => 'Nectarine',
            'تين شوكي' => 'Prickly Pear',
            'قصب سكر' => 'Sugar Cane',
            'بشملة' => 'Loquat',
            'سفرجل' => 'Quince',
            'فاكهة القشطة' => 'Custard Apple',
        ],

        /*
        |----------------------------------------------------------------------
        | الخضروات — «هناك الكثير من الخضراوات غير موجودة» ‹ owner
        |----------------------------------------------------------------------
        | The herbs are the biggest hole: «أعشاب وورقيات» was ONE row for
        | بقدونس and كزبرة and شبت and نعناع, which are four bunches at four
        | prices. The umbrella stays for the shop that sells them as one bundle.
        */
        'الخضروات' => [
            'بقدونس' => 'Parsley',
            'كزبرة خضراء' => 'Fresh Coriander',
            'شبت' => 'Dill',
            'نعناع' => 'Fresh Mint',
            'ريحان' => 'Basil',
            'جرجير' => 'Rocket',
            'كرفس' => 'Celery',
            'كراث' => 'Leek',
            'بصل أخضر' => 'Spring Onion',
            'فجل' => 'Radish',
            'سلق' => 'Chard',
            'مشروم' => 'Mushrooms',
            'فول أخضر' => 'Green Fava Beans',
            'قلقاس' => 'Taro',
            'هليون' => 'Asparagus',
            'زنجبيل طازج' => 'Fresh Ginger',
            'ورق عنب' => 'Vine Leaves',
            'طماطم شيري' => 'Cherry Tomatoes',
        ],

        /*
        |----------------------------------------------------------------------
        | مستلزمات المنزل #404 — borrowed for «أدوات منزلية», not cloned
        |----------------------------------------------------------------------
        | Seven rows written for «صيني ومستلزمات بيت»: أواني طهي، أدوات مائدة،
        | أطقم تقديم، تخزين وحفظ، مفارش سفرة، أدوات مطبخ، أدوات تنظيف منزلية.
        | Every one of them is a heading a grocer prices under too.
        |
        | The five below are what a china shop does not stock and a grocery
        | does — the errand end of the same shelf. Adding a row to a group is
        | not granting it: the china shop keeps exactly the seven links it has,
        | because rows and links are two different tables.
        */
        'مستلزمات المنزل' => [
            'مكانس وممسحات' => 'Brooms & Mops',
            'سلال مهملات' => 'Waste Bins',
            'شماعات ملابس' => 'Clothes Hangers',
            'حبال غسيل ومشابك' => 'Washing Lines & Pegs',
            'فويل وورق زبدة' => 'Foil & Baking Paper',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Who gets them
    |--------------------------------------------------------------------------
    | «ونضيف المجموعات إلى السوبر ماركت والهايبر والميني ماركت» — and to the two
    | dry grocers beside them, because they carried #285 too and retiring it
    | without giving them the replacement would leave them holding nothing.
    |
    | Every one of the thirteen is a SHELF-STABLE range, which is exactly what a
    | «مواد غذائية» shop is; not one of them needs a fridge. The fresh lists
    | (اللحوم، الأسماك، الألبان، الفواكه، الخضروات) stay with the markets, which
    | is the distinction between a mini-market and a dry grocer.
    */
    'links' => [

        'markets' => [
            'children' => [149, 185, 272],   // هايبر · مني · سوبر
            'groups' => [
                'أنواع المكرونة', 'أنواع الزيوت والسمن', 'أنواع السكر والمحليات',
                'أنواع البهارات والتوابل', 'أنواع المعلبات', 'أنواع المخللات والخل',
                'أنواع الصلصات والشوربات', 'أنواع العسل والمربى', 'أنواع الشاي والقهوة',
                'أنواع المكسرات والتسالي', 'أنواع الحلويات المعبأة',
                'أنواع أغذية الأطفال', 'أنواع المشروبات المعبأة',
                // re-stated so the rows added above reach them on this run
                'الفواكه', 'الخضروات', 'أنواع الحبوب والغلال',
            ],
        ],

        'dry_grocers' => [
            'children' => [109, 110],        // مواد غذائية · مواد غذائية ومنظفات
            'groups' => [
                'أنواع المكرونة', 'أنواع الزيوت والسمن', 'أنواع السكر والمحليات',
                'أنواع البهارات والتوابل', 'أنواع المعلبات', 'أنواع المخللات والخل',
                'أنواع الصلصات والشوربات', 'أنواع العسل والمربى', 'أنواع الشاي والقهوة',
                'أنواع المكسرات والتسالي', 'أنواع الحلويات المعبأة',
                'أنواع أغذية الأطفال', 'أنواع المشروبات المعبأة',
                'أنواع الحبوب والغلال',
            ],
        ],

        // The greengrocer himself — the new varieties and the missing veg.
        'greengrocer' => [
            'children' => [114],             // خضار وفاكهة
            'groups' => ['الفواكه', 'الخضروات'],
        ],

        /*
        |----------------------------------------------------------------------
        | «نظّف البقالة الجافة والمشروبات» — المالك، 2026-08-24
        |----------------------------------------------------------------------
        | Granted BEFORE the two groups below are retired, and that order is the
        | whole of it: «بن» #63 had exactly ONE priced list on the platform —
        | «أقسام البقالة الجافة», two rows — so switching it off first would
        | leave a coffee merchant with nothing he can put a price on.
        |
        | It is the same hole this taxonomy keeps digging, and the grocery split
        | wrote the rule while filling it: «ruling on what a shop does not sell
        | without giving it a word for what it does is how a child ends up
        | mute». «بن وشاي» was that word in 2026-08-10 — a single aisle row,
        | because there was no list. Now there is one, and it names the shelf:
        | شاي فتلة، بن محوج، بن سادة، كاكاو.
        |
        | And the nuts with it: «سناكس وتسالي» was his other row, and a محمصة
        | roasts the لب and the سوداني on the same fire it roasts the beans.
        */
        'coffee_merchant' => [
            'children' => [63],              // بن
            'groups' => ['أنواع الشاي والقهوة', 'أنواع المكسرات والتسالي'],
        ],

        /*
        |----------------------------------------------------------------------
        | The non-food half — «نظّف أقسام المنزل والعناية»
        |----------------------------------------------------------------------
        | ⚠ «منظفات» #83 is the «بن» of this pass: a real trade under THREE
        | roots whose only priced list on the platform was «أقسام المنزل
        | والعناية». It takes all six replacements, and it takes them BEFORE the
        | switch-off — grant first, then revoke.
        |
        | Its links are written SHARED (`category_id = 0`), which also retires
        | the two `mirror_links` entries that used to copy the old group into
        | شركات and مصانع root by root: a shared row already covers every root
        | the child stands under.
        */
        'household_and_care' => [
            'children' => [83, 149, 185, 272],   // منظفات · هايبر · مني · سوبر
            'groups' => [
                'أنواع المنظفات', 'أصناف العناية الشخصية', 'مستلزمات الأطفال',
                'مستلزمات الحيوانات الأليفة', 'أنواع الفحم والوقود المنزلي',
                'مستلزمات المنزل',
            ],
        ],

        /*
        | «مواد غذائية ومنظفات» #110 carried THREE of the six — منظفات، عناية
        | شخصية، فحم — and not the nappies, the pet food or the pots.
        |
        | Who gets what is READ and not decided, the same rule the fresh-counter
        | file states: its own link data is the answer, and widening it here
        | would be forty rows the owner has to take off by hand.
        */
        'grocer_with_detergents' => [
            'children' => [110],
            'groups' => [
                'أنواع المنظفات', 'أصناف العناية الشخصية', 'أنواع الفحم والوقود المنزلي',
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | The last two drawers — granted before they are switched off
        |----------------------------------------------------------------------
        | «مجمدات» #113 held five of the seven fresh counters and could name a
        | fresh cut, a fresh fish and a fresh cheese — every variety list it
        | carries is the UNFROZEN version. «أنواع المجمدات» is its actual trade,
        | and «أنواع الدواجن والطيور» comes with it: it carried «لحوم ودواجن»
        | and had the meat half only.
        */
        'frozen_shop' => [
            'children' => [113],             // مجمدات
            'groups' => ['أنواع المجمدات', 'أنواع الدواجن والطيور'],
        ],

        /*
        | «هايبر ماركت» #149 is the one market that still carried «مجمدات» — the
        | owner withdrew it from سوبر and مني by hand on 2026-08-24 16:53. Read,
        | not decided: he is not handed a freezer he took off two shops earlier.
        */
        'hypermarket_freezer' => [
            'children' => [149],
            'groups' => ['أنواع المجمدات'],
        ],

        /*
        | «مخابز» #27 carried «حلويات وشوكولاتة» — it sells the بسبوسة and the
        | بيتي فور beside the bread — and «أصناف الحلويات والجاتوه» is that word
        | with eighteen things a price hangs on. «حلويات» #210 has it already.
        */
        'bakery_sweets' => [
            'children' => [27],
            'groups' => ['أصناف الحلويات والجاتوه'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Switched off, not deleted
    |--------------------------------------------------------------------------
    | Each keeps its rows inside it: they are the record of what the shelves
    | were called before each one became a list.
    |
    | ── The two the owner named next, «نظّف البقالة الجافة والمشروبات» ────────
    |
    | «أقسام البقالة الجافة» (7) and «أقسام المشروبات» (2) came out of the same
    | 27-row aisle list in August, and every word in both is now a list:
    |
    |     بن وشاي              →  أنواع الشاي والقهوة
    |     بهارات               →  أنواع البهارات والتوابل
    |     زيوت وسمن            →  أنواع الزيوت والسمن
    |     معلبات               →  أنواع المعلبات
    |     سناكس وتسالي         →  أنواع المكسرات والتسالي
    |     مكرونات وأرز وحبوب   →  أنواع المكرونة + أنواع الحبوب والغلال
    |     مواد غذائية          →  the thirteen, which is what the word meant
    |     عصائر · مشروبات      →  أنواع المشروبات المعبأة
    |
    | Nine rows, and on the day this ran: zero merchant ticks, zero prices, zero
    | offerings. Five of the nine already reached no child at all — the owner
    | had withdrawn them by hand months before, which is the same ruling arrived
    | at from the other side.
    |
    | ── And the third, «نظّف أقسام المنزل والعناية» ──────────────────────────
    |
    | It was held back on the first pass, and correctly: منظفات and فحم are not
    | food and nothing written then replaced them. Now they are written, so the
    | last of the five aisle drawers goes with the other two:
    |
    |     منظفات                     →  أنواع المنظفات
    |     عناية شخصية                →  أصناف العناية الشخصية
    |     منتجات أطفال               →  مستلزمات الأطفال
    |     مستلزمات حيوانات أليفة     →  مستلزمات الحيوانات الأليفة
    |     فحم                        →  أنواع الفحم والوقود المنزلي
    |     أدوات منزلية               →  مستلزمات المنزل  (borrowed, extended)
    |
    | Zero ticks, zero prices, zero offerings on all six.
    |
    | ── And the last two, «نظّف أقسام الطازج واللحوم وبنود المخبوزات» ────────
    |
    | I argued for keeping these: a counter is weighed and a bakery is baked,
    | so both are work somebody does rather than a shelf. The owner overruled
    | it, and the eleven rows agree with him — ten were already sayable in a
    | list that can be priced, and the eleventh («مجمدات») is written above.
    |
    | ⚠ With these two, ALL FIVE drawers that «أقسام السوبر ماركت» split into on
    | 2026-08-10 are switched off, and the empty parent with them. That split
    | was right for what it had: twenty-seven words, five carrier sets, one
    | grab-bag. It was still a list of PLACES, and a place is not a price.
    |
    | ── The two that were already dead, «أوقف المجموعتين الميتتين» ───────────
    |
    | Found by auditing what the six retirements left behind: «مستلزمات
    | المزارع» (مستلزمات زراعية · ماشية وطيور · معدات ومستلزمات) and «صفوف
    | معروضة» (مركبة معروضة · وحدة معروضة · قطعة أثاث) — six rows between them
    | and **zero links**. They reach no child at all and have not for weeks.
    |
    | They are the same kind of word as the aisles: «وحدة معروضة» is not a thing
    | anyone buys, it is a PLACE a thing is put. Every child that ever answered
    | them has since been given its own trade list — الآلات والمعدات الزراعية،
    | أنواع الثروة الحيوانية والسمكية، ماركات الموتوسيكلات، الكرفانات، أنظمة
    | المصاعد — which is why the links drained away on their own.
    |
    | Ten children still NAME them in data/menu_line_bands.php and every one of
    | those ten already carries a real line list, so `hasOtherLineGroup()` has
    | been skipping them. The names come out of that map anyway: a map that
    | declares a retired row is one refactor away from granting it.
    */
    'retire' => [
        'أصناف المنتجات الغذائية',
        'أقسام البقالة الجافة',
        'أقسام المشروبات',
        'أقسام المنزل والعناية',
        'أقسام الطازج واللحوم',
        'بنود المخبوزات والحلويات',
        'مستلزمات المزارع',
        'صفوف معروضة',
    ],
];
