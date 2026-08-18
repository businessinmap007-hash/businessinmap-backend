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
        'ورشة باب وشباك',

        /*
        | Added 2026-08-10 by the same reasoning, one pass later. «طباعة» under
        | شركات was given `booking` that day (ServiceReinstatementSeeder — it had
        | no surface at all), and gaining booking is exactly what pulled it into
        | this rule. A printing company is commissioned by the job and then boxes
        | of printed material leave on a lorry: the carpenters' case, wearing
        | different overalls.
        |
        | Worth noting as a shape: giving a child `booking` can silently cost it
        | `delivery` on the next run of this seeder.
        */
        'طباعة',

        /*
        | The three carriers, 2026-08-11. The rule reads «books time, sells no
        | goods → it never delivers anything», and on these three it inverts:
        | moving the goods IS the trade. A freight forwarder carries the whole
        | `schedules` freight vocabulary — حاوية ٤٠ قدم، شحن جوي، تخليص جمركي —
        | and eight delivery types beside it.
        |
        | They were pulled in the way «طباعة» was: booking is the service that
        | does not belong on them (a shipping office is visited, not reserved),
        | and the rule cured the symptom by proposing to strip the one service
        | that is their entire business. Named here rather than switching their
        | booking off, because being visitable is not wrong — only inferring
        | «then you must not deliver» from it is.
        */
        'معدات ثقيلة',
        // «نقل دولي» folded into «شحن بري وبحري وجوى» on 2026-08-12.
        // «شحن بري وبحري وجوى» folded into «شركة» #68 on 2026-08-18 — the mode
        // became an option all three carriers hold, so the child was a
        // specialty wearing a child's clothes. #68 is named just above.
    ],
];
