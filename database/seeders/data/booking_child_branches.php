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
        'اصلاح زجاج السيارات' => ['services_tasks'],
        'باب وشباك' => ['services_tasks'],
        'باركيه' => ['services_tasks'],
        'بناء وواجهات حجرية' => ['services_tasks'],
        'تكسير ونحت' => ['services_tasks'],
        'جبس وديكورات' => ['services_tasks'],
        'جبس وكرانيش' => ['services_tasks'],
        'جبسيوم بورد' => ['services_tasks'],
        'جي أر سي' => ['services_tasks'],
        'حداد' => ['services_tasks'],
        'خدمات نظافة' => ['services_tasks'],
        'دش وأقمار صناعية' => ['services_tasks'],
        'رخام وجرانيت' => ['services_tasks'],
        'سائق' => ['services_tasks'],
        'سباك' => ['services_tasks'],
        'صيانة اجهزة منزلية' => ['services_tasks'],
        'صيانة تكيف' => ['services_tasks'],
        'عامل بناء' => ['services_tasks'],
        'فني الوميتال' => ['services_tasks'],
        'فني ستائر و تنجيد' => ['services_tasks'],
        'كهربائي' => ['services_tasks'],
        'كوافير' => ['beauty_care'],
        'مأذون شرعى' => ['services_tasks'],
        'مبلط' => ['services_tasks'],
        'مبيض محارة' => ['services_tasks'],
        'منجد' => ['services_tasks'],
        'نجار تنده' => ['services_tasks'],
        'نجار موبيليا' => ['services_tasks'],
        'نقاش' => ['services_tasks'],
    ],

    // ── الرياضة ──
    'sports' => [
        'اسكواش' => ['sports'],
        'باليه' => ['sports'],
        'تنس' => ['sports'],
        'جيم' => ['sports'],
        'سباحة' => ['sports'],
        'سلة' => ['sports'],
        'فنون الدفاع عن النفس' => ['sports'],
        'كرة طائرة' => ['sports'],
        'كرة قدم' => ['sports'],
        'كرة يد' => ['sports'],
        'مصارعه حرة وروماني' => ['sports'],
        'مكملات غذائية' => ['sports'],
        'ملاعب كرة' => ['sports'],
        'هوكي' => ['sports'],
    ],

    // ── فنون و ترفية ──
    'arts-entertainment' => [
        'انترنت كافيه' => ['entertainment_leisure'],
        'بلاي ستيشن' => ['entertainment_leisure'],
        'بلياردو' => ['entertainment_leisure'],
        'بولينج' => ['entertainment_leisure'],
        'بينج بونج' => ['entertainment_leisure'],
        'رحلات بحرية' => ['entertainment_leisure', 'tourism_travel'],
        'رحلات نيلية' => ['entertainment_leisure', 'tourism_travel'],
        'رحلة صيد سمك' => ['entertainment_leisure', 'tourism_travel'],
        'فوتوجرافر' => ['entertainment_leisure'],
        'مركز ترفيهي' => ['entertainment_leisure'],
    ],

    // ── ورش ومراكز صيانة ──
    'workshops' => [
        'Cnc' => ['services_tasks'],
        'آثاث' => ['services_tasks'],
        'أويمجى' => ['services_tasks'],
        'ابواب سيارات' => ['services_tasks'],
        'استانلس ومعدات مطاعم' => ['services_tasks'],
        'استورجى' => ['services_tasks'],
        'الكريتال' => ['services_tasks'],
        'باب وشباك' => ['services_tasks'],
        'تبريد وتكييف' => ['services_tasks'],
        'تصليح أجهزة كهربائية' => ['services_tasks'],
        'تصليح غسالات وبتوجازات' => ['services_tasks'],
        'تنجيد' => ['services_tasks'],
        'حداد' => ['services_tasks'],
        'سروجي' => ['services_tasks'],
        'سمكري' => ['services_tasks'],
        'عفشجى' => ['services_tasks'],
        'فيبر جلاس' => ['services_tasks'],
        'كهربائي سيارات' => ['services_tasks'],
        'كوتش' => ['services_tasks'],
        'مخرطة' => ['services_tasks'],
        'مركز سيارات' => ['services_tasks'],
        'مطابخ و دريسنج' => ['services_tasks'],
        'ميكانيكي' => ['services_tasks'],
        'نجار باب وشباك' => ['services_tasks'],
    ],

    // ── قاعات ──
    'halls' => [
        'اجتماعات' => ['halls_events'],
        'حفلات' => ['halls_events'],
        'مؤتمرات' => ['halls_events'],
        'ندوات مفتوحة' => ['halls_events'],
    ],

    // ── دورات و  تدريب ──
    'training-courses' => [
        'أكاديمية تعليم قص الشعر' => ['training'],
        'تعليم صيانة' => ['training'],
        'حضانات' => ['training'],
        'دورات و تدريب' => ['training'],
        'سنتر دروس' => ['training'],
        'قاعات تدريب' => ['training'],
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
    'property-and-land' => [
        'أرض' => ['real_estate'],
        'أرض زراعية' => ['real_estate'],
        'تسويق عقاري' => ['real_estate'],
        'شقة' => ['real_estate'],
        'عمارة' => ['real_estate'],
        'ڤيلا' => ['real_estate'],
        'محل' => ['real_estate'],
        'مزرعة' => ['real_estate'],
        'مصنع' => ['real_estate'],
        'معرض' => ['real_estate'],
        'مكتب' => ['real_estate'],
        'ورشة' => ['real_estate'],
    ],

    // ── الصحة ──
    'health' => [
        'أسنان' => ['clinic', 'health_medical'],
        'أمراض روماتيزمية ومزمنة' => ['clinic', 'health_medical'],
        'اطفال وحديثي الولادة' => ['clinic', 'health_medical'],
        'امراض الدم' => ['clinic', 'health_medical'],
        'انف وأذن وحنجرة' => ['clinic', 'health_medical'],
        'اورام' => ['clinic', 'health_medical'],
        'باطنه' => ['clinic', 'health_medical'],
        'تخسيس وتغذية' => ['clinic', 'health_medical'],
        'جراحة أطفال' => ['clinic', 'health_medical'],
        'جراحة أورام' => ['clinic', 'health_medical'],
        'جراحة اوعية دموية' => ['clinic', 'health_medical'],
        'جراحة تجميل' => ['clinic', 'health_medical'],
        'جراحة سمنة ومناظير' => ['clinic', 'health_medical'],
        'جراحة عامة' => ['clinic', 'health_medical'],
        'جراحة عمود فقري' => ['clinic', 'health_medical'],
        'جراحة عيون' => ['clinic', 'health_medical'],
        'جراحة مخ واعصاب' => ['clinic', 'health_medical'],
        'جلديه وتناسليه' => ['clinic', 'health_medical'],
        'جهاز هضمي ومناظير' => ['clinic', 'health_medical'],
        'حساسية ومناعة' => ['clinic', 'health_medical'],
        'حقن مجهري واطفال انابيب' => ['clinic', 'health_medical'],
        'ذكورة وعقم' => ['clinic', 'health_medical'],
        'رمد' => ['clinic', 'health_medical'],
        'سكر وغدد صماء' => ['clinic', 'health_medical'],
        'سمعيات' => ['clinic', 'health_medical'],
        'صدر' => ['clinic', 'health_medical'],
        'صيدلية' => ['clinic', 'health_medical'],
        'طب الأسرة' => ['clinic', 'health_medical'],
        'طب المسنين' => ['clinic', 'health_medical'],
        'طب تقويمي' => ['clinic', 'health_medical'],
        'عظام' => ['clinic', 'health_medical'],
        'علاج الآلام' => ['clinic', 'health_medical'],
        'علاج طبيعي واصابات ملاعب' => ['clinic', 'health_medical'],
        'عيون' => ['clinic', 'health_medical'],
        'قلب وأوعية دموية' => ['clinic', 'health_medical'],
        'كبد' => ['clinic', 'health_medical'],
        'كلى' => ['clinic', 'health_medical'],
        'مخ وأعصاب' => ['clinic', 'health_medical'],
        'مراكز أشعة' => ['clinic', 'health_medical'],
        'مسالك بوليه' => ['clinic', 'health_medical'],
        'معمل تحاليل' => ['clinic', 'health_medical'],
        'ممارسة عامة' => ['clinic', 'health_medical'],
        'نساء و ولادة' => ['clinic', 'health_medical'],
        'نطق وتخاطب' => ['clinic', 'health_medical'],
    ],

    // ── فنادق سياحية ──
    'tourist-hotels' => [
        '1 ⭐' => ['hotel'],
        '2⭐⭐' => ['hotel'],
        '3 ⭐⭐ ⭐' => ['hotel'],
        '4 ⭐⭐ ⭐ ⭐' => ['hotel'],
        '5 ➕ ⭐ ⭐ ⭐ ⭐ ⭐' => ['hotel'],
        '5 ⭐ ⭐ ⭐ ⭐ ⭐' => ['hotel'],
    ],

    // ── كوافير ──
    'hair-dresser' => [
        'كوافير حريمى' => ['beauty_care'],
        'كوافير رجالي' => ['beauty_care'],
    ],
];
