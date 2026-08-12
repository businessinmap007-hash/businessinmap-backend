<?php

/*
|--------------------------------------------------------------------------
| Children that can sell nothing at all
|--------------------------------------------------------------------------
| The fashion collapse asked whether the same disease was elsewhere. Measured
| across every root, it mostly is not — and the measurement is worth recording
| because it stops the remodel being applied where it does not belong:
|
|   160 children carry no `line` option. Of those,
|     •  0 have MENU        — every menu child already has a band;
|     • 64 have RETAIL only — their vocabulary is the central catalog, not an
|                             option, so a line would say nothing;
|     • 78 have BOOKING only — `direct_typed` children whose price list IS the
|                             item type, exactly as booking_child_modes intends;
|     • 18 have NEITHER      — and those are genuinely stuck.
|
| These 18 carry only `delivery` and `business_offers`: they can be delivered
| from and can publish an offer, but they cannot list a product, price a line or
| take a booking. 17 real businesses sit on four of them with no way to sell.
|
| The fix is not a taxonomy split — the child names are right. It is the missing
| selling service, chosen by what the trade actually does:
|
|   goods    → retail   (it stocks things a catalogue can hold)
|   service  → booking  (it sells time and turns up; direct, no unit list)
|
| Every one below was classified by that single question. Anything genuinely
| ambiguous was given `booking`, because a wrong booking wastes a screen while a
| wrong retail listing waits on catalogue rows that will never come.
*/

return [

    'goods' => [
        // زراعة: supplies and livestock a catalogue can carry
        'معدات زراعية',
        // Fourteen children of this root became nine on 2026-08-12 (owner):
        // تقاوي+أسمدة, فواكة+خضروات, مواشي+أرانب, and the three «معدات مزارع X»
        // into one. The folded rows survive and reach no root, so naming them
        // here asks whether a child nobody can see is sellable.
        'تقاوي وأسمدة ومبيدات',
        'أعلاف',
        'حبوب وغلال',
        'مواشي وأرانب',
        'مزارع سمكية',
        'معدات وتجهيزات المزارع',

        // شركات: equipment traders
        'كرڤان',
        'معدات سوبرماركت',
        'مصاعد وسلم كهرياء',   // 2 businesses — sells the lift, then installs it
    ],

    'service' => [
        'إتصالات',            // 6 businesses
        'أمن وسلامة',         // 4 businesses
        'استيراد وتصدير',     // 5 businesses — it arranges, it does not stock
        'إدارة صفحات',
    ],
];
