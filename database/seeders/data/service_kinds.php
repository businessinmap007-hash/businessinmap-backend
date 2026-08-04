<?php

/*
 * What a service OFFERS, after the vocabulary moved into options.
 *
 * Booking carried 294 item types across 12 branches and Menu 45 across 8,
 * because the item type was once the only place a merchant could say what he
 * sold. It is not any more: «كشف عظام»، «غرفة نوم — مودرن»، «سيدان — BMW» and
 * «مشويات» all come from `offering_options` now. What was left in the types
 * was a second, coarser copy of the same vocabulary — the duplication that
 * made the customer narrow twice for one question.
 *
 * So the type stops saying WHAT and says only HOW: which kind of booking, or
 * which selling surface. The what comes from the options the child carries.
 *
 * Retail is deliberately absent. Its `allowed_item_types` is not a vocabulary
 * at all — it is the 1:1 mirror onto `product_category_children.slug` that
 * scopes the shared catalog, and collapsing it would unplug 986 products from
 * every merchant. Delivery and schedules are likewise untouched: their types
 * describe the mechanism already.
 *
 * Shape: service key => [branch, kinds[key => [name_ar, name_en]],
 *                        map[old branch key => new kind key]].
 * Consumed by \Database\Seeders\ServiceKindsCollapseSeeder.
 */

return [

    'booking' => [
        'branch' => ['key' => 'booking_kinds', 'name_ar' => 'أنواع الحجز', 'name_en' => 'Booking Kinds'],

        'kinds' => [
            'booking_appointment' => ['حجز موعد', 'Appointment'],
            'booking_time' => ['حجز وقت', 'Time Slot'],
            'booking_stay' => ['حجز فندق', 'Stay'],
            'booking_table' => ['حجز طاولة', 'Table'],
        ],

        /*
         * Every branch lands on the kind that matches how the thing is booked,
         * not what trade sells it. A hall and a five-a-side pitch are both a
         * period of time; a serviced apartment and a hotel room are both a
         * stay measured in nights.
         */
        'map' => [
            'services_tasks' => 'booking_appointment',
            'business_consulting' => 'booking_appointment',
            'clinic' => 'booking_appointment',
            'training' => 'booking_appointment',
            'beauty_care' => 'booking_appointment',
            'tourism_travel' => 'booking_appointment',

            'sports' => 'booking_time',
            'halls_events' => 'booking_time',
            'coworking' => 'booking_time',

            'hotel' => 'booking_stay',
            'real_estate' => 'booking_stay',

            'restaurant_table' => 'booking_table',
        ],

        /*
         * 34 children (761 businesses — showrooms, transport offices, workshops)
         * sit on booking with no branch at all. What they take a booking FOR is
         * an appointment: a viewing, a quote, a car with a driver.
         */
        'default' => 'booking_appointment',
    ],

    'menu' => [
        'branch' => ['key' => 'menu_kinds', 'name_ar' => 'أنواع القائمة', 'name_en' => 'Menu Kinds'],

        'kinds' => [
            'menu_food' => ['منيو', 'Food Menu'],
            'menu_market' => ['ماركت', 'Market'],
            'menu_furniture' => ['موبليات', 'Furniture'],
            'menu_vehicles' => ['سيارات', 'Vehicles'],
            'menu_properties' => ['عقارات', 'Properties'],
        ],

        'map' => [
            'restaurant_menu' => 'menu_food',
            'bakery_sweets' => 'menu_food',
            'beverages_drinks' => 'menu_food',

            'supermarket' => 'menu_market',
            'grocery_pantry' => 'menu_market',
            'fresh_market' => 'menu_market',
            'household_personal' => 'menu_market',
        ],

        /*
         * The listings branch holds three families under one key, so it cannot
         * be mapped by branch — these are matched on the OLD item type instead.
         */
        'by_type' => [
            'furniture_piece' => 'menu_furniture',
            'vehicle_listing' => 'menu_vehicles',
            'property_listing' => 'menu_properties',
        ],

        'default' => 'menu_food',
    ],
];
