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

            /*
             * Added in the admin panel on 2026-08-14 and written down here on
             * 2026-08-15 — which is the whole point of the warning above. Six
             * live configs were already selling on these two, and every one of
             * them would have been thrown away by the next full seed: absent
             * from this array a kind is deactivated, pruned, and every config
             * offering it falls through to the bare «حجز موعد».
             *
             * Both are genuine mechanisms rather than trades, so they belong
             * with the other ten:
             *
             *   حجز كورس   a training centre, a nursery and a lessons centre do
             *              not sell an hour or an appointment. The customer
             *              enrols in a course that runs over a stretch of days,
             *              and the enrolment is the thing bought — one price,
             *              one booking, many sessions inside it.
             *
             *   حجز زيارة  the mirror of «زيارة منزلية», for a business rather
             *              than a home: a software house, a telecoms contractor
             *              and a security firm are booked to COME to the site
             *              and look before anything is quoted.
             */
            'book_a_course' => ['حجز كورس', 'Course Enrolment'],
            'book_a_visit' => ['حجز زيارة', 'Site Visit'],
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
            /*
             * Missing until 2026-08-11, so all ten children of «فنون و ترفية»
             * fell through to the default below and were given «حجز موعد».
             *
             * You do not make an appointment at a billiards hall. You take a
             * table for an hour — and the platform already knew that, because
             * «الرياضة» is the same shape of business and every one of its six
             * children is on «حجز وقت»: a gym, a pool, a football pitch. A
             * playstation lounge, a bowling alley, an internet café and a
             * children's play area are bought by the hour in exactly the way a
             * five-a-side pitch is.
             *
             * «فوتوجرافر» and «رحلات ومراكب» are the two that are not, and both
             * are named in `children` below.
             */
            'entertainment_leisure' => 'booking_time',

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
            /*
             * The three «تكنولوجيا» trades that were given «حجز زيارة» from the
             * bulk screen on 2026-08-14 (config_source: services_bulk), with
             * the plain appointment beside it. A survey visit is what these are
             * actually booked for before a quote exists, and it is priced as
             * its own thing — recorded here so the next collapse keeps it.
             */
            233 => ['booking_appointment', 'booking_consultation', 'booking_online_consultation', 'book_a_visit'], // برمجة
            67 => ['booking_appointment', 'booking_consultation', 'booking_online_consultation', 'book_a_visit'],  // إتصالات
            254 => ['booking_appointment', 'booking_consultation', 'booking_online_consultation', 'book_a_visit'], // أمن وسلامة

            261 => ['booking_consultation', 'booking_online_consultation'], // برمجيات
            153 => ['booking_consultation', 'booking_online_consultation'], // شركات تأمين

            // A contractor gives advice, then executes — the owner's call, and
            // the largest child on this list at 71 businesses. Consultation
            // only: there is no online version of walking a site.
            72 => ['booking_consultation'], // مقاولات

            /*
             * «مطاعم وكافيهات» — the plain appointment beside the table, added
             * from the bulk screen on 2026-08-14 00:57 for all six.
             *
             * A table is held for a sitting; an appointment is the other thing
             * these six are booked for — a tasting, a private event walkthrough,
             * a coffee cart booked for a morning. Written here because this
             * array is the FIRST thing BookingChildBranchesSeeder reads, so the
             * `restaurant_table` branch would otherwise have narrowed all six
             * back to «حجز طاولة» alone on its next run.
             */
            64 => ['booking_appointment', 'booking_table'],  // كافيه
            65 => ['booking_appointment', 'booking_table'],  // عربية قهوة ومأكولات
            108 => ['booking_appointment', 'booking_table'], // مجمع مطاعم
            143 => ['booking_appointment', 'booking_table'], // أكل بيتى
            245 => ['booking_appointment', 'booking_table'], // مطعم
            246 => ['booking_appointment', 'booking_table'], // مطعم وكافيه

            /*
             * «دورات و تدريب» — the course enrolment, from the same 2026-08-14
             * pass. All three sell a programme that runs, not a slot: a nursery
             * term, a course at a lessons centre, a training programme.
             */
            86 => ['book_a_course'],  // سنتر دروس
            195 => ['book_a_course'], // حضانات
            529 => ['book_a_course'], // مركز تدريب

            // The two exceptions to «entertainment_leisure → حجز وقت» above.
            // A photographer sells a session with a person, not a seat for an
            // hour; a boat trip has a departure, not a duration you choose.
            //
            // Both were narrowed further from the bulk screen on 2026-08-14 and
            // this line is what the collapse reads, so leaving it stale would
            // have taken the edit back on the next run:
            //   فوتوجرافر  keeps the appointment AND gains «حجز وقت» — a studio
            //              hour is bought differently from a shoot booked with
            //              a particular photographer, and he sells both.
            //   رحلات ومراكب  is «حجز بالمدة», not an appointment: a boat is held
            //              from a date to a date, the same shape as a hotel room
            //              or a rented flat.
            217 => ['booking_appointment', 'booking_time'], // فوتوجرافر
            526 => ['booking_stay'], // رحلات ومراكب
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

        /*
         * The listing children, stated outright — because an explicit
         * assignment «wins outright and is not merged with anything», which is
         * the only thing that keeps the default below off them.
         *
         * It had not been kept off them. Fifteen configs carried «منيو» beside
         * their real kind: a furniture factory (35 businesses), a car showroom
         * (9), an estate agent (16) could each publish a food menu. The default
         * put it there before ListingServiceLinkSeeder ever ran, and that
         * seeder MERGES by design, so it added «موبليات» beside the food rather
         * than instead of it.
         *
         * «مالك عقار» is the proof: the one listing child with no prior menu
         * config, so there was nothing to merge with, and it came out holding
         * «عقارات» alone — the shape all ten should have had.
         *
         * This is the same trap the booking block above documents («a clinic
         * given كشف and متابعة would keep a bare حجز موعد beside them»). The
         * menu block never got the same answer.
         */
        'children' => [
            517 => ['menu_properties'],  // مكتب عقاري
            518 => ['menu_properties'],  // مطور عقاري
            522 => ['menu_properties'],  // مالك عقار
            // #238 تسويق عقاري folded into #517 مكتب عقاري on 2026-08-12.
            // #53 سيارات folded into #188 معرض سيارات on 2026-08-17/18 and
            // was hard-deleted by the owner 2026-08-26 (rootless list review).
            188 => ['menu_vehicles'],    // معرض سيارات
            189 => ['menu_vehicles'],    // معرض موتوسيكلات
            116 => ['menu_furniture'],   // آثاث
            115 => ['menu_furniture'],   // مفروشات
            57 => ['menu_furniture'],    // نجف و تحف
        ],

        /*
         * «منيو» is what a child gets when nothing said otherwise. It is the
         * absence of an answer, not an answer — see the children block above
         * for what that cost.
         */
        'default' => 'menu_food',
    ],
];
