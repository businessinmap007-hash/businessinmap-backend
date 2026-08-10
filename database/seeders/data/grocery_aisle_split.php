<?php

/*
|--------------------------------------------------------------------------
| «راجع التكرار والتشابه بينهم وأعد تقسيمهم» — owner, 2026-08-10
|--------------------------------------------------------------------------
| Four food vocabularies were named. Three exist:
|
|   «أصناف المنتجات الغذائية»  #285  modifier  20 options   8 children
|   «أقسام السوبر ماركت»       #316  line      27 options  14 children
|   «بنود المنيو»              #122  line      14 options  18 children
|
| The fourth, «أصناف الوجبات», does not. The nearest name is «نظام الوجبات»
| #65 — شامل الإفطار / إقامة كاملة / نصف إقامة — which is a HOTEL board basis
| carried by six hotels and nothing to do with food ranges. It is left alone.
|
| A fourth copy of the same vocabulary that was NOT named also exists: retail
| branch #47 «بقالة وسوبر ماركت» carries 22 item types — ألبان، أجبان، عصائر،
| شاي وقهوة، زيوت وسمن، أرز ومكرونة، معلبات، صلصات، منظفات، عناية شخصية،
| بسكويت وسناكس، شوكولاتة، بقوليات وحبوب، بهارات، إفطار، مجمدات… That copy is
| the CATALOG axis and is deliberately not merged here: an item type is the
| branch a product row hangs from, an option is what the merchant answers.
| Worth knowing that nothing has ever been priced on ANY of the three option
| groups — `business_service_prices.line_option_id` finds zero rows for all of
| them — so no merchant is disturbed by regrouping them.
|
|--------------------------------------------------------------------------
| What the split is, and why these lines and not others
|--------------------------------------------------------------------------
| «أقسام السوبر ماركت» is not one list. Its own link data draws five, and it
| draws them cleanly: every one of the 27 options is carried by EXACTLY one of
| five carrier sets, with the three general markets (سوبر / مني / هايبر) on top
| of all five because a supermarket really does carry every aisle.
|
| Nobody designed that. It fell out of which trades the seeders matched, which
| is the strongest evidence available that the five are real: a fishmonger, a
| bakery, a coffee merchant, a juice bar and a cleaning-supplies shop each
| answered a different part of the list and ignored the rest.
|
| Same rule as MenuBandSplitSeeder, and the same promise: only `options.group_id`
| moves. No child link is touched, so no merchant loses a heading — a supermarket
| that had 27 still has 27, now under five titles instead of one, and a
| fishmonger sees only the counter he actually runs.
*/

return [

    /*
    | The group being taken apart. All 27 leave, so it is left standing and
    | EMPTY rather than deleted — nothing in this taxonomy is deleted, and an
    | empty group is the clearest possible record that the five below came out
    | of it. It stays declared in option_price_roles.php for the same reason.
    */
    'source_group' => 'أقسام السوبر ماركت',

    'groups' => [

        /*
        | Carried by: مجمدات، فواكة، دواجن، خضروات، أسماك + the three markets.
        | The fresh counters — everything weighed, chilled or frozen. «فسيخ» and
        | «رنجة» sit here rather than with the prepared food because in Egypt
        | they are a fishmonger's counter, and the carrier set agrees: they are
        | carried by exactly the children that carry «لحوم ودواجن».
        */
        'أقسام الطازج واللحوم' => [
            'name_en' => 'Fresh & Butchery Aisles',
            'reorder' => 60,
            'options' => [
                'خضار وفاكهة', 'ألبان وبيض', 'أجبان', 'لحوم ودواجن',
                'مجمدات', 'أسماك ومأكولات بحرية طازجة', 'فسيخ', 'رنجة', 'سلطة فواكة',
            ],
        ],

        /*
        | Carried by: مخابز، حلويات + the three markets. The counter that BAKES
        | — which is why «فطائر» and «وافل» are here and not in the dry grocery
        | with the packaged goods: they are made on the premises.
        */
        'أقسام المخبوزات والحلويات' => [
            'name_en' => 'Bakery & Confectionery Aisles',
            'reorder' => 61,
            'options' => [
                'مخبوزات', 'فطائر', 'وافل', 'حلويات وشوكولاتة', 'آيس كريم',
            ],
        ],

        /*
        | Carried by: بن، حبوب وغلال + the three markets. The shelf-stable
        | middle of the shop.
        */
        'أقسام البقالة الجافة' => [
            'name_en' => 'Dry Grocery Aisles',
            'reorder' => 62,
            'options' => [
                'مواد غذائية', 'مكرونات وأرز وحبوب', 'بهارات', 'معلبات',
                'زيوت وسمن', 'سناكس وتسالي',
            ],
        ],

        /*
        | Carried by: بن، عصائر + the three markets.
        */
        'أقسام المشروبات' => [
            'name_en' => 'Beverage Aisles',
            'reorder' => 63,
            'options' => ['عصائر', 'مشروبات'],
        ],

        /*
        | Carried by: منظفات + the three markets. The non-food half, and the
        | thing that actually distinguishes a supermarket from a grocer.
        | «فحم» is here because its carrier set is this one exactly.
        */
        'أقسام المنزل والعناية' => [
            'name_en' => 'Household & Personal Care Aisles',
            'reorder' => 64,
            'options' => [
                'منظفات', 'عناية شخصية', 'منتجات أطفال',
                'مستلزمات حيوانات أليفة', 'أدوات منزلية', 'فحم',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | One rename, and it is the only word this file changes
    |--------------------------------------------------------------------------
    | «أسماك ومأكولات بحرية طازجة» does not exist yet. The aisle list has no
    | seafood row of its own — the fishmongers reach «مأكولات بحرية» in «بنود
    | المنيو» instead, which is a RESTAURANT heading meaning a cooked dish.
    |
    | So مزارع سمكية and أسماك answer a menu question to say they sell fish.
    | Renaming the menu option would break it for the four restaurants that use
    | it properly, so a fresh aisle option is created and the fishmongers move
    | to it. The menu row keeps its restaurants and loses the shops.
    */
    'new_options' => [
        'أسماك ومأكولات بحرية طازجة' => [
            'name_en' => 'Fresh Fish & Seafood',
            'group' => 'أقسام الطازج واللحوم',
            // The children that get it, and lose «مأكولات بحرية» from the menu
            // group in exchange. Named, never derived: this is the only place
            // in the file where a child's links change at all.
            'move_from' => [
                'group' => 'بنود المنيو',
                'option' => 'مأكولات بحرية',
                'children' => [
                    'مجمدات', 'فواكة', 'دواجن', 'خضروات', 'أسماك',
                    'مزارع سمكية', 'سوبر ماركت', 'مني ماركت', 'هايبر ماركت',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | The duplicate that is a duplicate, not a split
    |--------------------------------------------------------------------------
    | «أصناف المنتجات الغذائية» #285 is the same vocabulary a third time —
    | «زيوت وسمن» and «معلبات» word for word, and thirteen of its twenty options
    | restate an aisle («حبوب وبقوليات» + «أرز» + «مكرونة» against «مكرونات وأرز
    | وحبوب», «ألبان وأجبان» against «ألبان وبيض» + «أجبان», and so on).
    |
    | It is not redundant everywhere. Three of its eight children carry no
    | market list at all — مواد غذائية، مواد غذائية ومنظفات، استيراد وتصدير —
    | and for a wholesaler or an importer «which ranges do you deal in» is a
    | real question with no priced heading behind it. That is what a `modifier`
    | is for and it keeps them.
    |
    | The five that carry BOTH are asked the same question twice, once priced
    | and once not. They keep the priced one.
    |
    | Written as declared empties in child_option_scopes.php rather than here,
    | because that file is the one place that says «this child answers none of
    | this group» and ChildOptionScopeSeeder already enforces it.
    */
    'redundant_for' => [
        'group' => 'أصناف المنتجات الغذائية',
        'children' => ['سوبر ماركت', 'مني ماركت', 'هايبر ماركت', 'مجمدات', 'حبوب وغلال'],
    ],
];
