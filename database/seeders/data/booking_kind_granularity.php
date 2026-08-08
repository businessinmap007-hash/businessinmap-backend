<?php

/*
|--------------------------------------------------------------------------
| How long each booking kind is measured in
|--------------------------------------------------------------------------
| «يكون البوكينج باليوم والعيادات بالساعة» — owner, 2026-08-08.
|
| The kind already says HOW a thing is booked; it did not say in what UNIT.
| `duration_unit` came from the CLIENT and was validated only against the enum
| ['minute','hour','day','night'], so nothing stopped an app sending «day» for a
| clinic examination — and three live bookings carry no unit at all because the
| app simply omitted it.
|
| The unit belongs to the kind, not to the child: a car showroom rents BY THE
| DAY and takes a test drive BY THE HALF HOUR, and one child-level setting can
| no more say both than one `requires_bookable_item` could say which kinds need
| a unit.
|
| Shape: kind key => [
|     'unit'         => minute|hour|day|night — what a duration counts in,
|     'slot_minutes' => the default length of ONE booking of this kind,
|     'all_day'      => the booking occupies whole days rather than a window,
| ]
|
| `slot_minutes` is a DEFAULT, not a law: it is what the app should propose and
| what fills a duration the client omitted. A business that examines in 40
| minutes rather than 20 is not wrong — the number lives on the type row's meta
| so it can be changed without a release.
|
| Consumed by \Database\Seeders\BookingKindGranularitySeeder, which writes it
| onto platform_service_item_types.meta.
*/

return [

    /*
     * Whole days. A hotel night, a rented flat, a rented car — the customer
     * holds the unit from a date to a date and no clock is involved, which is
     * why all_day is true and slot_minutes is a full day.
     */
    'booking_stay' => ['unit' => 'day', 'slot_minutes' => 1440, 'all_day' => true],

    /*
     * Hours. A hall for an evening, a five-a-side pitch, a desk in a coworking
     * space: a window inside a day, priced by the hour.
     */
    'booking_time' => ['unit' => 'hour', 'slot_minutes' => 60, 'all_day' => false],

    // A table is held for a sitting, not for an hour on the clock.
    'booking_table' => ['unit' => 'hour', 'slot_minutes' => 90, 'all_day' => false],

    /*
     * Minutes — the clinic side of the owner's example, and everything else
     * that is an appointment. These are the four specialised medical kinds plus
     * the generic one, and they differ from each other by how long the thing
     * actually takes: a follow-up is short, a procedure is not.
     */
    'booking_appointment' => ['unit' => 'minute', 'slot_minutes' => 30, 'all_day' => false],
    'booking_consultation' => ['unit' => 'minute', 'slot_minutes' => 45, 'all_day' => false],
    'booking_examination' => ['unit' => 'minute', 'slot_minutes' => 20, 'all_day' => false],
    'booking_follow_up' => ['unit' => 'minute', 'slot_minutes' => 15, 'all_day' => false],
    'booking_procedure' => ['unit' => 'minute', 'slot_minutes' => 60, 'all_day' => false],
    'booking_online_consultation' => ['unit' => 'minute', 'slot_minutes' => 30, 'all_day' => false],

    // A visit to the customer's home: the window has to cover the travel too.
    'booking_home_sample' => ['unit' => 'minute', 'slot_minutes' => 30, 'all_day' => false],
    'booking_home_visit' => ['unit' => 'minute', 'slot_minutes' => 60, 'all_day' => false],
];
