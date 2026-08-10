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
 * Booking then took four SPECIALISED appointment kinds on top (2026-08-04) —
 * see the note above its `kinds`. They are the one sanctioned exception, and
 * they survive only because one of them serves many trades at once.
 *
 * Retail is deliberately absent. Its `allowed_item_types` is not a vocabulary
 * at all — it is the 1:1 mirror onto `product_category_children.slug` that
 * scopes the shared catalog, and collapsing it would unplug 986 products from
 * every merchant. Delivery and schedules are likewise untouched: their types
 * describe the mechanism already.
 *
 * Shape: service key => [branch, kinds[key => [name_ar, name_en]],
 *                        map[old branch key => new kind key], child_branches].
 * Consumed by \Database\Seeders\ServiceKindsCollapseSeeder.
 *
 * `child_branches` names the approved child→branch file the branch seeders
 * apply. The collapse reads it FIRST, and reads it for a specific reason: the
 * branch rows it used to derive a kind from are exactly what the collapse then
 * deletes, so deriving from the database alone worked once and reset every
 * config to `default` on the next run. The file cannot be consumed by its own
 * output, so this is the only source that stays true across re-runs.
 */

return [

    'booking' => [
        'branch' => ['key' => 'booking_kinds', 'name_ar' => 'أنواع الحجز', 'name_en' => 'Booking Kinds'],
        'child_branches' => 'booking_child_branches.php',

        /*
         * The four mechanisms, then the specialised appointments (owner call
         * 2026-08-04).
         *
         * The first four answer HOW a thing is booked and nothing else. The
         * four below are narrower: they are all appointments, and they name
         * WHICH KIND of appointment — a dentist takes a كشف, an engineer takes
         * an استشارة, and the price differs by that alone.
         *
         * This is a deliberate exception to the rule the collapse drew, made by
         * the owner: one kind serves many specialties, so «استشارة» does not
         * multiply per trade the way the old 294 did, and it is the axis a
         * merchant prices on. NOTHING is auto-assigned — a child gets one only
         * when the owner ticks it, so a clinic can offer كشف and متابعة while
         * an engineering office offers استشارة alone.
         *
         * ⚠ Every booking type absent from this array is DEACTIVATED on the
         * next run and deleted by prune(). A kind added anywhere else — the
         * admin panel, a one-off seeder — does not survive. Add it here.
         */
        'kinds' => [
            'booking_appointment' => ['حجز موعد', 'Appointment'],
            'booking_time' => ['حجز وقت', 'Time Slot'],
            /*
             * «حجز فندق» until 2026-08-08, and it never only meant a hotel:
             * real estate has ridden this kind since the collapse, so a مكتب
             * عقاري renting a flat was offering «حجز فندق». The kind says HOW —
             * a named unit held for a period — and that is as true of a flat
             * and a rental car as of room 101.
             */
            'booking_stay' => ['حجز بالمدة', 'Period Booking'],
            'booking_table' => ['حجز طاولة', 'Table'],

            'booking_consultation' => ['حجز استشارة', 'Consultation'],
            'booking_examination' => ['حجز كشف', 'Examination'],
            'booking_follow_up' => ['حجز متابعة', 'Follow-up'],
            'booking_procedure' => ['حجز إجراء طبي', 'Medical Procedure'],
            'booking_online_consultation' => ['حجز استشارة أونلاين', 'Online Consultation'],
            'booking_home_sample' => ['حجز سحب عينة بالمنزل', 'Home Sample Collection'],
            'booking_home_visit' => ['حجز زيارة منزلية', 'Home Visit'],
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
         * Per-child assignment of the specialised kinds (owner-approved
         * 2026-08-05). Highest precedence, and it REPLACES rather than adds:
         * a clinic that offers كشف and متابعة should not also show a bare
         * «حجز موعد» beside them, which says nothing the other two don't.
         *
         * Safe to switch outright — all thirteen children carry ZERO priced
         * rows today, so nothing was left pointing at «حجز موعد».
         *
         * What a child is NOT given is as deliberate as what it is: «إجراء
         * طبي» goes to the hospital and the medical centre but not the clinic,
         * which examines and follows up; «متابعة» goes to law and accounting,
         * where following a case or a month is a real second price, but not to
         * the one-off trades. معمل تحاليل and مراكز أشعة are absent on purpose
         * — what they sell is «تحليل» and «أشعة», already line options, and the
         * booking is a plain appointment.
         *
         * Keyed by child_id, so a child sitting under two roots (11 «دعاية
         * وإعلان» is under both companies and offices) is answered once.
         */
        'children' => [
            // طبي
            /*
             * The clinic gets the general «زيارة منزلية», not the lab's
             * «سحب عينة بالمنزل»: the two are not the same errand. A nurse
             * comes, draws blood and leaves; a doctor comes and examines. Same
             * doorstep, different thing bought, and the price says so.
             */
            514 => ['booking_examination', 'booking_follow_up', 'booking_online_consultation', 'booking_home_visit'], // عيادة
            /*
             * The hospital gained «استشارة» on 2026-08-07 — the owner's own
             * edit, kept because a hospital really does sell an opinion that is
             * neither a كشف nor a متابعة (a second opinion, a pre-op review).
             * It is the one child here that carries all three.
             */
            513 => ['booking_consultation', 'booking_examination', 'booking_procedure', 'booking_online_consultation', 'booking_follow_up', 'booking_home_sample'], // مستشفى
            515 => ['booking_examination', 'booking_follow_up', 'booking_procedure', 'booking_online_consultation', 'booking_home_sample'], // مركز طبي
            /*
             * «مركز حجامة» #542, added 2026-08-09 with its booking config copied
             * whole from the clinic. The copy is not enough on its own: THIS map
             * is what ServiceKindsCollapseSeeder rewrites configs from, and a
             * child missing from it is handed the bare «موعد» — which is how the
             * cupping centre silently lost the four clinic kinds the day the
             * collapse next ran. A new booking child belongs in both places.
             *
             * No «سحب عينة بالمنزل»: a cupping session at home is a visit, not a
             * sample. Same four the clinic carries.
             */
            542 => ['booking_examination', 'booking_follow_up', 'booking_online_consultation', 'booking_home_visit'], // مركز حجامة

            /*
             * A lab keeps the plain appointment — coming in to give a sample is
             * still the ordinary case — and gains the home visit beside it.
             *
             * The home draw is a booking KIND and not a modifier on the 28
             * tests: it is priced once as a visit, where a modifier would have
             * doubled every test into «بالمعمل» and «بالمنزل», 28 rows becoming
             * 56 to say one thing. The customer picks the visit, then picks the
             * tests from the options he was always going to pick from.
             */
            163 => ['booking_appointment', 'booking_home_sample'], // معمل تحاليل

            // مهني
            // The owner added the plain appointment on 2026-08-09 from the bulk
            // screen (config_source: services_bulk): an agency is visited as well
            // as consulted. Recorded here so the next seed does not take it back.
            11 => ['booking_appointment', 'booking_consultation', 'booking_online_consultation'], // دعاية وإعلان
            123 => ['booking_consultation', 'booking_online_consultation'], // هندسية
            78 => ['booking_consultation', 'booking_online_consultation'],  // ديكور
            167 => ['booking_consultation', 'booking_follow_up', 'booking_online_consultation'], // محاماه
            10 => ['booking_consultation', 'booking_follow_up', 'booking_online_consultation'],  // محاسبة
            177 => ['booking_consultation', 'booking_online_consultation'], // تسويق
            233 => ['booking_consultation', 'booking_online_consultation'], // برمجة
            261 => ['booking_consultation', 'booking_online_consultation'], // برمجيات
            153 => ['booking_consultation', 'booking_online_consultation'], // شركات تأمين

            // A contractor gives advice, then executes — the owner's call, and
            // the largest child on this list at 71 businesses. Consultation
            // only: there is no online version of walking a site.
            72 => ['booking_consultation'], // مقاولات
        ],

        /*
         * Root-level fallback for a child the branch file does not name — and
         * it does not name many: the file was generated 2026-07-12 and 80 of
         * its child names have since been renamed, merged or retired, so a
         * child-name lookup alone leaves two thirds of the configs to `default`.
         *
         * Not a new classification. Each root here is the branch its own listed
         * children already agree on — professions 30/31 services_tasks, sports
         * 14/14 sports, halls 4/4 halls_events, tourist-hotels 6/6 hotel — read
         * through the same `map` above. A per-child entry still wins over it.
         *
         * `shops-online` and `arts-entertainment` are deliberately absent: their
         * children genuinely disagree (five market branches against two food
         * ones), and guessing a root there would be inventing an answer rather
         * than reading one.
         */
        'roots' => [
            'professions' => 'booking_appointment',
            'workshops' => 'booking_appointment',
            'training-courses' => 'booking_appointment',
            'technology' => 'booking_appointment',
            'offices' => 'booking_appointment',
            'companies' => 'booking_appointment',
            'health' => 'booking_appointment',
            'hair-dresser' => 'booking_appointment',

            'sports' => 'booking_time',
            'halls' => 'booking_time',

            'restaurants-cafes' => 'booking_table',

            'property-and-land' => 'booking_stay',
            'tourist-hotels' => 'booking_stay',
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
        'child_branches' => 'menu_child_branches.php',

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
