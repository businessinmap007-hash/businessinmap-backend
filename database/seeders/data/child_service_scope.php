<?php

/**
 * What each child may actually SELL — the services half of the redistribution.
 *
 * The options map fixed what a business can SAY about itself. This fixes what it
 * can PUT A PRICE ON, and it starts from a hole: the whole `cars` root — 638
 * businesses, 560 of them «خدمة ليموزين» — had exactly one active service,
 * `schedules`, with an EMPTY allowed_item_types. Not a narrow catalogue: an
 * empty one. Those 638 accounts could not list a single sellable thing.
 *
 * Keys are "root-slug:child-id" because a service config is stored per (root,
 * child), unlike the option pivot which is per child.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | schedules — trip legs, by transport mode
    |--------------------------------------------------------------------------
    | 'off' deactivates the config instead of deleting it: a car wash and a
    | parking garage do not run scheduled routes, and a rescue winch answers
    | calls rather than publishing a timetable.
    */
    'schedules' => [
        'cars:169' => ['mode_limousine', 'mode_passenger'], // خدمة ليموزين
        'cars:278' => ['mode_passenger'],                   // نقل ركاب
        // Named 2026-08-11. A hired driver runs the same trips as a limousine
        // service — he was the one child of this root the file did not rule on,
        // and that silence had a price: switching the three below OFF took root
        // 13 from five children carrying `schedules` to three of six siblings,
        // which is no longer a MAJORITY, and ChildRootMovesSeeder::adoptRootShape()
        // then wanted to strip the driver of the one thing he actually sells.
        'cars:85' => ['mode_limousine', 'mode_passenger'],  // سائق
        'cars:284' => ['mode_freight', 'mode_distribution'], // سيارات نقل
        'cars:46' => 'off',                                 // مغسلة سيارات
        'cars:119' => 'off',                                // جراج
        'cars:244' => 'off',                                // ونش إنقاذ

        // the carriers that were never offered the service at all
        'shipping-delivery:68' => ['mode_freight', 'mode_distribution'], // شركة
        'shipping-delivery:243' => ['mode_distribution'],                // مندوب
        'companies:166' => ['mode_freight'],                             // شحن بري وبحري وجوى
        'companies:154' => ['mode_freight'],                             // نقل دولي
    ],

    /*
    |--------------------------------------------------------------------------
    | booking — direct, no unit list
    |--------------------------------------------------------------------------
    | Every child here sells a slot, not a reserved instance, so the price sits
    | on the DEFAULT_ITEM_TYPE («افتراضي», key `category`) exactly as the
    | approved direct-booking plan defines it. A limousine trip, a car wash
    | appointment, a tow call, a studio hour: the customer books the business,
    | not object #3 in its inventory.
    */
    'booking_direct' => [
        'cars:169',  // خدمة ليموزين
        'cars:278',  // نقل ركاب
        'cars:284',  // سيارات نقل
        'cars:46',   // مغسلة سيارات
        'cars:119',  // جراج
        'cars:244',  // ونش إنقاذ
        // «استوديوهات» left المحلات for فنون و ترفية (ChildRootMovesSeeder:
        // «a studio is a place you book time in, which is what root 9 is
        // for»). The ref was not moved with it, so this seeder was creating a
        // config for a (root, child) pairing that does not exist.
        'arts-entertainment:271', // استوديوهات
    ],
];
