<?php

/*
|--------------------------------------------------------------------------
| Direct booking vs. unit booking — the approved plan's classification
|--------------------------------------------------------------------------
| Every booking child carried requires_bookable_item = true, forcing each
| business to register individual bookable units even when that is meaningless
| — a gym had to file football pitches, a carpenter 74 unrelated types.
|
| The real question the flag answers is NARROWER than "does it have types":
|   requires_bookable_item = TRUE  → the customer reserves a SPECIFIC INSTANCE,
|       so the business must register units (hotel room 101, court 3, hall A).
|   requires_bookable_item = FALSE → the customer books a TYPE or a slot; no
|       instance list exists (a PS5 hour, a consultation, an inspection visit).
|
| Hence three modes rather than two:
|   'units'        → leave as is; instances are genuinely reserved.
|   'direct'       → no instances AND no meaningful types: allowed_item_types
|                    collapses to ['category'], the DEFAULT_ITEM_TYPE price slot.
|   'direct_typed' → no instances, but the branch's types ARE the price list
|                    (كشف/متابعة، بلايستيشن 4/5، يوم عمل). Flag flips, types stay.
|
| The plan named entertainment "direct"; it is direct_typed here so a shop can
| still price a PS4 hour differently from a PS5 hour — the intent (no unit list)
| is preserved without discarding the pricing the remodel just created.
|
| Consumed by BookingChildModesSeeder. Roots carry a default; children listed
| under 'children' override it.
*/

return [

    'defaults' => [
        'tourist-hotels' => 'units',
        'restaurants-cafes' => 'units',
        'halls' => 'units',
        'health' => 'direct_typed',
        'professions' => 'direct_typed',
        'workshops' => 'direct_typed',
        'offices' => 'direct_typed',
        'training-courses' => 'direct_typed',
        'hair-dresser' => 'direct_typed',
        'arts-entertainment' => 'direct_typed',
        'companies' => 'direct',
        'technology' => 'direct',
        'property-and-land' => 'units',
        'sports' => 'direct_typed',
    ],

    'children' => [
        // A pitch, a lane and a pool lane are booked by the hour as specific
        // instances; a gym subscription and an academy course are not.
        'sports' => [
            'ملاعب كرة' => 'units',
            'حمام سباحة' => 'units',
            'نادي رياضي' => 'units',
        ],
        // Tour operators sell packages, not reserved instances.
        'companies' => [
            'سياحة' => 'direct_typed',
            'رحلات' => 'direct_typed',
        ],
        // Coworking reserves specific rooms/desks.
        'offices' => [
            'منطقة عمل مشتركة' => 'units',
        ],
    ],

    /*
    | Phase 3 — narrowing the lists the 'units' children keep.
    |
    | A units child still inherits its whole BRANCH, which is right for a
    | multi-sport club but wrong for a venue that rents one kind of thing: a
    | swimming pool was being offered eight football and tennis pitches, and a
    | football ground was offered swimming lanes. Only children that genuinely
    | need a narrower slice are listed; everything else keeps its branch, which
    | is already correct (hotels→rooms, restaurants→tables, halls→hall classes,
    | property brokers→every property type).
    */
    'type_overrides' => [
        'sports' => [
            'ملاعب كرة' => ['five_side_field', 'football_7_field', 'football_11_field', 'full_field'],
            'حمام سباحة' => ['swimming_lane'],
        ],
    ],
];
