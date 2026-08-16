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
| ── «مندوب» #243 is deliberately not here ─────────────────────────────────
|
| It carries 159 merchants, more than any child on the platform, and the owner
| stripped all thirteen of its options by hand on 2026-08-11 — including
| «شحن وتوصيل» and «توصيل مجانى». Handing it two NEW axes hours later would be
| reading his intent rather than his instruction. The seeder would grant these
| happily because they are not in the withdrawal record; that is exactly why
| the restraint has to be in the data file.
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
];
