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
         * One list for the three equipment traders, narrowed per child: a
         * milking parlour is not a rabbit hutch. The narrowing is in
         * `child_option_scopes.php`, so the group stays one list.
         */
        'معدات وتجهيزات المزارع' => [
            'name_en' => 'Livestock Farm Equipment', 'price_role' => 'line', 'children' => [171, 230, 235],
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
         * file: cattle to #170, rabbits to #236, fish to #102. One group
         * because it is one question — «what do you raise».
         */
        'أنواع الثروة الحيوانية والسمكية' => [
            'name_en' => 'Livestock & Fish Stock', 'price_role' => 'line', 'children' => [170, 236, 102],
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

        'وحدة البيع' => [
            'name_en' => 'Selling Unit', 'price_role' => 'modifier', 'children' => [14, 99, 107, 128],
            'options' => [
                'بالكيلو' => 'Per Kilo',
                'بالشيكارة' => 'Per Sack',
                'بالطن' => 'Per Tonne',
                'بالأردب' => 'Per Ardeb',
                'بالعبوة' => 'Per Pack',
            ],
        ],
    ],
];
