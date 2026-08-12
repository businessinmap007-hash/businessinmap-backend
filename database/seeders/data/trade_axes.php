<?php

/*
|--------------------------------------------------------------------------
| One trade, several doors — and the same vocabulary behind every door
|--------------------------------------------------------------------------
| Owner, 2026-08-09:
|
|   «قطع غيار سيارات: هناك مصنع يصنع هذه القطع وهناك شركة تستورد او توزع
|    وهناك محل يبيع، فيجب ان يكون لكل واحد منهم قائمة بماركات السيارات التى
|    لديه قطع غيار لها وايضا اذا كانت تخص الميكانيكا او الكهرباء الخ.
|    وكذلك اجهزة رياضية مصنع وشركة ومحل وهكذا.»
|
| So the duplicate rows the last sweep flagged were NOT a mistake — a factory,
| a distributor and a shop are three different businesses and belong under
| three different roots. What WAS a mistake is that they were three separate
| child rows carrying three different vocabularies:
|
|   #44 قطع غيار سيارات (المحلات) — 43 car brands
|   #43 قطع غيار سيارات (مصانع)   — none
|   #7  أجهزة رياضية   (المحلات)  — no equipment axis
|   #24 أجهزة رياضية   (شركات، معارض) — no equipment axis
|
| A factory that makes BMW parts could not say «BMW». And nobody at all could
| say whether the parts are mechanical or electrical, because no such axis
| existed on the platform.
|
| The platform already has the right shape for this and uses it elsewhere:
| #38 «اكسسوارت سيارات» is ONE child row attached to both مصانع and المحلات.
| One row, several roots, one vocabulary — and each business narrows it with
| its own ticks. That is what this applies to the two trades named above.
*/

return [

    /*
    | The axis the owner asked for and the platform did not have. `modifier`,
    | like «ماركات السيارات» beside it: it narrows what a merchant carries, it
    | is not itself a priced row — the priced rows are the parts in the catalog.
    */
    'new_groups' => [
        'نوع قطع الغيار' => [
            'name_en' => 'Spare Part Domain',
            'price_role' => 'modifier',
            /*
            | `options.name_en` is UNIQUE across the WHOLE table, so every name
            | here is qualified. It is not padding: «ميكانيكا» and «كهرباء»
            | already exist as `line` options in «تخصصات الهندسة», where they
            | mean an engineering office's priced specialty. Same word, other
            | trade — and reusing those rows would have priced a spare part as
            | an engineering service.
            */
            'options' => [
                'ميكانيكا' => 'Mechanical Parts',
                'كهرباء' => 'Electrical Parts',
                'فرامل' => 'Brake Parts',
                'تعليق ومساعدين' => 'Suspension Parts',
                'نقل حركة / فتيس' => 'Transmission Parts',
                'تبريد وتكييف' => 'Cooling & A/C Parts',
                'هيكل وصاج' => 'Body Panels',
                'زجاج سيارات' => 'Auto Glass',
                'إطارات وجنوط' => 'Tyres & Rims',
                'بطاريات' => 'Batteries',
                'زيوت وفلاتر' => 'Oils & Filters',
                'إضاءة' => 'Auto Lighting',
                'عوادم' => 'Exhaust Parts',
                'مقصورة وفرش داخلي' => 'Interior Parts',
            ],
        ],
    ],

    /*
    | Each trade: the child row that KEEPS the trade (the one already carrying
    | the fullest vocabulary), every root it must be reachable from, the axes it
    | must offer, and the redundant twins whose roots it takes over.
    |
    | `donor_root` on a twin is where its service wiring is copied from, so the
    | trade arrives under a new root offering exactly what it offered there.
    */
    'trades' => [

        'قطع غيار سيارات' => [
            'keep_child_id' => 44,
            // مصانع + شركات + المحلات — the owner's three, in his order.
            'roots' => [23, 22, 17],
            'axis_groups' => ['ماركات السيارات', 'نوع قطع الغيار'],
            // #43 is the empty مصانع copy; it holds no account.
            'retire_children' => [43],
        ],

        'أجهزة رياضية' => [
            'keep_child_id' => 24,
            // معارض and شركات it already has; المحلات and مصانع it needs.
            'roots' => [23, 22, 21, 17],
            // No equipment axis exists yet. «الأنشطة الرياضية» is NOT it — that
            // group is `line`, i.e. the priced session a gym sells, and it is
            // scoped to facilities (ملاعب، نادي، أكاديمية، حمام سباحة، جيم).
            // Naming what a sports SHOP stocks is a separate decision and is
            // left to the owner rather than invented here.
            'axis_groups' => [],
            'retire_children' => [7],
        ],

        /*
        | «سواء مصانع او شركات او محلات او ورش» — owner, 2026-08-10.
        |
        | The trade stood under three of the four he named (مصانع، ورش، مهن) and
        | under NEITHER شركات nor المحلات, so a doors-and-windows company or shop
        | had nowhere to register. What stood under شركات instead was «أبواب
        | مصفحة» #23 — a PRODUCT, filed as a trade. It is now one of the sixteen
        | types in «أنواع الأبواب والشبابيك», so the trade itself takes the root.
        |
        | Nothing is retired here. #23 holds no account and #289 «بي في سي» holds
        | three, and folding either into «باب وشباك» is the owner's call, not a
        | side effect of wiring a root — so both keep their rows AND get the new
        | vocabulary. (He made that call for both: #23 on 2026-08-10 and #289 on
        | 2026-08-12, in `child_root_detachments.php`. This file still runs
        | first and still needs them wired — a merchant cannot be rehomed onto a
        | vocabulary that was never written.) They serve only as WIRING donors:
        | the intersection fallback
        | would have handed a company delivery + offers and no retail, i.e. a
        | shopfront that can sell nothing.
        */
        'باب وشباك' => [
            'keep_child_id' => 50,
            // مصانع، شركات، المحلات — the three the trade SELLS from, and now
            // the only three.
            //
            // Root 10 ورش was listed here for a few hours on 2026-08-10 and the
            // owner took it off the same day: «حذف … باب وشباك من ابناء الورش».
            // The workshop form of the trade has its own child, «نجار باب وشباك»
            // #84. Left in this list, the detachment would have been undone on
            // this seeder's very next run — which is exactly what
            // DoorWindowTradeTest's idempotency case caught.
            //
            // Root 6 مهن وحرفيين went the same way later that day, and the
            // comment above is why it had survived: «nobody asked for it to
            // leave» is not a reason to stand somewhere. That root holds
            // twenty-eight one-man crafts — نقاش، سباك، كهربائي، مبلط — and
            // every one of them stands under it ALONE, because a craft is not
            // also a factory. This trade's other three standings all carry
            // retail. It was a goods seller in a root of trades that are booked,
            // with zero accounts, while «نجار باب وشباك» was the craftsman all
            // along. Second time this list has had to give a root back.
            'roots' => [23, 22, 17],
            'axis_groups' => ['أنواع الأبواب والشبابيك'],
            'retire_children' => [],
            // «ألمونتال» #17 is NOT listed as a child of this axis. It sells
            // the extrusion, not the window — see its own entry below.
            // #23 أبواب مصفحة stands under شركات, #51 مستلزمات نجارة under
            // المحلات; both sell (delivery + offers + retail) exactly as a
            // doors business would.
            'donor_children' => [23, 51],
        ],

        /*
        | «واضف نفس الابن الى المصانع» — owner, 2026-08-12.
        |
        | «ألمونتال» #17 stood under شركات، معارض، المحلات and not under مصانع,
        | which is the one root an extrusion trade most obviously belongs to:
        | somebody presses the profile before anybody wholesales it. Its three
        | standings all carry retail, so the new one must too, or a merchant
        | registering there gets a shopfront that can sell nothing — the same
        | trap the intersection fallback set for «باب وشباك» above.
        |
        | «باب وشباك» #50 is the donor: it is the nearest trade that already
        | sells from مصانع, with retail + delivery + offers active there.
        |
        | The two trades keep their own words. #17's axis is «قطاعات ومنتجات
        | الألومنيوم» (the extrusion), #50's is «أنواع الأبواب والشبابيك» (the
        | finished opening), and `child_option_scopes.php` holds #17 off the
        | second.
        */
        'ألمونتال' => [
            'keep_child_id' => 17,
            'roots' => [23, 22, 21, 17],
            'axis_groups' => ['قطاعات ومنتجات الألومنيوم'],
            'retire_children' => [],
            'donor_children' => [50],
        ],
    ],
];
