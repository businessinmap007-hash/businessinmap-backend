<?php

/*
 * The menu's headings, frozen.
 *
 * These were mirrored from the platform's menu item types once, on
 * 2026-08-04, and are now the source of truth in their own right — the
 * item types have since collapsed to five coarse kinds (منيو، ماركت،
 * موبليات، سيارات، عقارات) which say WHICH SURFACE a child sells on, not
 * what it sells. Deriving these from the types again would wipe all
 * 44 bands and leave five.
 *
 * Shape: band name_ar => name_en, then child name_ar => [band names].
 * Consumed by \Database\Seeders\MenuLineOptionsSeeder.
 */

return [

    'bands' => [
        'مقبلات'               => 'Appetizers',
        'سلطات'                 => 'Salads',
        'شوربة'                 => 'Soups',
        'مشويات'               => 'Grills',
        'أطباق رئيسية'    => 'Main Dishes',
        'ساندوتشات'         => 'Sandwiches',
        'بيتزا'                 => 'Pizza',
        'مكرونة / باستا'  => 'Pasta',
        'مأكولات بحرية'  => 'Seafood',
        'إفطار'                 => 'Breakfast',
        'حلويات'               => 'Desserts',
        'مشروبات ساخنة'  => 'Hot Drinks',
        'مشروبات باردة'  => 'Cold Drinks',
        'وجبات أطفال'      => 'Kids Meals',
        // «مجمع مطاعم يحتاج فروع اخرى لانه مكان به مجموعة من المطاعم» — owner,
        // 2026-08-16. A food court is a row of counters, and it already carried
        // thirteen of the fourteen bands; «كريب» was the one nobody had.
        'كريب'                   => 'Crêpes',
        // «اضف فطائر كبند فى بنود المنيو» — owner, 2026-08-16. It sat down here
        // among the aisles because the first split sent it out with them; it is
        // a menu section, and it is listed with the menu sections now. The row
        // itself is unchanged — see `kept` in menu_band_split.php for the move
        // and why there is still only one «فطائر» on the platform.
        'فطائر'                 => 'Pies',
        'مستلزمات زراعية'  => 'Agricultural Supplies',
        'ماشية وطيور'        => 'Livestock & Poultry',
        'معدات ومستلزمات'  => 'Equipment & Supplies',
        'وحدة معروضة'      => 'Property Listing',
        'مركبة معروضة'    => 'Vehicle Listing',
        'قطعة أثاث'          => 'Furniture Piece',
        'فحم'                     => 'Coal',
        'خضار وفاكهة'      => 'Fresh Produce',
        'مواد غذائية'      => 'Foodstuffs',
        'سلطة فواكة'        => 'Fruit Salad',
        'آيس كريم'            => 'Ice Cream',
        'عصائر'                 => 'Juices',
        'ألبان وبيض'        => 'Dairy & Eggs',
        'فسيخ'                   => 'Salted fish',
        'رنجة'                   => 'Smoked fish',
        'بهارات'               => 'Spices',
        'أجبان'                 => 'Cheese',
        'وافل'                   => 'Waffle',
        'مخبوزات'             => 'Bakery',
        'لحوم ودواجن'      => 'Meat & Poultry',
        'مجمدات'               => 'Frozen Food',
        'مكرونات وأرز وحبوب' => 'Pasta, Rice & Grains',
        'معلبات'               => 'Canned Food',
        'زيوت وسمن'          => 'Oils & Ghee',
        'سناكس وتسالي'    => 'Snacks',
        'حلويات وشوكولاتة' => 'Sweets & Chocolate',
        'مشروبات'             => 'Beverages',
        'منظفات'               => 'Cleaning Supplies',
        'عناية شخصية'      => 'Personal Care',
        'منتجات أطفال'    => 'Baby Products',
        'مستلزمات حيوانات أليفة' => 'Pet Supplies',
        'أدوات منزلية'    => 'Household Items',
    ],

    'children' => [
        // The fourteen goods children that had no selling service at all until
        // 2026-08-09 — see UnsellableChildrenSeeder. Menu, not retail: the
        // central catalogue holds no seed, feed or fertiliser, so these trades
        // write their own list.
        'معدات زراعية' => ['مستلزمات زراعية', 'معدات ومستلزمات'],
        // «تقاوي وأسمدة ومبيدات» after the 2026-08-12 merge, and its line is
        // «مستلزمات المحاصيل» now — a real list, not the grab-bag row.
        'تقاوي وأسمدة ومبيدات' => ['مستلزمات زراعية'],
        'أعلاف' => ['مستلزمات زراعية', 'ماشية وطيور'],
        'حبوب وغلال' => ['مكرونات وأرز وحبوب', 'مواد غذائية'],
        'مواشي وأرانب' => ['ماشية وطيور'],
        // «مأكولات بحرية» withdrawn 2026-08-10: a fish farm sells fish, it does
        // not cook a seafood dish. It reaches «أسماك ومأكولات بحرية طازجة» in
        // «أقسام الطازج واللحوم» now — granted by GroceryAisleSplitSeeder, not
        // from here, because this map only owns the restaurant bands. Leaving
        // the name here would have had this seeder hand the menu row straight
        // back on its next run, which is how every one of these moves fails.
        'مزارع سمكية' => ['ماشية وطيور'],
        // Three children became one on 2026-08-12; the difference between them
        // was which animal, which is a row and not a child.
        'معدات وتجهيزات المزارع' => ['معدات ومستلزمات', 'ماشية وطيور'],
        'كرڤان' => ['معدات ومستلزمات'],
        'معدات سوبرماركت' => ['معدات ومستلزمات'],
        'مصاعد وسلم كهرياء' => ['معدات ومستلزمات'],

        'أسماك' => [
            'خضار وفاكهة', 'سلطة فواكة', 'ألبان وبيض',
            'فسيخ', 'رنجة', 'أجبان', 'لحوم ودواجن',
            'مجمدات',
        ],
        // Its counter is the one word, and «أنواع اللحوم» is what hangs on it.
        'جزارة' => ['لحوم ودواجن'],
        'أكل بيتى' => [
            'مقبلات', 'سلطات', 'شوربة', 'مشويات',
            'أطباق رئيسية', 'ساندوتشات', 'بيتزا', 'مكرونة / باستا',
            'مأكولات بحرية', 'إفطار', 'حلويات', 'مشروبات ساخنة',
            'مشروبات باردة', 'وجبات أطفال',
        ],
        /*
        | «بن يبيع حبوب فقط» — owner, 2026-08-10. It is a SHOP, so the two menu
        | bands go: «مشروبات ساخنة» is a cup a kitchen serves, not a bag a
        | grocer sells. The two aisle rows «عصائر» and «مشروبات» go with them,
        | because the ruling is about the drinks and «فقط» answers it — a shop
        | that sells beans does not also stock juice.
        |
        | What it sells now has a word of its own, «بن وشاي», granted from
        | grocery_aisle_split.php. Deliberately NOT listed here: this map must
        | not manage it, or the day someone edits this line the trade loses the
        | only row that names it.
        */
        'بن' => [
            'مواد غذائية', 'بهارات', 'مكرونات وأرز وحبوب',
            'معلبات', 'زيوت وسمن', 'سناكس وتسالي',
        ],
        'حلويات' => [
            'ساندوتشات', 'آيس كريم', 'فطائر', 'وافل',
            'مخبوزات', 'حلويات وشوكولاتة',
        ],
        'دواجن' => [
            'خضار وفاكهة', 'سلطة فواكة', 'ألبان وبيض',
            'فسيخ', 'رنجة', 'أجبان', 'لحوم ودواجن',
            'مجمدات',
        ],
        /*
        | «البقالة تحتفظ بالمعبأ فقط» — owner, 2026-08-10, closing the last of
        | the shop-versus-kitchen questions.
        |
        | «فطائر» and «وافل» are the two of the bakery counter's five that are
        | only ever made fresh, so they leave all three markets and stay with
        | «مخابز» and «حلويات», which were ruled kitchens. «مخبوزات»، «آيس كريم»
        | and «حلويات وشوكولاتة» stay: a grocer stocks the wrapped loaf, the tub
        | and the bar, and that is the packaged version he keeps.
        |
        | The three deli bands also go from this entry. They were already off
        | «سوبر ماركت» in the database — he unticked them by hand and the
        | withdrawal record has been holding the line ever since — but the map
        | still declared them, so it disagreed with «مني ماركت» and «هايبر
        | ماركت» which were corrected explicitly. All three now read the same.
        */
        'سوبر ماركت' => [
            'فحم', 'خضار وفاكهة', 'مواد غذائية', 'سلطة فواكة',
            'آيس كريم', 'عصائر', 'ألبان وبيض',
            'فسيخ', 'رنجة', 'بهارات', 'أجبان',
            'مخبوزات', 'لحوم ودواجن', 'مجمدات',
            'مكرونات وأرز وحبوب', 'معلبات', 'زيوت وسمن', 'سناكس وتسالي',
            'حلويات وشوكولاتة', 'مشروبات', 'منظفات', 'عناية شخصية',
            'منتجات أطفال', 'مستلزمات حيوانات أليفة', 'أدوات منزلية',
        ],
        /*
        | A cart, not a kitchen with fourteen sections, and the owner has said so
        | twice from opposite directions.
        |
        | On 2026-08-10 he withdrew مقبلات، سلطات، شوربة، أطباق رئيسية and
        | حلويات — the sit-down half. On 2026-08-16 18:17 he ticked back the
        | eight it does serve out of a window, «فطائر» and «كريب» among them.
        | Between the two saves the list below is his, written down: eleven
        | bands, and the map now says what the screen says.
        |
        | The five withdrawals are NOT restored by listing them here — the
        | ledger blocks a re-grant either way — so this entry is deliberately
        | the eleven and not the fourteen it inherited from «مطعم».
        */
        'عربية قهوة ومأكولات' => [
            'مشويات', 'ساندوتشات', 'بيتزا', 'مكرونة / باستا',
            'مأكولات بحرية', 'إفطار', 'مشروبات ساخنة',
            'مشروبات باردة', 'وجبات أطفال', 'فطائر', 'كريب',
        ],
        /*
        | «عصائر مطبخ» — owner, same ruling, the other way. A juice bar PREPARES
        | what it sells, so it keeps the menu bands and gives up the two aisle
        | rows: «عصائر» as an aisle is a fridge of bottles, and that is a
        | different trade from a man with a blender.
        |
        | Note the shape of the pair — بن and عصائر sat on both lists and the
        | answer went opposite ways. That is why this was reported rather than
        | guessed: nothing in the data distinguishes a shop from a kitchen.
        */
        'عصائر' => [
            'مشروبات ساخنة', 'مشروبات باردة',
        ],
        'خضار وفاكهة' => [
            'خضار وفاكهة', 'سلطة فواكة', 'ألبان وبيض',
            'فسيخ', 'رنجة', 'أجبان', 'لحوم ودواجن',
            'مجمدات',
        ],
        /*
        | Four bands until 2026-08-17, and «عربية قهوة ومأكولات» — a CART — had
        | eleven. The café was outsold by the pavement outside it.
        |
        | Unlike «عصائر» above, nothing ruled it: the decisions ledger holds no
        | menu row for #64 at all, only the payment and shipping withdrawals of
        | 2026-08-10, so the four were what a seeder left rather than a ruling.
        | An Egyptian café sells a sandwich, a crêpe, a fiteer and a plate of
        | chips all day long.
        |
        | It stops there, and that is the whole point of the entry. مشويات،
        | أطباق رئيسية، مأكولات بحرية، مكرونة are the grill, and the child that
        | runs a grill AND a coffee machine is «مطعم وكافيه», standing right
        | next to it. Handing #64 the restaurant list is how two children
        | become one word again — the same reason «عصائر» keeps a blender and
        | not a fridge.
        */
        'كافيه' => [
            'مقبلات', 'ساندوتشات', 'إفطار', 'حلويات',
            'مشروبات ساخنة', 'مشروبات باردة', 'فطائر', 'كريب',
        ],
        'مجمدات' => [
            'خضار وفاكهة', 'سلطة فواكة', 'ألبان وبيض',
            'فسيخ', 'رنجة', 'أجبان', 'لحوم ودواجن',
            'مجمدات',
        ],
        /*
        | A food court is not one kitchen — it is a row of them, so it takes
        | every band there is. «مأكولات بحرية»، «مشويات» and «ساندوتشات» it
        | already had; «كريب» and «فطائر» complete the five the owner named on
        | 2026-08-16.
        |
        | «فطائر» is the SAME option the bakery counter uses — the one that moved
        | back into «بنود المنيو» rather than a second row beside it. The
        | alternative was lending the court the whole bakery counter and then
        | narrowing it back to two rows, which put the borrow seeder and the
        | scope seeder in a loop over one child, each undoing the other every
        | run. Moving one option's group ends that: it is a menu band now, so no
        | borrowing is needed and no narrowing has to be undone.
        */
        'مجمع مطاعم' => [
            'مقبلات', 'سلطات', 'شوربة', 'مشويات',
            'أطباق رئيسية', 'ساندوتشات', 'بيتزا', 'مكرونة / باستا',
            'مأكولات بحرية', 'إفطار', 'حلويات', 'مشروبات ساخنة',
            'مشروبات باردة', 'وجبات أطفال', 'كريب', 'فطائر',
        ],
        'مخابز' => [
            'ساندوتشات', 'آيس كريم', 'فطائر', 'وافل',
            'مخبوزات', 'حلويات وشوكولاتة',
        ],
        'مطعم' => [
            'مقبلات', 'سلطات', 'شوربة', 'مشويات',
            'أطباق رئيسية', 'ساندوتشات', 'بيتزا', 'مكرونة / باستا',
            'مأكولات بحرية', 'إفطار', 'حلويات', 'مشروبات ساخنة',
            'مشروبات باردة', 'وجبات أطفال',
        ],
        'مطعم وكافيه' => [
            'مقبلات', 'سلطات', 'شوربة', 'مشويات',
            'أطباق رئيسية', 'ساندوتشات', 'بيتزا', 'مكرونة / باستا',
            'مأكولات بحرية', 'إفطار', 'حلويات', 'مشروبات ساخنة',
            'مشروبات باردة', 'وجبات أطفال',
        ],
        'معرض موتوسيكلات' => [
            'مركبة معروضة',
        ],
        'منظفات' => [
            'فحم', 'منظفات', 'عناية شخصية', 'منتجات أطفال',
            'مستلزمات حيوانات أليفة', 'أدوات منزلية',
        ],
        /*
        | «المني والهايبر بقالة مش مطاعم» — owner, 2026-08-10.
        |
        | The three general markets are one trade at three sizes and they had
        | stopped agreeing: he took «ساندوتشات» and the two drink bands off
        | «سوبر ماركت» by hand and the other two kept them, so the platform held
        | two answers to whether a grocer runs a deli counter. All three say the
        | same thing now.
        |
        | They do not go silent on drinks — «عصائر» and «مشروبات» are still
        | theirs as AISLES, three lines down. What goes is the claim to serve a
        | cup, which is the same distinction «بن» and «عصائر» were ruled on:
        | a shop stocks, a kitchen prepares.
        */
        'مني ماركت' => [
            'فحم', 'خضار وفاكهة', 'مواد غذائية', 'سلطة فواكة',
            'آيس كريم', 'عصائر', 'ألبان وبيض', 
            'فسيخ', 'رنجة', 'بهارات', 'أجبان',
            'مخبوزات', 'لحوم ودواجن', 'مجمدات',
            'مكرونات وأرز وحبوب', 'معلبات', 'زيوت وسمن', 'سناكس وتسالي',
            'حلويات وشوكولاتة', 'مشروبات', 'منظفات', 'عناية شخصية',
            'منتجات أطفال', 'مستلزمات حيوانات أليفة', 'أدوات منزلية',
        ],
        // Same ruling, same trade. See «مني ماركت» above.
        'هايبر ماركت' => [
            'فحم', 'خضار وفاكهة', 'مواد غذائية', 'سلطة فواكة',
            'آيس كريم', 'عصائر', 'ألبان وبيض', 
            'فسيخ', 'رنجة', 'بهارات', 'أجبان',
            'مخبوزات', 'لحوم ودواجن', 'مجمدات',
            'مكرونات وأرز وحبوب', 'معلبات', 'زيوت وسمن', 'سناكس وتسالي',
            'حلويات وشوكولاتة', 'مشروبات', 'منظفات', 'عناية شخصية',
            'منتجات أطفال', 'مستلزمات حيوانات أليفة', 'أدوات منزلية',
        ],
    ],
];
