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

/*
| Named once: the rows added to «عقارات وممتلكات» after this file was first
| written, which all three children take. Repeating them in three link entries
| is how one of the three quietly ends up narrower than its siblings.
*/
$added = [
    'مخازن', 'جمالون',
    'دوبلكس', 'بنتهاوس', 'وحدة إدارية', 'وحدة تجارية',
    'مول تجاري', 'كمبوند', 'شاليه', 'شقة مصيفية',
];

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

        /*
        | ── «حالة العقار» ─────────────────────────────────────────────────
        | «الخيارات المتاحة تكون تم التشطيب وتحت الإنشاء» — owner, 2026-08-23.
        |
        | Not «مستوى التشطيب», which is already here and answers a different
        | question: «على المحارة» describes a FINISHED building handed over
        | bare. A unit «تحت الإنشاء» has no finish level yet — it has a
        | delivery date — and the two were being read as one axis, which is
        | how a buyer filtering for «تشطيب كامل» loses every off-plan unit
        | that will be handed over fully finished in 2028.
        |
        | Two rows, because two is what he named and because a third («على
        | الماكيت», «تحت التشطيب») is a shade of one of them that only a
        | developer can tell apart from the other.
        |
        | `modifier`: the property is what is bought; this changes its price.
        */
        'حالة العقار' => [
            'name_en' => 'Property Status', 'price_role' => 'modifier', 'children' => [517, 518, 522],
            'options' => [
                'جاهز للتسليم' => 'Ready to Move',
                'تحت الإنشاء' => 'Under Construction',
            ],
        ],

        /*
        | ── «مدة التقسيط» ─────────────────────────────────────────────────
        | «وخيارات الدفع كاش وتقسيط ٣ و٥ و٧ و١٠ سنوات وتقسيط بدون فوائد».
        |
        | «كاش» and «تقسيط» are already answered — group #50 «الدفع والسداد»,
        | which every trade on the platform can answer and all three of these
        | children carry. What has no home is HOW LONG, and it is the single
        | loudest number on any Egyptian property hoarding.
        |
        | A group of its own rather than five more rows in «الدفع والسداد»:
        | that group is shared by hundreds of children and «تقسيط ٧ سنوات» on
        | a grocer is nonsense. Scoped to the three property children, it is
        | the second half of a sentence whose first half is already there.
        |
        | «تقسيط بدون فوائد» is NOT minted here. It exists — option #204 — and
        | policy since 2026-08-10 is that it is hand-set only, because
        | interest-free instalments are a commercial claim only the merchant
        | can make. The `links` section grants it to these three on the
        | owner's instruction, which is what «hand-set» means when the hand is
        | his.
        */
        'مدة التقسيط' => [
            'name_en' => 'Instalment Term', 'price_role' => 'modifier', 'children' => [517, 518, 522],
            'options' => [
                'تقسيط سنة' => '1 Year',
                'تقسيط 3 سنوات' => '3 Years',
                'تقسيط 5 سنوات' => '5 Years',
                'تقسيط 7 سنوات' => '7 Years',
                'تقسيط 10 سنوات' => '10 Years',
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

            /*
            | ── 2026-08-23 ────────────────────────────────────────────────
            | «اضافة العقارات والاراضي: وحدات سكنية وادارية وتجارية والمولات
            |  التجارية والمدن الجديدة والمشروعات» — owner.
            |
            | The fifteen rows covered the OLD market: a flat, a villa, a
            | shop, an office, a plot. They had no word for anything a
            | developer sells in a new city, which is where most of the
            | primary market now is.
            |
            | «وحدة إدارية» is not «مكتب» and the difference is the deal: a
            | مكتب is a room you rent in a building that exists, an إدارية is
            | a unit sold off-plan in a tower, quoted per metre and delivered
            | on a date. Same for «وحدة تجارية» against «محل».
            |
            | «كمبوند» and «مول تجاري» are what «المشروعات» means when a
            | developer says it — the thing he advertises before any single
            | unit in it has a number.
            |
            | And the two the coastal market is made of, which the list did
            | not carry at all: a chalet and a summer flat. For SALE, here —
            | letting one by the night is «مالك وحدة مصيفية» under «سياحة
            | وفنادق», which is a stay and not a listing.
            */
            'دوبلكس' => 'Duplex',
            'بنتهاوس' => 'Penthouse',
            'وحدة إدارية' => 'Administrative Unit',
            'وحدة تجارية' => 'Commercial Unit',
            'مول تجاري' => 'Shopping Mall',
            'كمبوند' => 'Compound',
            'شاليه' => 'Chalet',
            'شقة مصيفية' => 'Summer Apartment',
        ],
    ],

    /*
    | `extend` creates the rows and links nothing — the group belongs to
    | option_group_splits.php, not to this file, so the links are named here
    | and the three children take both.
    */
    /*
    |--------------------------------------------------------------------------
    | 2026-08-17 — «راجع باقي أبناء عقارات و أراضي بنفس الطريقة»
    |--------------------------------------------------------------------------
    | Three children, thirteen ledger rows, and twelve of the thirteen are one
    | sweep: «نمط تقديم الخدمة» emptied off all three within forty-three seconds
    | on 2026-08-11. The thirteenth is «تبديل» pinned onto مكتب عقاري on
    | 2026-08-16. Both stand.
    |
    | **«مطور عقاري» #518 can name neither the rooms nor the finish.**
    |
    | Its two siblings carry «الغرف» (6) and «مستوى التشطيب» (6); it carries
    | zero of each, and the ledger holds no row for either — it was never
    | granted them. This file's own opening argument is that «a broker, an owner
    | and a developer list the same kinds of property», which is why every group
    | it writes goes to all three; the two OLDER groups are the ones that do not.
    |
    | And of the three it is the developer who is defined by the finish. A
    | مطور عقاري in Egypt is quoted by what he HANDS OVER — «يُسلَّم على
    | المحارة»، «نصف تشطيب»، «تشطيب كامل» is the headline on every hoarding, and
    | the one axis a buyer compares two compounds on. He could not say it.
    |
    | ── Why the links are written here and not left to the seeder ─────────────
    |
    | `PropertyModifierOptionsSeeder` created both groups and links them to
    | «children that already carry the property types» — a live query, not a
    | list, so #518 would be picked up automatically. Two things stop that.
    |
    | It is in NO seeder list. Nothing runs it; the only mention of its name
    | anywhere outside itself is a comment in FurnitureStyleOptionsSeeder. Like
    | ChildOptionScopeSeeder, it self-heals in a chain it is not part of.
    |
    | And its query has drifted since it was written. «شقق فندقية» #537 and
    | «منتجع» #538 each hold ONE row of «عقارات وممتلكات» — شقة، and ڤيلا for
    | the resort — as accommodation types, which is correct and deliberate. That
    | single row now pulls both into the query, so running the seeder today
    | hands a resort «على المحارة» and «نصف تشطيب» and puts it into «الغرف»,
    | whose contents are governed for hotels by HotelRoomKindOptionsSeeder. Two
    | seeders on one group is the fight this taxonomy keeps producing. The
    | seeder's child list is narrowed to the property ROOT in the same change —
    | the exception is a root, not a list of ids, exactly as PrepaymentScopeSeeder
    | puts it.
    |
    | ── «الغرف» is named row by row, never 'all' ──────────────────────────────
    |
    | The group holds twenty-eight options: the six room counts a listing uses
    | AND the twenty-two hotel kinds — جناح رئاسي، شاليه، كابينة ديلوكس، سرير في
    | غرفة مشتركة. `'all'` would hand a property developer a royal suite. The
    | six are the six the siblings hold.
    |
    | ── What is NOT copied across ─────────────────────────────────────────────
    |
    | «تبديل». مكتب عقاري holds all three of «نوع التعامل» and the other two hold
    | two; the third is a PIN the owner placed by hand on 2026-08-16, an hour
    | before he built the area ladders. A pin on one child is a statement about
    | that child, and swapping a flat for a flat is a different trade from
    | building one. Left exactly as he set it.
    */
    'links' => [
        /*
        | «تقسيط بدون فوائد» is NOT granted here, and the difference matters.
        |
        | Policy since 2026-08-10: the option is hand-set only, and
        | PaymentTermsScopeTest enforces it by demanding that every child
        | holding it be PINNED in the ledger or ticked by a merchant. A link
        | written by a vocabulary file is neither, and the decisions seeder —
        | which runs last — would take it straight back off.
        |
        | The owner asked for it on 2026-08-23, so it is a pin, in
        | child_option_pins.php. That is the same sentence said in the place
        | the platform reads it.
        */
        517 => ['عقارات وممتلكات' => $added],
        522 => ['عقارات وممتلكات' => $added],

        518 => [
            'عقارات وممتلكات' => $added,
            'الغرف' => ['استوديو', 'غرفة', 'غرفتين', 'ثلاث غرف', 'أربع غرف', 'خمس غرف فأكثر'],
            'مستوى التشطيب' => 'all',
        ],
    ],
];
