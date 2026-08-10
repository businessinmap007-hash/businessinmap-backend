<?php

/*
|--------------------------------------------------------------------------
| A booking is time, and time is not delivered
|--------------------------------------------------------------------------
| Owner, 2026-08-10:
|
|   «حجز بدون توصيل هو حجز وقت او مدة فلا نستخدم خدمة التوصيل.»
|
| The rule, not a list — a list of 56 children would rot the first time one was
| added. A child is stripped of `delivery` under a root when, under THAT root:
|
|   1. `booking` is active, and
|   2. neither `menu` nor `retail` is — it sells no goods at all.
|
| Both halves matter. A furniture showroom books a viewing AND sells the piece,
| so it keeps delivery; a نقاش is booked to come and paint your wall, and the
| delivery service on him has never meant anything.
|
| The wiring goes through ChildServiceWriter, so the link and the config are
| switched off together — a dormant service half-wired is the fault
| ServiceWiringIntegrityTest exists to catch.
*/

return [

    /*
    | Owner's own exception, 2026-08-10: «واستثنِ الثلاثة النجارين من قاعدة
    | التوصيل».
    |
    | These three are booked by the hour like every other tradesman, and then a
    | wardrobe, a sofa or a window LEAVES THE WORKSHOP on a lorry. The rule reads
    | «sells no goods» off the service wiring, and the wiring cannot see that a
    | commissioned piece is still a piece.
    */
    'keep_delivery' => [
        'نجار موبيليا',
        'منجد',
        'نجار باب وشباك',
    ],
];
