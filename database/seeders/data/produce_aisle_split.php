<?php

/*
|--------------------------------------------------------------------------
| «فواكة تحتها كل الفواكة» — owner, 2026-08-24
|--------------------------------------------------------------------------
| «أصناف الخضار والفاكهة» (#557, line, 45 options) is the whole trade of
| «خضار وفاكهة» in one flat list: مانجو and فراولة and عنب sit beside طماطم and
| بطاطس and بصل with nothing between them.
|
| That was fine while the list was only a picker — a merchant scrolls and taps.
| It stops being fine the moment the list becomes the ARRANGEMENT of a menu
| (see App\Services\Menu\MenuOutline): the group is the section, so a greengrocer
| got one section of forty-five bands, and the owner's own example —
| «فاكهة تحتها كل الفواكه» — had no section to be under.
|
| Same promise as MenuBandSplitSeeder and GroceryAisleSplitSeeder, which this
| follows exactly: only `options.group_id` moves. No option is created, none is
| deleted, and NO `category_child_option` row is touched — a shop that carried
| forty-five words still carries forty-five, under two titles instead of one.
|
| Nothing is priced on any of them (`business_service_prices.line_option_id`
| finds no rows for the group) and «خضار وفاكهة» has no businesses on it yet, so
| no merchant's screen changes under him.
|
|--------------------------------------------------------------------------
| Where the line falls, and the one row worth arguing about
|--------------------------------------------------------------------------
| Eighteen fruits and twenty-seven vegetables, and the split is the one an
| Egyptian greengrocer already makes with his own stall — not a botanist's.
| So طماطم is a vegetable here (it is bought to cook with), and بطيخ and شمام
| are fruit (they are bought to eat).
|
| **ليمون** is the row to look at twice. It is a citrus and it is filed with
| البرتقال and اليوسفي below — but it is bought the way an onion is bought, by
| the kilo, to cook with. Move it if the stall says otherwise; it is one line.
|
| Nothing else is close: خرشوف and ذرة and قرع عسلي are vegetables however they
| are cooked, and بلح وتمر and تين are fruit however they are dried.
*/

return [

    /*
    | The group being taken apart. All 45 leave, so it is left standing and
    | EMPTY rather than deleted — nothing in this taxonomy is deleted, and an
    | empty group is the clearest record of where the two below came from. It
    | stays named in option_price_roles.php for the same reason.
    */
    'source_group' => 'أصناف الخضار والفاكهة',

    /*
    | Old name => new name, for groups this file has renamed since it first
    | ran. The seeder finds a group BY NAME and creates it when missing, so a
    | key renamed without an entry here leaves the old group standing, full,
    | beside a new empty one.
    */
    'renames' => [],

    'groups' => [

        'الفواكه' => [
            'name_en' => 'Fruit',
            'reorder' => 5570,
            'options' => [
                'مانجو',
                'فراولة',
                'عنب',
                'برتقال',
                'يوسفي',
                'ليمون',          // ← the debatable one; see the note above
                'موز',
                'تفاح',
                'جوافة',
                'رمان',
                'بلح وتمر',
                'تين',
                'خوخ',
                'مشمش',
                'بطيخ',
                'شمام وكانتلوب',
                'كمثرى',
                'جريب فروت',
            ],
        ],

        'الخضروات' => [
            'name_en' => 'Vegetables',
            'reorder' => 5571,
            'options' => [
                'طماطم',
                'بطاطس',
                'بصل',
                'ثوم',
                'خيار',
                'فلفل ألوان',
                'فلفل حار',
                'باذنجان',
                'كوسة',
                'جزر',
                'بامية',
                'فاصوليا خضراء',
                'بازلاء',
                'لوبيا',
                'ملوخية',
                'سبانخ',
                'خس',
                'كرنب',
                'قرنبيط',
                'بروكلي',
                'بنجر',
                'لفت',            // ← «لفت وفجل» when it moved; split 2026-08-24
                'بطاطا',
                'قرع عسلي',
                'خرشوف',
                'ذرة',
                'أعشاب وورقيات',
            ],
        ],
    ],
];
