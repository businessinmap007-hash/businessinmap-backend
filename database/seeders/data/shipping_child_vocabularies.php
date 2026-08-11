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
    ],
];
