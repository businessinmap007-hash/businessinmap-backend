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
*/

return [

    'root' => 'shipping-delivery',

    'name_en_suffix' => 'Freight',

    'groups' => [

        'نطاق الشحن' => [
            'name_en' => 'Shipping Range', 'price_role' => 'modifier', 'children' => [68, 198],
            'options' => [
                'داخل المدينة' => 'Within the City',
                'بين المحافظات' => 'Intercity',
                'الصعيد والحدود' => 'Upper Egypt & Borders',
                'شحن دولي' => 'International',
            ],
        ],

        'سرعة الشحن' => [
            'name_en' => 'Delivery Speed', 'price_role' => 'modifier', 'children' => [68, 198],
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
        243 => [
            'نطاق الشحن' => ['داخل المدينة', 'بين المحافظات', 'الصعيد والحدود'],
            'سرعة الشحن' => 'all',
            'تجهيز الشحن البري' => ['جاف / عادي', 'مبرد', 'مجمد'],
        ],
    ],
];
