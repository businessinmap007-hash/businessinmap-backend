<?php

/*
|--------------------------------------------------------------------------
| «عقارات وأراضي» — two property types nobody could list, and the axis a
| listing is actually searched on
|--------------------------------------------------------------------------
| Owner, 2026-08-16: «اضافة الى عقارات واراضى مخازن - جمالون» and «مساحات
| للشقق والاراضى والشركات الخ تتناسب مع فروع مجموعة عقارات وممتلكات حتى يكون
| العرض سهل وايضا البحث يكون محدد».
|
| The root is three children — مكتب عقاري (23 merchants), مالك عقار, مطور
| عقاري — and all three carry the same list, which is correct: a broker, an
| owner and a developer list the same kinds of property. So both additions are
| made once and reach all three.
|
| ── The two types ─────────────────────────────────────────────────────────
|
| «عقارات وممتلكات» had thirteen rows and neither of the two an industrial
| listing needs. «مخازن» is the commonest commercial listing in Egypt after
| the shop, and «جمالون» — a steel-truss shed — is how a light factory, a
| workshop or a storage yard is actually advertised. «مصنع» and «ورشة» were
| carrying both by implication, which is why a search for a warehouse returned
| factories.
|
| ── The area, and why ONE ladder and not three ────────────────────────────
|
| «تتناسب مع فروع مجموعة عقارات وممتلكات» is the requirement, and the way to
| meet it is not a band list per property type — the platform has no
| conditional options, so three groups would mean the customer filtering
| «100–150» must first know which of the three to open, and a merchant with a
| flat and a warehouse answers two axes for one question.
|
| One ladder meets it instead, by being FINE where the small properties live
| and COARSE where the large ones do. A flat is answered in the first five
| rows and never sees the last three; a warehouse or a factory starts where
| the flat stops; land runs off the top end. Every branch of the group finds
| its own resolution in one axis, which is what makes the search precise —
| eleven rows, one question, and no row that fits nothing.
|
| `modifier`, and not a line. What is bought is the PROPERTY — «شقة»، «مخازن»
| — and the area is what changes its price: شقة × 100–150 م² is a price and
| «100–150 م²» on its own is not a thing anybody buys. It joins «مستوى
| التشطيب» and «نوع التعامل», which qualify the same line the same way.
|
| ── What is NOT here ──────────────────────────────────────────────────────
|
| The فدان. Egyptian farmland is quoted in أفدنة (1 فدان ≈ 4200 م²), so
| «أرض زراعية» and «مزرعة» land in «أكثر من 10000 م²» and stop resolving —
| two feddans and twenty read the same. Mixing a second unit into this ladder
| would make «5000–10000 م²» and «فدان – 5 أفدنة» overlap, so the ladder stays
| in one unit and the farm axis is left open rather than half-built. It is a
| second group when the owner wants it.
|
| @see \Database\Seeders\ChildTradeVocabulariesSeeder
*/

return [

    'root' => 'property-and-land',

    'name_en_suffix' => 'Property',

    'groups' => [

        'المساحة' => [
            'name_en' => 'Area', 'price_role' => 'modifier', 'children' => [517, 518, 522],
            'options' => [
                'أقل من 50 م²' => 'Under 50 m²',
                '50 – 100 م²' => '50–100 m²',
                '100 – 150 م²' => '100–150 m²',
                '150 – 200 م²' => '150–200 m²',
                '200 – 300 م²' => '200–300 m²',
                '300 – 500 م²' => '300–500 m²',
                '500 – 1000 م²' => '500–1000 m²',
                '1000 – 2000 م²' => '1000–2000 m²',
                '2000 – 5000 م²' => '2000–5000 m²',
                '5000 – 10000 م²' => '5000–10000 m²',
                'أكثر من 10000 م²' => 'Over 10000 m²',
            ],
        ],
    ],

    /*
    | Added to the group that already exists rather than given one of their
    | own: they answer the same question its thirteen rows answer, and a
    | fourteenth and fifteenth row is what that means.
    */
    'extend' => [
        'عقارات وممتلكات' => [
            'مخازن' => 'Warehouses',
            'جمالون' => 'Steel-Frame Shed',
        ],
    ],

    /*
    | `extend` creates the rows and links nothing — the group belongs to
    | option_group_splits.php, not to this file, so the links are named here
    | and the three children take both.
    */
    'links' => [
        517 => ['عقارات وممتلكات' => ['مخازن', 'جمالون']],
        518 => ['عقارات وممتلكات' => ['مخازن', 'جمالون']],
        522 => ['عقارات وممتلكات' => ['مخازن', 'جمالون']],
    ],
];
