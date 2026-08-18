<?php

/*
|--------------------------------------------------------------------------
| «شحن وتوصيل» — the two axes that actually move a freight price
|--------------------------------------------------------------------------
| Owner, 2026-08-11: «راجع الجذور اللى فيها معدلات ناقصة فعلا».
|
| «شركة» #68 (20 merchants) and «مكتب» #198 (14) carry «مركبات النقل والركاب»
| as their line — the lorry — and nothing that changes what a lorry costs. But
| the SAME lorry is two prices depending on how far it goes and how fast, and
| those are the first two questions any customer asks a carrier.
|
| Two groups rather than one, because they are two questions: a same-day
| delivery across the city and an ordinary consignment to Aswan are different
| answers on different axes, and a merchant may compete on one and not the
| other.
|
| ── «مندوب» #243, and the restraint that has now been lifted ──────────────
|
| It was deliberately kept out of both groups on 2026-08-11: 159 merchants,
| more than any child on the platform, and the owner had stripped all thirteen
| of its options by hand that morning. Handing it two NEW axes hours later
| would have been reading his intent rather than his instruction, and the
| seeder would have granted them happily because they were not in the
| withdrawal record — which is why the restraint had to live in this file.
|
| «راجع باقي أبناء شحن وتوصيل بنفس الطريقة» — owner, 2026-08-16, is that
| instruction. The gap it leaves is the largest on the platform by merchant
| count: its two siblings can say how far they go and how fast, and the child
| carrying 159 merchants could say neither. It had one descriptive group and a
| lone «فردي» — a modifier with no line under it, which is the shape this whole
| sweep has been closing.
|
| See `links` at the foot of the file for what it gets and what it does not,
| and note that none of it is a new word: all three groups exist and two of
| them were written for this root.
|
| ── «شحن بري وبحري وجوى» #166 joined this root on 2026-08-16 ──────────────
|
| «انقل شحن بري وبحري وجوى الى شحن وتوصيل». It had been filed under «شركات»
| beside the marketing and insurance firms while being the same trade as
| «شركة» #68, and it is named in all four groups above now — the two written
| for this root included. Its own name says «دولي» in three words, so it takes
| «نطاق الشحن» whole where «مندوب» takes three quarters of it.
|
| The move carried six tables with it; see `bim:move-child`.
*/

return [

    'root' => 'shipping-delivery',

    'name_en_suffix' => 'Freight',

    'groups' => [

        'نطاق الشحن' => [
            'name_en' => 'Shipping Range', 'price_role' => 'modifier', 'children' => [68, 198, 166],
            'options' => [
                'داخل المدينة' => 'Within the City',
                'بين المحافظات' => 'Intercity',
                'الصعيد والحدود' => 'Upper Egypt & Borders',
                'شحن دولي' => 'International',
            ],
        ],

        'سرعة الشحن' => [
            'name_en' => 'Delivery Speed', 'price_role' => 'modifier', 'children' => [68, 198, 166],
            'options' => [
                'عادي' => 'Standard',
                'سريع' => 'Express',
                'في نفس اليوم' => 'Same Day',
                'موعد محدد' => 'Scheduled Slot',
            ],
        ],

        /*
        | ── «شحن بري وبحري وجوى» could not say بري, بحري or جوي ──────────────
        |
        | Owner, 2026-08-16: «اضافة مجموعة خيارات تعبر عن شحن بري وبحري وجوى».
        |
        | #166 is named for three modes and carried a word for none of them. Its
        | only `line` was «مركبات النقل والركاب» — a lorry list — so a company
        | that moves containers by sea and pallets by air was selling trucks.
        | #68 and #198 both tick «شحن دولي» in «نطاق الشحن» and had the same
        | hole: how far and how fast, never by what.
        |
        | `line`, and that is the point of it. The mode IS the thing bought and
        | it is priced per mode — a container by sea, a kilo by air — so a
        | descriptive here would have left the gap exactly where it was. It
        | stands beside the lorry list, which is normal: 42 of the platform's
        | children carry more than one line.
        |
        | Three rows and no fourth. «سكك حديدية» and «نقل متعدد الوسائط» are
        | real freight modes and neither is in the child's name; the name is the
        | list, the way «تسليم أرض المصنع» is its own definition.
        */
        'وسيلة الشحن' => [
            'name_en' => 'Freight Mode', 'price_role' => 'line', 'children' => [68, 198, 166],
            'options' => [
                'شحن بري' => 'Land Freight',
                'شحن بحري' => 'Sea Freight',
                'شحن جوي' => 'Air Freight',
            ],
        ],

        /*
        | ── and what the load travels IN ─────────────────────────────────────
        |
        | «وايضا ما يستخدم فى البرى مثلا مقطورة - مبرد - مجمد الخ» — same
        | instruction, second half.
        |
        | «مقطورة» is deliberately NOT here, and neither are جامبو، ربع نقل or
        | معدات ثقيلة: all four already exist in «مركبات النقل والركاب», which
        | all three carriers hold. Restating them would be the duplication this
        | taxonomy keeps having to undo, and it would put the same word in two
        | groups where a merchant can tick one and not the other.
        |
        | What is genuinely missing is the other axis — not WHICH VEHICLE but
        | HOW THE LOAD IS CARRIED. «مبرد» and «مجمد» exist nowhere on the
        | platform, and a refrigerated lorry and a dry one are the same lorry at
        | two prices, which is the definition of a modifier.
        |
        | «سيارات نقل» #284 gets it too. It stands under «سيارات», hires out
        | exactly these lorries, and carries the same vehicle list; it is not an
        | international carrier, so it takes this and not the mode above.
        */
        'تجهيز الشحن البري' => [
            'name_en' => 'Land Freight Equipment', 'price_role' => 'modifier', 'children' => [68, 198, 166, 284],
            'options' => [
                'جاف / عادي' => 'Dry Cargo',
                'مبرد' => 'Refrigerated',
                'مجمد' => 'Frozen',
                'حاوية' => 'Container',
                'صهريج سوائل' => 'Liquid Tanker',
                'سطحة (فلات بد)' => 'Flatbed',
                'بضائع سائبة' => 'Bulk Cargo',
                'نقل ثقيل واستثنائي' => 'Heavy & Oversize',
                'بضائع خطرة' => 'Hazardous Goods',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 2026-08-17 — «راجع باقي أبناء شحن وتوصيل بنفس الطريقة», second pass
    |--------------------------------------------------------------------------
    | Four children, forty-five ledger rows, and every ruling in them holds up.
    | One thing is missing and it is not missing from this root — it is missing
    | from the platform.
    |
    | **Nobody can say «الدفع عند الاستلام».**
    |
    | The whole database holds exactly ONE word about WHEN the money changes
    | hands: «دفع مسبق» #292, and PrepaymentScopeSeeder exists solely to keep it
    | on this root because «paying before you receive is what a carrier asks
    | for». Its opposite — paying when you receive — has never existed. Not in
    | «الدفع والسداد», not in any group, and not in the app either: there is no
    | COD anywhere in the payment code, so this is not a second name for a thing
    | the runtime already models.
    |
    | An axis with one pole is half an axis, and this is the half that matters
    | here. «مندوب» #243 carries 159 merchants — more than any child on the
    | platform — and a مندوب in Egypt IS the man who hands you the parcel and
    | takes the money at the door. That is the trade. He could not name it.
    |
    | ── The withdrawal that looks like a refusal and is the opposite ──────────
    |
    | On 2026-08-11 the owner emptied «الدفع والسداد» across this root: #68 lost
    | كاش and تقسيط, #198 and #243 lost كاش، تقسيط AND دفع مسبق. Read quickly
    | that says «carriers are not asked about payment» and this file should stop.
    |
    | Read properly it says the reverse. كاش and تقسيط are SHOP words — the
    | question a counter is asked — and he took them off all four. He then KEPT
    | «دفع مسبق» on «شركة», the one carrier word available, and took it off the
    | broker and the rep. A مندوب does not get paid in advance; being paid on
    | delivery is his entire commercial position, and on 2026-08-11 there was no
    | row that said so. He withdrew the three words that existed. He could not
    | withdraw or keep a fourth that did not.
    |
    | ── Who gets it, read from the service wiring rather than from an opinion ──
    |
    | #68، #198 and #243 all carry `rep_errand`, `document_courier`,
    | `small_parcel` and `same_day_pickup` in their delivery config — the parcel
    | types, and COD is a parcel word.
    |
    | #166 carries none of them. Its delivery types are `full_truckload`,
    | `partial_load`, `sea_freight`, `air_freight`, `customs_clearance`: it moves
    | consignments, and it holds «تصدير» and «إستيراد» pinned by hand. A freight
    | forwarder settles against an invoice, not at a doorstep. It is left out for
    | the same reason «شحن دولي» was left out of «مندوب» below — the exclusion
    | is symmetric and both halves are read from the child's own wiring.
    |
    | ── Why `extend` and not a new group ─────────────────────────────────────
    |
    | It goes into «الدفع والسداد» beside «دفع مسبق», because it answers the same
    | question, and a second group asking it would be the two half-populated
    | versions of one idea that `option_group_splits.php` names as the disease.
    |
    | It is deliberately NOT added to `payment_terms.options` in
    | `child_option_groups.php`. That list is what ChildOptionGroupsSeeder
    | MANAGES — grants per root and prunes — so a row left out of it is neither
    | handed to 286 children nor deleted from these three. «دفع مسبق» has stood
    | in that exact position since 2026-08-08 and this follows it precisely: in
    | the group, out of the bundle, granted by name to the children whose trade
    | it is.
    */
    'extend' => [
        'الدفع والسداد' => [
            'الدفع عند الاستلام' => 'Cash on Delivery',
        ],

        /*
        |----------------------------------------------------------------------
        | 2026-08-18 — «وحدة التسعير»
        |----------------------------------------------------------------------
        | Owner: «نفذ وحدة التسعير».
        |
        | A freight price with no unit is half a price. The four carriers could
        | say how far they go, how fast, by what mode and in what kind of body —
        | and not the one thing that makes two quotes comparable: per WHAT. A
        | merchant quoting 900 and a merchant quoting 4,000 are not expensive and
        | cheap until you know one means a tonne and the other a trip.
        |
        | ── Why «وحدة البيع» and not a new group ──────────────────────────────
        |
        | Because it is the same question, and «بالطن» #2010 and «بالكيلو» #2008
        | are already IN it. A second group named «وحدة التسعير» would ask «فى
        | إيه؟» beside a group asking «فى إيه؟», and a merchant could answer one
        | and not the other — the two half-populated versions of one idea that
        | `option_group_splits.php` names as this taxonomy's oldest disease. The
        | group is already a `modifier`, which is the right role: the same lorry
        | is one rate by the tonne and another by the trip.
        |
        | Its farm rows — بالأردب، بالشيكارة، بالطبق — do not follow. A group is
        | shared; a CHILD's view of it is not, and the links below are the gate.
        |
        | ── The five that had to be minted ────────────────────────────────────
        |
        | «بالمتر المكعب» is what LCL and a furniture move are quoted in;
        | «بالحاوية» is the 20ft/40ft box; «بالرحلة» is the full truckload and
        | the مشوار both; «بالكيلومتر» is the long haul; «بالطرد» is the courier.
        | None existed anywhere on the platform.
        */
        'وحدة البيع' => [
            'بالمتر المكعب' => 'Per Cubic Metre',
            'بالحاوية' => 'Per Container',
            'بالرحلة' => 'Per Trip',
            'بالكيلومتر' => 'Per Kilometre',
            'بالطرد' => 'Per Parcel',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | «مندوب» #243 — the biggest child on the platform, and the quietest
    |--------------------------------------------------------------------------
    | Narrowed rather than handed the groups whole, and every cut is read from
    | the child's own service configs rather than from an opinion about couriers.
    |
    | ── نطاق الشحن: three of four ──
    | «شحن دولي» is left out and it is the only exclusion. A مندوب is domestic
    | by definition; the other three are real answers for him, and «الصعيد
    | والحدود» especially — a courier company sending a rep to Aswan is the
    | ordinary case, not the edge.
    |
    | ── سرعة الشحن: all four ──
    | «في نفس اليوم» is literally a courier's product, and his delivery config
    | already carries `same_day_pickup` to prove it.
    |
    | ── تجهيز الشحن البري: three of nine ──
    | His `schedules` config allows exactly `distribution_van` and
    | `distribution_refrigerated`. So «مبرد» and «مجمد» are things the platform
    | ALREADY says he does and had no word for, and «جاف / عادي» is their
    | opposite. The other six — حاوية، صهريج، سطحة، سائبة، ثقيل، خطرة — are for
    | a fleet he does not have, and are the leak this review exists to avoid.
    |
    | ── What he does NOT get, and the contradiction to report ──
    | No vehicle. «مركبات النقل والركاب» held ربع نقل، ربع نقل صندوق and ميني
    | ڤان 7 for him and the owner withdrew all three on 2026-08-11 02:36 — he
    | has ruled on that axis and this file does not reopen it. Worth knowing
    | that the ruling and the service wiring disagree: `distribution_van` says
    | he runs a van and the withdrawal says he may not name one. One of the two
    | is wrong and only he can say which.
    |
    | No `line` either, and that is not a gap. A delivery merchant prices per
    | ITEM TYPE — rep_errand، document_courier، small_parcel، same_day_pickup —
    | and that axis is already his. Inventing an option group beside it would be
    | a second pricing system for one thing, which is the reason «مطاعم
    | وكافيهات» carries no modifier either.
    */
    'links' => [
        /*
        | Who quotes in what, read from each child's own delivery and schedules
        | item types rather than from an opinion about carriers.
        |
        | «شركة» #68 and «مكتب» #198 carry the containers, the LCL consolidation,
        | air freight AND the parcel types, so they quote in all seven.
        |
        | «شحن بري وبحري وجوى» #166 carries every freight type and NO parcel type
        | — no `small_parcel`, no `document_courier` — so it takes six and not
        | «بالطرد». It moves consignments; a parcel rate is a courier's word, and
        | it is the same evidence that kept COD off this child.
        |
        | «مندوب» #243 is the reverse. His schedules allow `distribution_van` and
        | `distribution_refrigerated` and nothing else, so the tonne, the
        | container, the cubic metre and the kilometre are all a fleet he does not
        | have. He is paid per parcel, per kilo, or per مشوار.
        */
        243 => [
            'نطاق الشحن' => ['داخل المدينة', 'بين المحافظات', 'الصعيد والحدود'],
            'سرعة الشحن' => 'all',
            'تجهيز الشحن البري' => ['جاف / عادي', 'مبرد', 'مجمد'],

            // Paid per parcel, per kilo, or per مشوار — the tonne, the
            // container and the kilometre are a fleet he does not have.
            'وحدة البيع' => ['بالطرد', 'بالكيلو', 'بالرحلة'],

            // The word his trade is named for. See the 2026-08-17 block above:
            // his three payment withdrawals are what makes the case for it, not
            // what argues against it.
            'الدفع والسداد' => ['الدفع عند الاستلام'],
        ],

        // «شركة» — it already says «دفع مسبق» and now says the other half. A
        // carrier that takes money up front for a consignment and collects at
        // the door for a parcel is the ordinary Egyptian shipping company, and
        // it could previously describe only one of the two.
        68 => [
            'الدفع والسداد' => ['الدفع عند الاستلام'],
            'وحدة البيع' => ['بالطن', 'بالكيلو', 'بالمتر المكعب', 'بالحاوية', 'بالرحلة', 'بالكيلومتر', 'بالطرد'],
        ],

        // «مكتب» — the broker. He withdrew «دفع مسبق» from it on 2026-08-11 and
        // that ruling stands untouched: an office is not paid in advance. It is
        // paid when the goods are handed over at the counter, which is this row
        // and nothing else, and it left the child with no payment word at all.
        /*
        | «شحن بري وبحري وجوى» #166 carries every freight type and NO parcel type
        | — no `small_parcel`, no `document_courier` — so it takes six of the
        | seven and not «بالطرد». It moves consignments; a parcel rate is a
        | courier's word, and it is the same evidence that kept COD off it.
        |
        | Its only entry in this file, so a whole key rather than a merged line.
        */
        166 => [
            'وحدة البيع' => ['بالطن', 'بالكيلو', 'بالمتر المكعب', 'بالحاوية', 'بالرحلة', 'بالكيلومتر'],
        ],

        198 => [
            'الدفع والسداد' => ['الدفع عند الاستلام'],
            'وحدة البيع' => ['بالطن', 'بالكيلو', 'بالمتر المكعب', 'بالحاوية', 'بالرحلة', 'بالكيلومتر', 'بالطرد'],
        ],
    ],
];
