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
    'training-courses' => [
        'مركز تدريب' => ['training'],
    ],
];
