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
    | ⚠ «أقسام المنزل والعناية» is NOT here. منظفات، عناية شخصية، فحم are not
    | food, nothing above replaces them, and retiring a list because it sits
    | beside one that was replaced is how a shop loses a shelf it still keeps.
    */
    'retire' => [
        'أصناف المنتجات الغذائية',
        'أقسام البقالة الجافة',
        'أقسام المشروبات',
    ],
];
