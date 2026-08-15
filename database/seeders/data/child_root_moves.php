<?php

/*
|--------------------------------------------------------------------------
| A child filed under the wrong root
|--------------------------------------------------------------------------
| Owner, 2026-08-09: «انقل مأذون شرعى من مهن وحرفيين الى مكاتب».
|
| Not a cleanup and not a remodel — a filing correction. The child, its
| vocabulary, its services and its accounts all stay exactly as they are; only
| the root it hangs from changes, because that root is where a customer looks.
|
| A مأذون is not a craftsman you call to the house. He keeps a register and
| receives you at his office, next to «محاماه» — which root #19 already carries.
|
| Declared here rather than written into a one-off script so the next move is one
| line, and so the record of what moved and why survives the commit message.
|
| Rules the seeder enforces:
|   - the child must currently hang from `from_root`, or the move is skipped;
|   - service links, configs and fee rows move WITH it, keeping their stored
|     config and provenance — a move must not silently rewrite an admin's work;
|   - any account sitting on the child has its `category_id` moved too, so
|     nobody is left pointing at a root the child no longer belongs to.
|
| `adopt_services` — set it when the move changes what the business IS, not just
| where it is filed. Carrying the wiring verbatim is right for «مأذون شرعى»: he
| took bookings under مهن and takes bookings under مكاتب. It was WRONG for
| «مكملات غذائية», which arrived in المحلات still carrying booking and training
| from الرياضة and unable to sell a single item, and for «تجهيز عرائس», which
| arrived in مهن still a shop and could not be booked. With the flag on, the
| child adopts the service shape its new siblings actually have, and each config
| is copied from one of them rather than invented.
*/

return [

    [
        'child_name_ar' => 'مأذون شرعى',
        'from_root_slug' => 'professions',   // مهن وحرفيين
        'to_root_slug' => 'offices',         // مكاتب
        'why' => 'يستقبل في مكتبه ويحرّر عقدًا، فهو إلى جانب «محاماه» لا إلى جانب الحرفيين',
    ],

    /*
    | From the pass over the remaining roots. Each of these was standing in a
    | root whose own children answer a different question, and each holds at
    | most one account, so nothing is disturbed by the move.
    */

    /*
    | «اصلاح زجاج السيارات» was moved professions → workshops here, and this
    | entry's own note gave the reason it should not have survived: «ورش already
    | holds سمكري، ميكانيكي، كهربائي سيارات، ابواب سيارات، مركز سيارات. This is
    | the sixth of them.» On 2026-08-10 WorkshopRemodelSeeder folded all six —
    | every one is now a priced LINE option inside «تخصصات ورش السيارات» on
    | «ورشة سيارات» #543, this one as option #1202.
    |
    | The child row #104 was left standing under NO root, which is the fold's
    | undo record. The move entry outlived the child's reason to exist and would
    | have re-attached it to ورش on the next seed — a second door beside the
    | workshop, offering what the workshop already prices. Nobody is on it (0
    | accounts).
    |
    | WITHDRAWN, on the same rule as «تجهيز عرائس» below: a move seeder that
    | names a folded child re-attaches it on its next run.
    */

    [
        // Root 17 is «المحلات أو أونلاين» — things sold over a counter. A studio
        // is a place you book time in, which is what root 9 is for; it already
        // holds «فوتوجرافر».
        'child_name_ar' => 'استوديوهات',
        'from_root_slug' => 'shops-online',
        'to_root_slug' => 'arts-entertainment',
        'why' => 'الاستوديو مكان يُحجز فيه وقت لا محل يبيع، وبجواره «فوتوجرافر»',
    ],

    [
        // Root 7 «الرياضة» holds PLACES you go to and book: جيم، ملاعب، حمام
        // سباحة، أكاديمية، نادي. A supplements seller is a shop, and the root
        // says where the business stands.
        'child_name_ar' => 'مكملات غذائية',
        'adopt_services' => true,
        'from_root_slug' => 'sports',
        'to_root_slug' => 'shops-online',
        'why' => 'جذر الرياضة أماكن تُحجز، وبائع المكملات محل — بجوار «عطور» و«أدوات تجميل»',
    ],

    /*
    | «عفشجى» was moved ورش → شحن وتوصيل here on 2026-08-09, and the owner took
    | it off شحن وتوصيل on 2026-08-10 — the trade is one of the one-man benches
    | he wants written as words rather than rows. The entry is WITHDRAWN rather
    | than left pointing at a root the child no longer stands under: a move
    | seeder that names a detached child re-attaches it on its next run, which is
    | the failure this file has already caused twice ([[seeder-must-withdraw]]).
    | The detachment now lives in data/child_root_detachments.php, and it names
    | ورش as well, so a fresh seed never leaves it where it started either.
    */

    /*
    | Second pass over the roots, 2026-08-09. Both hold zero accounts.
    */

    /*
    | «نادي صحي» stood here — moved out of الصحة into الرياضة on 2026-08-09,
    | because a health club is not a medical facility. On 2026-08-14 the owner
    | retired it outright: «حذف نادي صحي ونكتفى ب نادى رياضى واكاديمية». It
    | carried no account and no vocabulary «نادي رياضي» lacks.
    |
    | The entry is GONE rather than kept-and-ignored, because a move says «put
    | it under sports» and `child_root_detachments.php` now says «take it out of
    | sports». Two files arguing, and whichever seeder ran last would win.
    */

    [
        // Root 15 «تكنولوجيا» is what is built and run — برمجة، إتصالات. Running
        // someone's pages is an ADVERTISING service, and root 19 already carries
        // «دعاية وإعلان» and «محاسبة»: the offices you hire to do a thing for you.
        'child_name_ar' => 'إدارة صفحات',
        'from_root_slug' => 'technology',
        'to_root_slug' => 'offices',
        'why' => 'إدارة صفحات عملٌ دعائي يُشترى من مكتب، لا منتج تقني — بجوار «دعاية وإعلان»',
    ],

    /*
    | «تجهيز عرائس» was moved shops-online → مهن وحرفيين here on 2026-08-09 on the
    | owner's ruling «خدمة تجميل انقله», and the note in this entry already said
    | the quiet part: it is ALSO a priced line option inside «خدمات الكوافير
    | والتجميل». So the move landed it next to a child that could already say it,
    | and on 2026-08-10 its three merchants were folded onto «كوافير» with that
    | service ticked (data/child_root_detachments.php).
    |
    | The entry is WITHDRAWN rather than left pointing at a root the child no
    | longer stands under — a move seeder that names a folded child re-attaches
    | it on its next run.
    */

    /*
    | Third pass, 2026-08-09.
    */

    [
        // The other 28 children of مهن وحرفيين are building and household
        // crafts — نقاش، سباك، نجار، حداد، مبلط. Root 13 «سيارات» is where a
        // car-based service stands, and it already holds نقل ركاب (55 accounts)
        // and خدمة ليموزين, which is what hiring a driver actually is.
        'child_name_ar' => 'سائق',
        'adopt_services' => true,
        'from_root_slug' => 'professions',
        'to_root_slug' => 'cars',
        'why' => 'خدمة تقوم على سيارة، فمكانها مع «نقل ركاب» و«خدمة ليموزين» لا بين حرف البناء',
    ],
];
