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
| ── The farm, and why it is a SECOND ladder ───────────────────────────────
|
| «اضف مجموعة ثانية للمساحة بالفدان للأراضي الزراعية والمزارع» — owner,
| 2026-08-16, and the reason it is a second group and not eight more rows on
| the first is the unit. A فدان is ≈4200 م², so «5000 – 10000 م²» and «فدان –
| 3 أفدنة» describe overlapping ground: dropped into one ladder they would give
| a search two rows covering the same plot, which is the opposite of «محدد».
| Two ladders in two units, each internally clean, and the merchant answers the
| one his trade quotes in — a farm is advertised in أفدنة and never in metres,
| a flat the reverse.
|
| Both groups sit on all three children because all three list both kinds:
| «عقارات وممتلكات» holds «أرض زراعية» and «مزرعة» beside «شقة». The platform
| has no conditional options, so nothing can offer the metres to a flat and the
| feddans to a farm — what it can do is give each unit a clean ladder and let
| the listing decide, which is what a merchant does anyway.
|
| Sub-feddan land is quoted in قراريط (1/24 فدان ≈ 175 م²), and «أقل من فدان»
| is the row that carries them: a ladder of twenty-four قيراط steps is a
| precision no listing is written with.
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

        'المساحة بالفدان' => [
            'name_en' => 'Area in Feddans', 'price_role' => 'modifier', 'children' => [517, 518, 522],
            'options' => [
                'أقل من فدان (قراريط)' => 'Under 1 Feddan',
                'فدان – 3 أفدنة' => '1–3 Feddans',
                '3 – 5 أفدنة' => '3–5 Feddans',
                '5 – 10 أفدنة' => '5–10 Feddans',
                '10 – 20 فدان' => '10–20 Feddans',
                '20 – 50 فدان' => '20–50 Feddans',
                '50 – 100 فدان' => '50–100 Feddans',
                'أكثر من 100 فدان' => 'Over 100 Feddans',
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
