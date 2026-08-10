<?php

/*
 * Branch wiring for the children CREATED by the 2026-08-02 three-axis remodels
 * (Health, Real Estate, Sports, Entertainment, Halls, Training) — they post-date
 * data/booking_child_branches.php, so without this they had no booking service
 * link at all. Same shape as that file: [root slug => [child name_ar => branch
 * keys]]. Consumed by NewChildrenBranchesSeeder (merge-style, additive).
 *
 * قاعات تدريب re-appears here because it moved from the training-courses root
 * to halls; حمام سباحة/نادي صحي get the sports branch (swimming lanes, courts).
 */

return [
    'health' => [
        'مستشفى' => ['clinic'],
        'عيادة' => ['clinic'],
        'مركز طبي' => ['clinic'],
        'نادي صحي' => ['sports'],
    ],
    'property-and-land' => [
        'مكتب عقاري' => ['real_estate'],
        'تسويق عقاري' => ['real_estate'],
        'مطور عقاري' => ['real_estate'],
        'مالك عقار' => ['real_estate'],
    ],
    'sports' => [
        'نادي رياضي' => ['sports'],
        'أكاديمية رياضية' => ['sports'],
        'حمام سباحة' => ['sports'],
    ],
    'arts-entertainment' => [
        'اكوا بارك' => ['entertainment_leisure'],
        'صالة ألعاب' => ['entertainment_leisure'],
        'منطقة أطفال' => ['entertainment_leisure'],
        'رحلات ومراكب' => ['tourism_travel'],
    ],
    'halls' => [
        'قاعة مناسبات' => ['halls_events'],
        'مركز مؤتمرات واجتماعات' => ['halls_events'],
        'قاعات تدريب' => ['halls_events'],
    ],
    // accommodation types (HotelsRemodelSeeder) — the star children they
    // replace stay wired until their 66 mis-filed accounts are re-classified.
    'tourist-hotels' => [
        'فندق' => ['hotel'],
        'شقق فندقية' => ['hotel'],
        'منتجع' => ['hotel'],
        'نُزل / هوستل' => ['hotel'],
        'بيت ضيافة' => ['hotel'],
        'فندق عائم / بوت نيلي' => ['hotel'],
    ],
    'training-courses' => [
        'مركز تدريب' => ['training'],
        // The six stage children were folded into «سنتر دروس» on 2026-08-10
        // («اطوها كالورش») and are options in «المراحل التعليمية» now. A
        // root-keyed branch map naming a folded child re-wires a row nothing
        // points at — see data/child_root_detachments.php.
    ],
    // offices review 2026-08-02: professions whose booking is a consultation /
    // site visit / online session; home services sell tasks; coworking books
    // rooms like the halls do.
    'offices' => [
        'هندسية' => ['business_consulting'],
        'ديكور' => ['business_consulting'],
        'تخليص جمركي' => ['business_consulting'],
        'طباعة' => ['business_consulting'],
        'خدمات منزلية' => ['services_tasks'],
        // was borrowing halls_events; now has its own branch (desk, private
        // office, meeting room) — see CoworkingAndHotelUnitsSeeder.
        'منطقة عمل مشتركة' => ['coworking'],
    ],
];
