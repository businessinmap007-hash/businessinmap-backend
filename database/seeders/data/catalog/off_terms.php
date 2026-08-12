<?php

/**
 * The English→Arabic vocabulary used to name an imported product in Arabic.
 *
 * Open Food Facts carries an Arabic name on barely one row in seven, and half
 * of those are a latin transliteration typed into the Arabic field. The owner
 * chose «أترجم أنا الاسم» — so the translation is mine, and this file is it:
 * every word the importer will ever write in Arabic is listed here where it
 * can be read and corrected. Nothing is translated that is not in this file,
 * and a name with one unknown word is not written at all — «جبنة feta» is
 * worse than no row.
 *
 * **Two lists, because Arabic and English disagree about order.** English puts
 * the modifier first («Greek Yoghurt»), Arabic puts the noun first («زبادي
 * يوناني»). So the head noun is picked out of the name and the attributes
 * follow it, rather than the words being translated where they stand.
 *
 * A word that is BOTH — «cheese» in «Cheese Sandwich» and in «Cream Cheese» —
 * belongs in `nouns`; it is used as an attribute only when another noun is
 * present.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Two words that mean one thing
    |--------------------------------------------------------------------------
    |
    | Checked before the single words, and the pair is consumed together.
    | Without this, «full cream milk» came out «لبن كامل الدسم كريمة» — the
    | «cream» of «full cream» translated a second time as an ingredient.
    */
    'noun_phrases' => [
        'ice cream' => 'آيس كريم',
        'corn flakes' => 'كورن فليكس',
        'peanut butter' => 'زبدة فول سوداني',
        'olive oil' => 'زيت زيتون',
        'tomato paste' => 'صلصة طماطم',
        'chewing gum' => 'لبان',
        'body lotion' => 'لوشن للجسم',
        'shower gel' => 'جل استحمام',
    ],

    'attribute_phrases' => [
        'full cream' => 'كامل الدسم',
        'full fat' => 'كامل الدسم',
        'low fat' => 'قليل الدسم',
        'fat free' => 'خالي الدسم',
        'sugar free' => 'بدون سكر',
        'extra virgin' => 'بكر ممتاز',
        'long life' => 'طويل الأمد',
        // «fresh» alone is «طازج», which is right for food and wrong on a tube
        // of toothpaste — so the toothpaste phrase is spelled out separately.
        'extra fresh' => 'إكسترا فريش',
    ],

    /*
    |--------------------------------------------------------------------------
    | Head nouns — what the thing IS
    |--------------------------------------------------------------------------
    */
    'nouns' => [
        // dairy
        'milk' => 'لبن',
        'milks' => 'لبن',
        'cheese' => 'جبنة',
        'yogurt' => 'زبادي',
        'yoghurt' => 'زبادي',
        'labneh' => 'لبنة',
        'cream' => 'كريمة',
        'butter' => 'زبدة',
        'ghee' => 'سمن',
        'margarine' => 'مارجرين',

        // drinks
        'water' => 'مياه',
        'juice' => 'عصير',
        'nectar' => 'عصير',
        'cola' => 'كولا',
        'soda' => 'صودا',
        'drink' => 'مشروب',
        'beverage' => 'مشروب',
        'coffee' => 'قهوة',
        'tea' => 'شاي',

        // bakery, snacks, sweets
        'chocolate' => 'شوكولاتة',
        'biscuit' => 'بسكويت',
        'biscuits' => 'بسكويت',
        'cookies' => 'كوكيز',
        'wafer' => 'ويفر',
        'wafers' => 'ويفر',
        'crackers' => 'كراكرز',
        'chips' => 'شيبس',
        'crisps' => 'شيبس',
        'cake' => 'كيك',
        'cakes' => 'كيك',
        'bread' => 'خبز',
        'toast' => 'توست',
        'croissant' => 'كرواسون',
        'candy' => 'حلوى',
        'gum' => 'لبان',
        'halva' => 'حلاوة طحينية',
        'halawa' => 'حلاوة طحينية',
        'tahini' => 'طحينة',
        'molasses' => 'عسل أسود',

        // staples
        'oil' => 'زيت',
        'rice' => 'أرز',
        'pasta' => 'مكرونة',
        'macaroni' => 'مكرونة',
        'spaghetti' => 'اسباجتي',
        'penne' => 'بيني',
        'fusilli' => 'فوسيلي',
        'noodles' => 'نودلز',
        'oats' => 'شوفان',
        'oat' => 'شوفان',
        'cornflakes' => 'كورن فليكس',
        'flakes' => 'رقائق',
        'cereal' => 'حبوب إفطار',
        'honey' => 'عسل',
        'jam' => 'مربى',
        'sugar' => 'سكر',
        'salt' => 'ملح',
        'pepper' => 'فلفل',
        'spices' => 'بهارات',
        'vinegar' => 'خل',
        'flour' => 'دقيق',

        // tins and sauces
        'tuna' => 'تونة',
        'sardines' => 'سردين',
        'ketchup' => 'كاتشب',
        'mayonnaise' => 'مايونيز',
        'mayo' => 'مايونيز',
        'mustard' => 'خردل',
        'sauce' => 'صلصة',
        'puree' => 'معجون',
        'paste' => 'معجون',
        'beans' => 'فول',
        'lentils' => 'عدس',
        'chickpeas' => 'حمص',
        'nuts' => 'مكسرات',
        'dates' => 'تمر',

        // household & care
        'shampoo' => 'شامبو',
        'conditioner' => 'بلسم',
        'soap' => 'صابون',
        'deodorant' => 'مزيل عرق',
        'toothpaste' => 'معجون أسنان',
        'lotion' => 'لوشن',
        'detergent' => 'منظف',
        'tissues' => 'مناديل',
        'diapers' => 'حفاضات',
    ],

    /*
    |--------------------------------------------------------------------------
    | Attributes — what KIND, what flavour, what state
    |--------------------------------------------------------------------------
    */
    'attributes' => [
        // fat and diet
        'full' => 'كامل الدسم',
        'whole' => 'كامل الدسم',
        'skimmed' => 'خالي الدسم',
        'skim' => 'خالي الدسم',
        'light' => 'لايت',
        'diet' => 'دايت',
        'zero' => 'زيرو',
        'protein' => 'بروتين',
        'vegan' => 'نباتي',
        'organic' => 'عضوي',

        // grade
        'natural' => 'طبيعي',
        'plain' => 'سادة',
        'original' => 'أصلي',
        'classic' => 'كلاسيك',
        'premium' => 'بريميوم',
        'extra' => 'إكسترا',
        'super' => 'سوبر',
        'gold' => 'جولد',
        'golden' => 'جولد',
        'excellence' => 'إكسيلانس',
        'fresh' => 'طازج',
        'frozen' => 'مجمد',
        'dried' => 'مجفف',
        'canned' => 'معلب',
        'instant' => 'سريع التحضير',
        'ground' => 'مطحون',
        'roasted' => 'محمص',
        'creamy' => 'كريمي',
        'crunchy' => 'كرانشي',
        'digestive' => 'دايجستف',

        // colour
        'white' => 'أبيض',
        'brown' => 'بني',
        'black' => 'أسود',
        'dark' => 'دارك',
        'red' => 'أحمر',
        'green' => 'أخضر',

        // taste and flavour
        'sweet' => 'حلو',
        'sweetened' => 'محلى',
        'salted' => 'مملح',
        'spicy' => 'حار',
        'hot' => 'حار',
        'sour' => 'حامض',
        'mixed' => 'مشكل',
        'greek' => 'يوناني',
        'turkish' => 'تركي',
        'egyptian' => 'مصري',
        'vanilla' => 'فانيليا',
        'caramel' => 'كراميل',
        'cocoa' => 'كاكاو',
        'choco' => 'شوكو',
        'coconut' => 'جوز الهند',
        'hazelnut' => 'بندق',
        'almond' => 'لوز',
        'peanut' => 'فول سوداني',
        'sesame' => 'سمسم',
        'strawberry' => 'فراولة',
        'mango' => 'مانجو',
        'orange' => 'برتقال',
        'apple' => 'تفاح',
        'banana' => 'موز',
        'guava' => 'جوافة',
        'pineapple' => 'أناناس',
        'lemon' => 'ليمون',
        'peach' => 'خوخ',
        'apricot' => 'مشمش',
        'grape' => 'عنب',
        'pomegranate' => 'رمان',
        'berries' => 'توت',
        'fruit' => 'فواكه',
        'tomato' => 'طماطم',
        'chili' => 'شطة',
        'garlic' => 'ثوم',
        'onion' => 'بصل',
        'potato' => 'بطاطس',
        'corn' => 'ذرة',
        'wheat' => 'قمح',
        'durum' => 'ديورم',
        'olive' => 'زيتون',
        'sunflower' => 'دوار الشمس',
        'vegetable' => 'نباتي',
        'feta' => 'فيتا',
        'cheddar' => 'شيدر',
        'mozzarella' => 'موتزاريلا',
        'cheese' => 'جبنة',
        'chocolate' => 'شوكولاتة',
        'milk' => 'لبن',
        'milky' => 'بالحليب',
        'coffee' => 'قهوة',
        'honey' => 'عسل',

        // who it is for
        'baby' => 'أطفال',
        'kids' => 'أطفال',
        'men' => 'رجالي',
        'women' => 'حريمي',
        'body' => 'للجسم',
        'hair' => 'للشعر',
        'face' => 'للوجه',
        'cleansing' => 'منظف',
    ],
];
