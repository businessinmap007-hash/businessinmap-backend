<?php

/*
|--------------------------------------------------------------------------
| «فنادق سياحية» — a room, an airport transfer, and nothing else for sale
|--------------------------------------------------------------------------
| Owner, 2026-08-17: «راجع باقي أبناء الفنادق والسياحة بنفس الطريقة».
|
| This is the most carefully built root in the taxonomy and the audit says so.
| All six children are fluent, none is promoted, none is narrowed to silence,
| and «الغرف» is scoped room by room — a hostel sells a dorm bed and never a
| royal suite, only the Nile boat says «كابينة», the aparthotel is sold by unit
| size and not by bed count. `HotelRoomKindOptionsSeeder` owns that axis and
| argues every line of it.
|
| Two things it does not cover.
|
| ── 1. «خدمات الفندق» had ONE row ────────────────────────────────────────
|
| «نقل من المطار». That is the entire list of things a hotel can put a price
| on besides the room itself.
|
| The group exists because the mixed-group audit pulled that one row out of
| «مرافق الإقامة» on 2026-08-16, and its note defends a group of one: it earns
| its place when something otherwise unpriceable becomes priceable. True — and
| the same cut has more to do here, because the facilities list it came from
| still holds «سبا» and «مسبح», and those are the FACILITY half of the rule the
| gym and the clinic were already sorted by: a pool the resident swims in is a
| facility, a massage session and a day pass are somebody's time and a ticket.
| A hotel that sells both had one word for the first and none for the second.
|
| Seven rows added, every one of them something an Egyptian hotel charges for
| separately and cannot currently name: an extra bed, a spa session, day use of
| the pool, laundry, a meeting room, an early check-in, a day tour.
|
| Scoped per child in `links` rather than granted to all six, the same
| discipline the room list uses: a hostel does not hire out a meeting room, an
| aparthotel does not run a spa, and a city hotel does not sell a Luxor trip
| the way the boat moored beside it does.
|
| ── 2. The one accommodation type that sells a bed by gender could not say so ─
|
| «سيدات / رجال / ميكس» arrived on 2026-08-13 for the trades where the ROOM is
| segregated — a gym, a pool, a hall, a trainer. Two hospitality children hold
| them and neither is the one that needs them: they reached «بيت ضيافة» and
| «فندق عائم» through `HospitalityOptionRestoreSeeder`, which restores whole
| GROUPS and so handed over the gender rows its own docblock never intended —
| that file states the base is «the intersection of all four intact siblings»,
| and the four hold exactly عائلي and ممنوع التدخين.
|
| Meanwhile «نُزل / هوستل», which sells «سرير في غرفة مشتركة» and is the only
| child in the root whose entire product is a bed in a shared room, had no way
| to say whose room it is.
|
| So the axis is put where the trade actually asks it: the hostel gains it, the
| guest house keeps it — a بيت ضيافة للسيدات is a real listing — and the Nile
| cruiser is narrowed back to the sibling base in `child_option_scopes.php`,
| because a boat sells cabins to a mixed manifest and nothing about it is
| segregated. The restore file was corrected at the same time so it stops
| re-granting the group whole.
*/

return [

    'root' => 'tourist-hotels',

    'name_en_suffix' => 'Hotel',

    /*
    | Created in the existing group, not linked from here — the per-child map
    | below decides who may say each one. `extend` is exactly this: mint the
    | vocabulary, let `links` hand it out.
    */
    'extend' => [
        'خدمات الفندق' => [
            'سرير إضافي' => 'Extra Bed',
            'جلسة سبا ومساج' => 'Spa & Massage Session',
            'دخول المسبح (Day Use)' => 'Pool Day Pass',
            'غسيل وكي' => 'Laundry & Ironing',
            'تأجير قاعة اجتماعات' => 'Meeting Room Hire',
            'تسجيل دخول مبكر أو مغادرة متأخرة' => 'Early Check-in / Late Check-out',
            'جولة سياحية يومية' => 'Day Tour',
        ],
    ],

    /*
    | Shared rows (`category_id = 0`), which is right and not laziness: every
    | one of these six children hangs from «فنادق سياحية» and from nothing else,
    | so there is no second root for a scoped row to say anything different to.
    | `hospitality_option_restore.php` reaches the same conclusion in its own
    | words, and its collapseScoped() exists to undo the scoped duplicates an
    | accidental save left behind.
    */
    'links' => [

        // The four every place that lets a bed for the night charges for.
        536 => ['خدمات الفندق' => [
            'نقل من المطار', 'سرير إضافي', 'غسيل وكي', 'تسجيل دخول مبكر أو مغادرة متأخرة',
            // …and the three a full-service city hotel adds: a spa treatment,
            // a pool pass sold to somebody not staying, a meeting room, and the
            // pyramids trip its concierge books.
            'جلسة سبا ومساج', 'دخول المسبح (Day Use)', 'تأجير قاعة اجتماعات', 'جولة سياحية يومية',
        ]],

        // A serviced apartment is a flat with a reception desk. It has no spa,
        // no day-use pool and no meeting room, and it does not run excursions —
        // the guest who books a month in one is not on a tour.
        537 => ['خدمات الفندق' => [
            'نقل من المطار', 'سرير إضافي', 'غسيل وكي', 'تسجيل دخول مبكر أو مغادرة متأخرة',
        ]],

        // Everything the hotel has, and the excursion desk is busier.
        538 => ['خدمات الفندق' => [
            'نقل من المطار', 'سرير إضافي', 'غسيل وكي', 'تسجيل دخول مبكر أو مغادرة متأخرة',
            'جلسة سبا ومساج', 'دخول المسبح (Day Use)', 'تأجير قاعة اجتماعات', 'جولة سياحية يومية',
        ]],

        // Laundry and a day trip are half a hostel's income. A spa is not.
        539 => [
            'خدمات الفندق' => [
                'نقل من المطار', 'سرير إضافي', 'غسيل وكي', 'تسجيل دخول مبكر أو مغادرة متأخرة',
                'جولة سياحية يومية',
            ],
            /*
             * The dorm. This is the child the axis was always for.
             *
             * «ميكس» was withdrawn by hand on 2026-08-20 and is not re-declared:
             * a hostel advertises a ladies' dorm or a men's dorm, and the room
             * that is neither is the DEFAULT — it is what a dorm is when nobody
             * says otherwise. Ticking all three says the same thing twice.
             */
            'ملاءمة المكان' => ['سيدات', 'رجال'],
        ],

        540 => [
            'خدمات الفندق' => [
                'نقل من المطار', 'سرير إضافي', 'غسيل وكي', 'تسجيل دخول مبكر أو مغادرة متأخرة',
                'جولة سياحية يومية',
            ],
            // Kept, and named here on purpose: it holds these today only because
            // the restore seeder granted the group whole, and that grant is being
            // taken out of the restore file. A بيت ضيافة للسيدات is a real
            // listing, so the row is re-declared where it can be argued with
            // rather than left depending on a bug.
            'ملاءمة المكان' => ['سيدات', 'رجال', 'ميكس'],
        ],

        // The temple excursions ARE the product; the spa is on the sun deck.
        // No day-use pool — nobody buys a swim on a moving boat — and no
        // meeting room.
        541 => ['خدمات الفندق' => [
            'نقل من المطار', 'سرير إضافي', 'غسيل وكي', 'تسجيل دخول مبكر أو مغادرة متأخرة',
            'جلسة سبا ومساج', 'جولة سياحية يومية',
        ]],
    ],
];
