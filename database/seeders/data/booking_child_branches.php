<?php

/*
 * Booking child→branch map — generated from the approved live configuration
 * (2026-07-12). Keyed by root slug + child name_ar; values are
 * platform_service_item_groups keys. Consumed by BookingChildBranchesSeeder.
 * See docs/delivery-branches-taxonomy.md for the pattern rationale.
 */

return [

    // ── مهن وحرفيين ──
    'professions' => [
        'أويمجى' => ['services_tasks'],
        'استرجي' => ['services_tasks'],
        'باركيه' => ['services_tasks'],
        'بناء وواجهات حجرية' => ['services_tasks'],
        'تكسير ونحت' => ['services_tasks'],
        'جبس وجبسيوم بورد' => ['services_tasks'], // «جبس وكرانيش» و«جبسيوم بورد» طُويا فيه 2026-08-14
        'جي أر سي' => ['services_tasks'],
        'حداد' => ['services_tasks'],
        'خدمات نظافة' => ['services_tasks'],
        'دش وأقمار صناعية' => ['services_tasks'],
        'رخام وجرانيت' => ['services_tasks'],
        'سباك' => ['services_tasks'],
        'فني صيانة أجهزة منزلية' => ['services_tasks'],   // was «صيانة اجهزة منزلية»
        'صيانة تبريد وتكييف' => ['services_tasks'],   // was «صيانة تكيف»
        'عامل بناء' => ['services_tasks'],
        'فني الوميتال' => ['services_tasks'],
        'فني ستائر و تنجيد' => ['services_tasks'],
        'كهربائي' => ['services_tasks'],
        'متخصص كوافير' => ['beauty_care'],            // was «كوافير»
        'مبلط' => ['services_tasks'],
        'مبيض محارة' => ['services_tasks'],
        'منجد' => ['services_tasks'],
        'نجار تنده' => ['services_tasks'],
        'نجار موبيليا' => ['services_tasks'],
        'نقاش' => ['services_tasks'],
    ],

    // ── الرياضة ──
    /*
    | Twelve sports — كرة قدم، سباحة، تنس، باليه — stood here until the remodel
    | made them multi-select OPTIONS, so one club can carry many. What is left
    | is the six places you actually book: an hour on a pitch, in a pool, in a
    | gym.
    */
    'sports' => [
        'جيم' => ['sports'],
        'ملاعب كرة' => ['sports'],
        'حمام سباحة' => ['sports'],
        'نادي رياضي' => ['sports'], // «نادي صحي» طُوي فيه 2026-08-14
        'مدرب' => ['sports'],
        'أكاديمية رياضية' => ['sports'],
    ],

    // ── فنون و ترفية ──
    'arts-entertainment' => [
        'بلاي ستيشن' => ['entertainment_leisure'],
        'بلياردو وبينج بونج' => ['entertainment_leisure'], // «بينج بونج» طُوي فيه 2026-08-14
        'بولينج' => ['entertainment_leisure'],
        'فوتوجرافر' => ['entertainment_leisure'],
        'مركز ترفيهي' => ['entertainment_leisure'],
        // The four EntertainmentRemodelSeeder created (2026-08-04) and this map
        // never learned about, so they fell to the root fallback instead of
        // their branch.
        'اكوا بارك' => ['entertainment_leisure'],
        'صالة ألعاب' => ['entertainment_leisure'],
        'منطقة أطفال' => ['entertainment_leisure'],
        'استوديوهات' => ['entertainment_leisure'],
        // «رحلات بحرية» / «رحلات نيلية» / «رحلة صيد سمك» were folded into
        // «رحلات ومراكب» #526 by the same remodel; naming them here only
        // reported three missing children on every run.
        'رحلات ومراكب' => ['tourism_travel'],
    ],

    // ── ورش ومراكز صيانة ──
    'workshops' => [
        'تبريد وتكييف' => ['services_tasks'],
        'ورشة باب وشباك' => ['services_tasks'],
        // The twenty benches that used to be children here became options
        // inside these four on 2026-08-10 (WorkshopRemodelSeeder). Naming a
        // folded child would only report it missing on every run.
        'ورشة سيارات' => ['services_tasks'],
        'ورشة أثاث ونجارة' => ['services_tasks'],
        'ورشة حدادة وخراطة' => ['services_tasks'],
        'ورشة صيانة أجهزة' => ['services_tasks'],
        // «آثاث» and «باب وشباك» left this root on 2026-08-10 by the owner's
        // word — see data/child_root_detachments.php. A branch map keyed by
        // ROOT that still names them re-wires a child nothing points at.
    ],

    // ── قاعات ──
    /*
    | «اجتماعات»، «حفلات»، «مؤتمرات»، «ندوات مفتوحة» were never businesses —
    | they are what a hall is HIRED FOR, and they became options. The three
    | children below are the halls themselves.
    */
    'halls' => [
        'قاعة مناسبات' => ['halls_events'],
        'مركز مؤتمرات واجتماعات' => ['halls_events'],
        'قاعات تدريب' => ['halls_events'],
    ],

    // ── دورات و  تدريب ──
    'training-courses' => [
        'حضانات' => ['training'],
        'سنتر دروس' => ['training'],
        'مركز تدريب' => ['training'],
    ],

    // ── تكنولوجيا ──
    'technology' => [
        'برمجة' => ['business_consulting'],
    ],

    // ── مطاعم وكافيهات ──
    'restaurants-cafes' => [
        'أكل بيتى' => ['restaurant_table'],
        'عربية قهوة ومأكولات' => ['restaurant_table'],
        'كافيه' => ['restaurant_table'],
        'مجمع مطاعم' => ['restaurant_table'],
        'مطعم' => ['restaurant_table'],
        'مطعم وكافيه' => ['restaurant_table'],
    ],

    // ── عقارات و أراضي ──
    /*
    | Eleven property TYPES — شقة، ڤيلا، أرض، محل، مصنع — stood here as
    | children. A flat is not a business; it is what the business lists, and
    | they became options on «عقارات وممتلكات». The four below are the trades.
    */
    'property-and-land' => [
        'مكتب عقاري' => ['real_estate'],
        'مطور عقاري' => ['real_estate'],
        'مالك عقار' => ['real_estate'],
        // «تسويق عقاري» #238 folded into «مكتب عقاري» on 2026-08-12 (owner).
    ],

    // ── مكاتب ──
    'offices' => [
        /*
         * Unmapped until 2026-08-11, and that absence is what put it on «حجز
         * موعد»: the `coworking` branch has said `booking_time` in the collapse
         * map since the day it was written, but a child no map names never
         * reaches its branch, so the kind the collapse happened to find stored
         * stood unchallenged. The same leak that gave the entertainment root
         * appointments instead of hours.
         *
         * You do not make an appointment for a desk. You take it for an hour,
         * a day or a month — see CoworkingWorkspaceOptionsSeeder.
         */
        'منطقة عمل مشتركة' => ['coworking'],
        'تنسيق حفلات' => ['halls_events', 'business_consulting'],
        'دعاية وإعلان' => ['business_consulting'],
        'محاسبة' => ['business_consulting'],
        'محاماه' => ['business_consulting'],
    ],

    // ── الصحة ──
    /*
    | Forty-one specialty children stood here — أسنان، باطنه، قلب وأوعية دموية —
    | until the three-axis remodel turned every one of them into a multi-select
    | OPTION on «تخصصات طبية», so that one hospital could carry many. The names
    | outlived the children by nine days: this map reported all forty-one
    | «missing» on every run and would have handed them a branch the moment any
    | were linked to a root again.
    |
    | WITHDRAWN. What is left is the three that are still children — a pharmacy,
    | a radiology centre and a lab are places, not specialties.
    |
    | «health_medical» went with them. It was the specialties' own branch, it
    | has no kind in the collapse map, and beside «clinic» it said nothing these
    | three did not already get.
    */
    'health' => [
        'صيدلية' => ['clinic'],
        'مراكز أشعة' => ['clinic'],
        'معمل تحاليل' => ['clinic'],
    ],

    // ── شركات ──
    'companies' => [
        'أمن' => ['business_consulting'],
        'برمجيات' => ['business_consulting'],
        'تسويق' => ['business_consulting'],
        'تنسيق حفلات' => ['halls_events', 'business_consulting'],
        'دعاية وإعلان' => ['business_consulting'],
        'سياحة' => ['tourism_travel', 'business_consulting'],
        'شركات تأمين' => ['business_consulting'],
        'صرافة وتحويل أموال' => ['business_consulting'],
        'مقاولات' => ['business_consulting'],
        'مقاولات بنية تحتية' => ['business_consulting'],
    ],

    // ── فنادق سياحية ──
    /*
    | The six star ratings (1 ⭐ … 5 ➕) were children here and were retired —
    | a star is a grade, not a trade. The six below are the places.
    */
    'tourist-hotels' => [
        'فندق' => ['hotel'],
        'شقق فندقية' => ['hotel'],
        'منتجع' => ['hotel'],
        'نُزل / هوستل' => ['hotel'],
        'بيت ضيافة' => ['hotel'],
        'فندق عائم / بوت نيلي' => ['hotel'],
    ],

];
