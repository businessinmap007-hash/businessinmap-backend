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

    'groups' => [
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
