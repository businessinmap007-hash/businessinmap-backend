<?php

/*
|--------------------------------------------------------------------------
| Health taxonomy — the three-axis remodel (approved 2026-08-02)
|--------------------------------------------------------------------------
| Health used to model 44 category children, but 41 of them were medical
| SPECIALTIES (عظام، باطنة، قلب…) rather than business types. That made the
| single-child signup unworkable: a hospital with 10 specialties could only
| ever register under one of them, which is why just 8 of the 44 children ever
| carried a business.
|
| The correct axes (the established price test):
|   who the business IS      → category child        (مستشفى، عيادة، معمل…)
|   what DESCRIBES it        → option (many-to-many) (the 41 specialties)
|   what it SELLS for a price→ platform item type    (كشف، متابعة، أشعة…)
|
| Specialties therefore move to the option axis, which is already many-to-many
| in two layers: `category_child_option` = what a child MAY carry (the pool a
| business picks from at signup), `option_user` = what a given business
| ACTUALLY carries (what DiscoveryController filters on, AND-semantics).
|
| Consumed by HealthRemodelSeeder. Adding a specialty later = append to
| SPECIALTIES and re-run; the seeder is idempotent and never deletes.
*/

return [

    // ── Axis 1: who the business is. `item_type_key` marks the ones that are
    // being rescued from the WRONG axis (they exist today as booking item
    // types in the `health_medical` group, which is why مستشفى/عيادة were
    // never selectable at signup). `carries_specialties` decides whether the
    // whole specialty pool gets attached to that child.
    'children' => [
        ['name_ar' => 'مستشفى',      'name_en' => 'Hospital',        'item_type_key' => 'hospital',    'carries_specialties' => true],
        ['name_ar' => 'عيادة',       'name_en' => 'Clinic',          'item_type_key' => 'clinic',      'carries_specialties' => true],
        ['name_ar' => 'مركز طبي',    'name_en' => 'Medical Center',  'item_type_key' => null,          'carries_specialties' => true],
        // «نادي صحي» was here until 2026-08-09 and is now a child of «الرياضة»
        // beside جيم and نادي رياضي — the root «الصحة» is medical FACILITIES.
        // It is NOT re-listed here: an add-only seeder that still names a child
        // the owner has moved re-attaches it on the next run, which is exactly
        // what happened and what turned ChildRootMovesTest red.
        // See [[seeder-must-withdraw]].
        // Already correct business types — kept exactly as they are.
        ['name_ar' => 'صيدلية',      'name_en' => 'Pharmacy',        'item_type_key' => null,          'carries_specialties' => false, 'existing' => true],
        ['name_ar' => 'معمل تحاليل', 'name_en' => 'Medical Lab',     'item_type_key' => null,          'carries_specialties' => false, 'existing' => true],
        ['name_ar' => 'مراكز أشعة',  'name_en' => 'Radiology Center','item_type_key' => null,          'carries_specialties' => false, 'existing' => true],
    ],

    // ── Axis 2: what describes the business. These 41 are the current health
    // children; they become options under a new group and are detached from
    // the Health root. The master rows are NEVER deleted — only the
    // parent↔child link is removed, so the move stays reversible.
    'specialty_group' => ['name_ar' => 'تخصصات طبية', 'name_en' => 'Medical Specialties'],

    'specialties' => [
        'أسنان' => 'Dentistry',
        'أمراض روماتيزمية ومزمنة' => 'Rheumatology & Chronic Diseases',
        'اطفال وحديثي الولادة' => 'Pediatrics & Neonatology',
        'امراض الدم' => 'Hematology',
        'انف وأذن وحنجرة' => 'ENT',
        'اورام' => 'Oncology',
        'باطنه' => 'Internal Medicine',
        'تخسيس وتغذية' => 'Weight Loss & Nutrition',
        'جراحة أطفال' => 'Pediatric Surgery',
        'جراحة أورام' => 'Surgical Oncology',
        'جراحة اوعية دموية' => 'Vascular Surgery',
        'جراحة تجميل' => 'Plastic Surgery',
        'جراحة سمنة ومناظير' => 'Bariatric & Laparoscopic Surgery',
        'جراحة عامة' => 'General Surgery',
        'جراحة عمود فقري' => 'Spine Surgery',
        'جراحة عيون' => 'Eye Surgery',
        'جراحة مخ واعصاب' => 'Neurosurgery',
        'جلديه وتناسليه' => 'Dermatology & Venereology',
        'جهاز هضمي ومناظير' => 'Gastroenterology & Endoscopy',
        'حساسية ومناعة' => 'Allergy & Immunology',
        'حقن مجهري واطفال انابيب' => 'IVF & ICSI',
        'ذكورة وعقم' => 'Andrology & Infertility',
        'رمد' => 'Ophthalmology',
        'سكر وغدد صماء' => 'Diabetes & Endocrinology',
        'سمعيات' => 'Audiology',
        'صدر' => 'Pulmonology',
        'طب الأسرة' => 'Family Medicine',
        'طب المسنين' => 'Geriatrics',
        'طب تقويمي' => 'Orthodontics',
        'عظام' => 'Orthopedics',
        'علاج الآلام' => 'Pain Management',
        'علاج طبيعي واصابات ملاعب' => 'Physiotherapy & Sports Injuries',
        'عيون' => 'Ophthalmology (General)',
        'قلب وأوعية دموية' => 'Cardiology & Vascular',
        'كبد' => 'Hepatology',
        'كلى' => 'Nephrology',
        'مخ وأعصاب' => 'Neurology',
        'مسالك بوليه' => 'Urology',
        'ممارسة عامة' => 'General Practice',
        'نساء و ولادة' => 'Obstetrics & Gynecology',
        'نطق وتخاطب' => 'Speech Therapy',
        // «راجع باقى مجموعات الخدمات الطبية والصحية» — المالك، 2026-08-25.
        // Four specialties an Egyptian hospital or clinic answers to by name
        // and the original 41 (all inherited from the pre-remodel children)
        // never carried, because none of them was ever a registered business
        // TYPE on its own.
        'طب نفسي' => 'Psychiatry',
        'طب الطوارئ' => 'Emergency Medicine',
        'الأمراض المعدية' => 'Infectious Diseases',
        'علاج الإدمان' => 'Addiction Medicine',
    ],

    // ── A second, narrower option pool: the imaging modalities a radiology
    // centre actually owns. Same axis as the specialties (descriptive,
    // multi-select, filterable) — «هذا المركز عنده رنين» — while the priced
    // offering stays the existing `clinic` item type «أشعة / تصوير».
    // Attached only to مراكز أشعة, so مستشفى/عيادة don't get imaging noise.
    'imaging_group' => ['name_ar' => 'أنواع الأشعة', 'name_en' => 'Imaging Modalities'],

    'imaging_child' => 'مراكز أشعة',

    'imaging_modalities' => [
        'أشعة سينية عادية' => 'X-Ray',
        'أشعة بالصبغة' => 'Contrast Radiography',
        'أشعة مقطعية' => 'CT Scan',
        'رنين مغناطيسي' => 'MRI',
        'موجات صوتية / سونار' => 'Ultrasound',
        'سونار رباعي الأبعاد' => '4D Ultrasound',
        'دوبلر' => 'Doppler',
        'أشعة الثدي / ماموجرام' => 'Mammography',
        'قياس كثافة العظام' => 'Bone Densitometry (DEXA)',
        'تصوير الأوعية' => 'Angiography',
        'أشعة تداخلية' => 'Interventional Radiology',
        'أشعة أسنان بانورامية' => 'Panoramic Dental X-Ray',
        'طب نووي' => 'Nuclear Medicine',
        // PET-CT reached ordinary oncology imaging in Egypt (57357, NCI…)
        // without ever getting a row of its own — it was answered by «طب
        // نووي», which is the department, not the scan a patient is booked
        // for by name.
        'بيت سكان (PET-CT)' => 'PET-CT Scan',
    ],

    // ── Third narrow pool: the lab tests a معمل تحاليل performs — same axis
    // logic as imaging (descriptive, multi-select, filterable); the priced
    // offering stays the clinic item type «تحليل / اختبار». Also attached to
    // مستشفى since hospitals run their own labs.
    'lab_group' => ['name_ar' => 'التحاليل الطبية', 'name_en' => 'Medical Lab Tests'],

    'lab_children' => ['معمل تحاليل', 'مستشفى'],

    'lab_tests' => [
        'صورة دم كاملة CBC' => 'Complete Blood Count',
        'سكر صائم وفاطر' => 'Blood Glucose',
        'سكر تراكمي HbA1c' => 'HbA1c',
        'وظائف كبد' => 'Liver Function',
        'وظائف كلى' => 'Kidney Function',
        'دهون وكوليسترول' => 'Lipid Profile',
        'غدة درقية' => 'Thyroid Profile',
        'فيتامين د' => 'Vitamin D',
        'فيتامين ب12' => 'Vitamin B12',
        'حديد ومخزون الحديد' => 'Iron & Ferritin',
        'إنزيمات القلب' => 'Cardiac Enzymes',
        'سرعة الترسيب ESR' => 'ESR',
        'بروتين سي التفاعلي CRP' => 'CRP',
        'سيولة الدم INR' => 'Coagulation / INR',
        'فيروسات الكبد' => 'Hepatitis Panel',
        'هرمونات' => 'Hormone Panel',
        'دلالات أورام' => 'Tumor Markers',
        'تحليل حمل' => 'Pregnancy Test',
        'بول كامل' => 'Urinalysis',
        'براز كامل' => 'Stool Analysis',
        'مزرعة وحساسية' => 'Culture & Sensitivity',
        'حمض يوريك' => 'Uric Acid',
        'كالسيوم وأملاح' => 'Calcium & Electrolytes',
        'تحليل PCR' => 'PCR Test',
        'فصيلة الدم' => 'Blood Typing',
        'تحليل مخدرات' => 'Drug Screening',
        'ما قبل الزواج' => 'Premarital Panel',
        'باقة فحص شامل' => 'Full Checkup Panel',
        // Two tests a patient asks a lab for by name, not by mechanism —
        // «تحليل PCR» already covers the METHOD; a patient does not ask for
        // «PCR», he asks for «كوفيد».
        'مسحة كوفيد (PCR / سريع)' => 'COVID-19 Test (PCR / Rapid)',
        'تحليل الحساسية' => 'Allergy Test',
    ],

    // ── Where the 8 businesses currently registered under a specialty child
    // get moved. Their former specialty is preserved as a selected option in
    // `option_user`, so nothing is lost — only re-filed onto the right axis.
    // Owner can re-point any of these to مستشفى from the admin panel after.
    'business_migration_target' => 'عيادة',

    // ── Axis 3 cleanup: item types in the `health_medical` booking group that
    // are business types (rescued to axis 1 above) or belong elsewhere.
    // 'medical_tourism' becomes an option; 'pharma_materials' is a retail
    // product, not a booking, so it leaves the booking group. `clinic` group
    // (كشف، متابعة، أشعة…) is already correct and is NOT touched.
    'health_medical_disposition' => [
        'hospital' => 'to_child',
        'clinic' => 'to_child',
        'health_club' => 'to_child',
        'house_visit' => 'duplicate_of_clinic_group',
        'medical_tourism' => 'to_option',
        'pharmaceutical_materials' => 'drop_from_booking',
    ],
];
