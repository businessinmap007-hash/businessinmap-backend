<?php

/*
|--------------------------------------------------------------------------
| One «اكسسوار» child, and what kind of accessory as an option
|--------------------------------------------------------------------------
| Owner, 2026-08-10:
|
|   «فى محلات او اونلاين هناك اكتر من ابن اكسسوار يمكن جمعهم جميعا تحت اكسسوار
|    وتحتها (موبايل - سيارات - كمبيوتر - الخ) من الخيارات. ااى تخصصات تحت اب
|    واحد يمكن دمجها بهذا الشكل.»
|
| Four rows said «accessories» in four places: #8 اكسسوار (9 accounts, three
| roots), #38 اكسسوارت سيارات (1), #117 اكسسوار موبيلات (1 — and its name_en
| still reads «Furniture Accessories», which is how long it has been unread),
| and #186 موبيلات و اكسسوار (17).
|
| #8 keeps the trade. #38 and #117 fold into it and become two of the options.
|
| #186 IS NOT FOLDED, and that is the whole judgement in this file: it sells the
| PHONE, not only the accessory, and seventeen merchants stand on it. Folding it
| into «اكسسوار» would quietly demote every one of them from a phone shop to an
| accessory stand. It gets the vocabulary instead, so it can say which
| accessories it carries beside the phones.
*/

return [

    'keeper' => [
        'name_ar' => 'اكسسوار',
        // The trade sells from المحلات too, and #8 did not stand there — which
        // is why «اكسسوارت سيارات» had to exist under المحلات to receive a
        // merchant at all. Wiring copied from #186, the shop already standing.
        'gain_roots' => [
            'shops-online' => 'موبيلات و اكسسوار',
        ],
    ],

    /*
    | `modifier`, like «ماركات السيارات» and «أنواع الأجهزة الكهربائية»: it says
    | what the shop STOCKS. Nobody buys the phrase «اكسسوار موبايل» — the priced
    | rows are the products in the catalog.
    */
    'group' => [
        'name_ar' => 'أنواع الإكسسوارات',
        'name_en' => 'Accessory Types',
        'price_role' => 'modifier',
        'options' => [
            'اكسسوار موبايل' => 'Mobile Accessories',
            'اكسسوار سيارات' => 'Car Accessories Range',
            'اكسسوار كمبيوتر' => 'Computer Accessories',
            'اكسسوار منزل' => 'Home Accessories',
            'ساعات' => 'Watches',
            'نظارات وإكسسوار' => 'Eyewear Accessories',
            'حقائب وشنط' => 'Bags & Cases',
            'مجوهرات وإكسسوار' => 'Jewellery Accessories',
            'إكسسوار شعر وتجميل' => 'Hair & Beauty Accessories',
            'إكسسوار أطفال' => 'Kids Accessories',
            'إكسسوار رياضي' => 'Sports Accessories',
            'شواحن وكابلات' => 'Chargers & Cables',
            'سماعات' => 'Headphones',
            'أغطية وحافظات' => 'Covers & Protectors',
        ],
    ],

    /*
    | Folded: the child leaves every root, its merchants move to #8 under the
    | root they were standing in, and the option below is ticked for them FIRST
    | so nobody arrives unable to say what he sells.
    */
    'folds' => [
        'اكسسوارت سيارات' => 'اكسسوار سيارات',
        'اكسسوار موبيلات' => 'اكسسوار موبايل',
    ],

    /*
    | Given the vocabulary, kept as its own child.
    */
    'carry_only' => [
        'موبيلات و اكسسوار',
    ],
];
