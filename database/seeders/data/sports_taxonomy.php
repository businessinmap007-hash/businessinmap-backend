<?php

/*
|--------------------------------------------------------------------------
| Sports taxonomy — the Health pattern, applied to root 7 «الرياضة»
|--------------------------------------------------------------------------
| Root 7 had 14 children, but 11 of them were SPORTS (اسكواش، تنس، سباحة، سلة،
| كرة يد، هوكي، باليه…) rather than business types — every one of those 11
| carried zero accounts, exactly like the medical specialties did. The only
| real business types were جيم (4 accounts), مكملات غذائية (1) and ملاعب كرة.
|
| A sport behaves like a specialty, not like a property: a club HAS squash and
| swimming, it does not sell "squash" as a priced unit — it sells a court hour
| or a monthly subscription. So sports go to the OPTION axis (as in Health),
| not the item-type axis (as in Real Estate).
|
|   who the business IS       → child   : جيم، نادي رياضي، أكاديمية…
|   what DESCRIBES it         → option  : the sports below (multi-select)
|   what it SELLS for a price → item type: the existing `sports` branch
|                                          (ملعب خماسي، ملعب تنس، حارة سباحة…)
|
| The `sports` item-type branch is already correct and is NOT touched here.
| Note it is still mis-scoped per child — a gym is currently offered the nine
| football/tennis court types — but that is the separate «direct booking»
| plan's job (requires_bookable_item), not a taxonomy fix.
|
| Consumed by SportsRemodelSeeder.
*/

return [

    // ── Axis 1. جيم / مكملات غذائية / ملاعب كرة already exist and are real
    // business types; the rest are new homes for multi-sport venues.
    'children' => [
        ['name_ar' => 'جيم',             'name_en' => 'Gym',                 'existing' => true],
        ['name_ar' => 'ملاعب كرة',       'name_en' => 'Football Fields',     'existing' => true],
        ['name_ar' => 'مكملات غذائية',   'name_en' => 'Sports Supplements',  'existing' => true],
        ['name_ar' => 'نادي رياضي',      'name_en' => 'Sports Club',         'existing' => false],
        ['name_ar' => 'أكاديمية رياضية', 'name_en' => 'Sports Academy',      'existing' => false],
        ['name_ar' => 'حمام سباحة',      'name_en' => 'Swimming Pool',       'existing' => false],
    ],

    // ── Axis 2: what the venue offers. The first eleven are the current
    // children being re-filed; the rest are the everyday ones a gym or club
    // needs for the filter to be usable.
    'activity_group' => ['name_ar' => 'الأنشطة الرياضية', 'name_en' => 'Sports Activities'],

    'activities' => [
        // re-filed from the existing children
        'كرة قدم' => 'Football',
        'كرة سلة' => 'Basketball',
        'كرة طائرة' => 'Volleyball',
        'كرة يد' => 'Handball',
        'تنس' => 'Tennis',
        'اسكواش' => 'Squash',
        'سباحة' => 'Swimming',
        'هوكي' => 'Hockey',
        'باليه' => 'Ballet',
        'فنون الدفاع عن النفس' => 'Martial Arts',
        'مصارعة حرة ورومانية' => 'Wrestling',
        // the common ones the filter needs to be worth using
        'كمال أجسام / حديد' => 'Bodybuilding',
        'كارديو' => 'Cardio',
        'كروس فيت' => 'CrossFit',
        'يوجا' => 'Yoga',
        'بيلاتس' => 'Pilates',
        'زومبا' => 'Zumba',
        'بادل' => 'Padel',
        'جمباز' => 'Gymnastics',
        'ملاكمة' => 'Boxing',
        'كيك بوكسينج' => 'Kickboxing',
        'كاراتيه' => 'Karate',
        'تايكوندو' => 'Taekwondo',
        'تنس طاولة' => 'Table Tennis',
        'ألعاب قوى' => 'Athletics',
        // the ones Egypt actually plays and competes in — squash and fencing
        // are national strengths, and the Red Sea makes diving and water
        // sports everyday businesses rather than curiosities.
        'رفع أثقال' => 'Weightlifting',
        'مبارزة / شيش' => 'Fencing',
        'رماية' => 'Shooting',
        'فروسية / ركوب خيل' => 'Equestrian',
        'جودو' => 'Judo',
        'كونغ فو' => 'Kung Fu',
        'جوجيتسو / فنون قتالية مختلطة' => 'Jiu-Jitsu / MMA',
        'مواي تاي' => 'Muay Thai',
        'تجديف' => 'Rowing',
        'غوص / غطس' => 'Diving',
        'رياضات مائية' => 'Water Sports',
        'كرة ماء' => 'Water Polo',
        'سباحة إيقاعية' => 'Synchronized Swimming',
        'دراجات' => 'Cycling',
        'بادمنتون' => 'Badminton',
        'سبينينج' => 'Spinning',
        'آيروبكس' => 'Aerobics',
        'تزلج / اسكيت' => 'Skating',
        'رجبي' => 'Rugby',
        'باركور' => 'Parkour',
    ],

    // Every business-type child gets the whole activity pool to pick from,
    // except the supplements shop, which sells product and plays no sport.
    'skip_activity_pool' => ['مكملات غذائية'],

    // ── The sport children detached from root 7. Master rows are kept, so the
    // move is reversible; a child that still carries an account is skipped.
    'detach_children' => [
        'اسكواش', 'باليه', 'تنس', 'سباحة', 'سلة', 'فنون الدفاع عن النفس',
        'كرة طائرة', 'كرة قدم', 'كرة يد', 'مصارعه حرة وروماني', 'هوكي',
    ],

    // Maps a detached sport child onto the option that replaces it, so an
    // account moved off it keeps the information as a selected activity.
    'child_to_activity' => [
        'اسكواش' => 'اسكواش',
        'باليه' => 'باليه',
        'تنس' => 'تنس',
        'سباحة' => 'سباحة',
        'سلة' => 'كرة سلة',
        'فنون الدفاع عن النفس' => 'فنون الدفاع عن النفس',
        'كرة طائرة' => 'كرة طائرة',
        'كرة قدم' => 'كرة قدم',
        'كرة يد' => 'كرة يد',
        'مصارعه حرة وروماني' => 'مصارعة حرة ورومانية',
        'هوكي' => 'هوكي',
    ],

    // A named individual under a sport child reads as a coach, so the academy
    // is the safer default; the owner can re-point to نادي/ملاعب after.
    'business_migration_target' => 'أكاديمية رياضية',
];
