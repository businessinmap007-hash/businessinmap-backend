<?php

/*
|--------------------------------------------------------------------------
| Entertainment taxonomy — root 9 «فنون و ترفية»
|--------------------------------------------------------------------------
| This section is the INVERSE of Health. There, business types were stuck in
| the item-type axis while specialties squatted on the children. Here most of
| the children are already correct — a bowling alley, a billiards hall, a
| PlayStation shop really are business types — and it is the ITEM TYPE axis
| that is polluted: `entertainment_leisure` holds اكوا بارك، صالة ألعاب،
| منطقه للأطفال، نادي, which are venues, not things you can price.
|
| So the fixes run in the opposite direction:
|   - venue item types      → children  (اكوا بارك، صالة ألعاب، منطقة أطفال)
|   - trip children         → item types (رحلات بحرية/نيلية/صيد are priced
|                             offerings, not business types) + a رحلات ومراكب
|                             child to own them
|   - real priced units     → added (a table, a lane, a console-hour, a ticket)
|
| بلايستيشن 3 / بلايستيشن 4 stay as item types and are RIGHT: two devices at
| two prices inside one shop is exactly what the item-type axis is for.
| «نادي» is left alone — it is too vague to re-file safely now that سports
| owns نادي رياضي.
|
| Consumed by EntertainmentRemodelSeeder.
*/

return [

    // ── Axis 1. The first seven already exist and are correct; the rest are
    // venues rescued from the item-type axis, plus an owner for the trips.
    'children' => [
        ['name_ar' => 'انترنت كافيه', 'name_en' => 'Internet Cafe',   'existing' => true],
        ['name_ar' => 'بلاي ستيشن',   'name_en' => 'PlayStation Shop', 'existing' => true],
        ['name_ar' => 'بلياردو',      'name_en' => 'Billiards Hall',   'existing' => true],
        ['name_ar' => 'بولينج',       'name_en' => 'Bowling Alley',    'existing' => true],
        ['name_ar' => 'بينج بونج',    'name_en' => 'Table Tennis Hall','existing' => true],
        ['name_ar' => 'مركز ترفيهي',  'name_en' => 'Entertainment Center', 'existing' => true],
        ['name_ar' => 'فوتوجرافر',    'name_en' => 'Photographer',     'existing' => true],
        // rescued from `entertainment_leisure` item types
        ['name_ar' => 'اكوا بارك',    'name_en' => 'Aqua Park',        'existing' => false],
        ['name_ar' => 'صالة ألعاب',   'name_en' => 'Games Arcade',     'existing' => false],
        ['name_ar' => 'منطقة أطفال',  'name_en' => 'Kids Play Area',   'existing' => false],
        // owns the trips that used to be children
        ['name_ar' => 'رحلات ومراكب', 'name_en' => 'Trips & Boats',    'existing' => false],
    ],

    // Item type keys that are really venues — detached from the booking branch
    // so they stop being offered as something to price. The types themselves
    // are deactivated rather than deleted, keeping any old reference readable.
    'venue_item_types' => ['aqua_park', 'games_hall', 'kids_erea'],

    'branch_key' => 'entertainment_leisure',

    // ── Axis 3: what an entertainment venue actually charges for.
    'priced_item_types' => [
        'playstation_5' => ['بلايستيشن 5', 'PlayStation 5'],
        'billiard_table' => ['طاولة بلياردو', 'Billiard Table'],
        'bowling_lane' => ['مسار بولينج', 'Bowling Lane'],
        'ping_pong_table' => ['طاولة بينج بونج', 'Table Tennis Table'],
        'gaming_pc' => ['جهاز كمبيوتر', 'Gaming PC'],
        'entry_ticket' => ['تذكرة دخول', 'Entry Ticket'],
        'play_hour' => ['ساعة لعب', 'Play Hour'],
    ],

    // ── The trips: priced offerings, filed into the tourism branch that
    // already serves this root, with the children they came from detached.
    'trip_branch_key' => 'tourism_travel',

    'trip_item_types' => [
        'sea_trip' => ['رحلة بحرية', 'Sea Trip'],
        'nile_trip' => ['رحلة نيلية', 'Nile Trip'],
        'fishing_trip' => ['رحلة صيد سمك', 'Fishing Trip'],
    ],

    'detach_children' => ['رحلات بحرية', 'رحلات نيلية', 'رحلة صيد سمك'],

    'business_migration_target' => 'رحلات ومراكب',
];
