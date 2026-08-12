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

        'وحدة البيع' => [
            'name_en' => 'Selling Unit', 'price_role' => 'modifier', 'children' => [14, 107, 128], // «أسمدة» folded into #14 on 2026-08-12
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
