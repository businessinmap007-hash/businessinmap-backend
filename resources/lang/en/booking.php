<?php

/*
 * The English half of resources/lang/ar/booking.php.
 *
 * `resources/lang/en/` was an empty directory, so every dotted key resolved to
 * itself: an English client asking for a booking form got a field labelled
 * «booking.field.guest_count». The Arabic-source strings in ar.json/en.json are
 * covered by ServiceMessageLocalizationTest; these keys are not, because they
 * are not raised from app/Services.
 *
 * `guest_count` and `party_size` are one column (`bookings.party_size`) asked in
 * two voices — a hotel counts guests, a restaurant counts people.
 */

return [
    'field' => [
        'datetime' => 'Date & time',
        'date_range' => 'From — to',
        'duration' => 'Duration',
        'guest_count' => 'Number of guests',
        'children_count' => 'Number of children',
        'party_size' => 'Number of people',
        'quantity' => 'Quantity',
        'channel' => 'In person or online',
        'topic' => 'Subject',
        'group' => 'Group',
        'level' => 'Level',
        'visit_place' => 'Where',
        'notes' => 'Notes',
    ],
];
