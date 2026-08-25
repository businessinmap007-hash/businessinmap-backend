<?php

/**
 * «راجع مجموعات الخدمات وأضف ما ينقصها» — المالك، 2026-08-25.
 *
 * The second half of the same pass. data/option_group_gaps.php filled the
 * lists of THINGS; this one fills the lists of WORK.
 *
 * ── What counts as a missing service ────────────────────────────────────────
 *
 * A row here is a job a customer asks for by name and pays for on its own.
 * «تظليل زجاج» is a row because a workshop quotes it, invoices it, and can be
 * booked for it and nothing else. «سريع» is not — that is how the job is done,
 * not which job it is, and it belongs in a modifier group.
 *
 * The test for a service list is the same one the goods lists take: could a
 * merchant put a price next to this line and mean something by it?
 *
 * ── Where a name was already spent ──────────────────────────────────────────
 *
 * `options.name_en` is unique platform-wide, and services overlap far more
 * than goods do — «دراسات جدوى» is already a marketing service, «تنسيق حدائق»
 * already belongs to the plant nursery, «تأسيس شركات» is already an
 * accountant's job. Those rows stayed where they are; nothing was cloned to
 * make a second copy of the same job under a second trade. Where the two jobs
 * genuinely differ, the narrower name carries the difference — a hotel's
 * «موقف سيارات» is not the garage's «انتظار يومي».
 */

return [

    'extend' => [

        /*
        |----------------------------------------------------------------------
        | المهن المكتبية
        |----------------------------------------------------------------------
        |
        | An Egyptian accountant's year is VAT returns and e-invoicing, and the
        | list had neither — it named the DEPARTMENTS of accounting («ضرائب»,
        | «مراجعة») rather than the jobs a client pays for.
        |
        */

        'تخصصات المحاسبة' => [
            'مسك دفاتر' => 'Bookkeeping',
            'القيمة المضافة' => 'VAT Filing',
            'الفاتورة الإلكترونية' => 'E-Invoicing',
            'تسوية بنكية' => 'Bank Reconciliation',
            'محاسبة تكاليف' => 'Cost Accounting',
            'تصفية شركات' => 'Company Liquidation',
            'تعديل السجل التجاري' => 'Commercial Register Amendments',
        ],

        'تخصصات المحاماة' => [
            'تنفيذ أحكام' => 'Judgment Enforcement',
            'بنوك وشيكات' => 'Banking & Cheque Cases',
            'ملكية فكرية وعلامات تجارية' => 'IP & Trademarks',
        ],

        'تخصصات الهندسة' => [
            'طاقة شمسية ومتجددة' => 'Solar & Renewable Engineering',
            'هندسة بيئية' => 'Environmental Engineering',
            'هندسة صناعية' => 'Industrial Engineering',
            'سلامة وإطفاء' => 'Fire Safety Engineering',
        ],

        'تخصصات الدعاية والإعلان' => [
            'أستاندات ورول أب' => 'Roll-ups & Stands',
            'فينيل وستيكرز سيارات' => 'Vehicle Wraps',
            'لافتات ليد ونيون' => 'LED & Neon Signs',
            'كتابة محتوى إعلاني' => 'Ad Copywriting',
            'مونتاج فيديو إعلاني' => 'Ad Video Editing',
            'هدايا دعائية' => 'Promotional Gifts',
        ],

        'تخصصات الديكور' => [
            'تصميم ثلاثي الأبعاد' => '3D Visualisation',
            'ديكور مطاعم وكافيهات' => 'Restaurant & Café Interiors',
            'ديكور مكاتب وشركات' => 'Office Interiors',
            'تنسيق وتأثيث شقق' => 'Home Styling',
            'حوائط وكلادينج داخلي' => 'Interior Wall Cladding',
        ],

        'خدمات التسويق' => [
            'تسويق عبر المؤثرين' => 'Influencer Marketing',
            'تسويق عقاري' => 'Real Estate Marketing',
            'تسويق بالبريد والرسائل' => 'Email & SMS Marketing',
        ],

        'خدمات البرمجة والتطوير' => [
            'ذكاء اصطناعي وأتمتة' => 'AI & Automation',
            'تحليل بيانات ولوحات معلومات' => 'Data & Dashboards',
            'ربط بوابات الدفع' => 'Payment Gateway Integration',
            'بوتات محادثة وخدمة عملاء' => 'Chatbots',
        ],

        'خدمات الاتصالات والشبكات' => [
            'شبكات نقاط البيع' => 'POS Networking',
            'فحص وتوثيق الشبكات' => 'Network Testing & Certification',
            'ربط الفروع' => 'Branch Interconnect',
        ],

        'خدمات الطباعة' => [
            'طباعة ثلاثية الأبعاد' => '3D Printing',
            'تصميم وإخراج' => 'Design & Layout',
            'طباعة على الأقمشة' => 'Textile Printing',
            'كروت دعوة' => 'Invitation Cards',
        ],

        /*
        |----------------------------------------------------------------------
        | الورش والصيانة
        |----------------------------------------------------------------------
        |
        | «تخصصات ورش الأجهزة» had seven rows for a trade that fixes everything
        | in a flat. A television and a microwave are two different technicians
        | and two different prices.
        |
        */

        'تخصصات ورش الأجهزة' => [
            'صيانة ميكروويف وأفران' => 'Microwave & Oven Repair',
            'صيانة شاشات وتلفزيونات' => 'TV Repair',
            'صيانة مكانس ومراوح' => 'Vacuum & Fan Repair',
            'صيانة أجهزة صغيرة' => 'Small Appliance Repair',
        ],

        'تخصصات ورش المعادن' => [
            'جلفنة وطلاء معادن' => 'Galvanising & Plating',
            'تشكيل صاج' => 'Sheet Metal Forming',
            'صب وسباكة معادن' => 'Metal Casting',
        ],

        'تخصصات ورش السيارات' => [
            'تظليل زجاج' => 'Window Tinting',
            'تركيب أنظمة صوت' => 'Car Audio Installation',
            'عزل صوت وحرارة' => 'Sound & Heat Insulation',
            'صيانة سيارات كهربائية' => 'EV Service',
        ],

        // «خدمات غسيل السيارات» is NOT extended here. It has a closed
        // authority in data/stray_child_vocabularies.php — eight rows, each
        // argued as a separate figure on the board outside an Egyptian
        // مغسلة — carried by exactly one child. That file is its owner.

        /*
        |----------------------------------------------------------------------
        | أعمال المبانى
        |----------------------------------------------------------------------
        |
        | These are the WORK lists, not the material lists — «عزل مائي» in
        | مواد البناء is the drum you buy; «عزل أسطح وحمامات» here is the man
        | who applies it.
        |
        */

        'أعمال الكهرباء' => [
            'تركيب ألواح شمسية' => 'Solar Panel Installation',
            'تركيب عدادات' => 'Meter Installation',
            'فحص وتقرير كهربائي' => 'Electrical Inspection',
        ],

        'أعمال السباكة' => [
            'تركيب فلاتر مياه' => 'Water Filter Installation',
            'تركيب سخانات شمسية' => 'Solar Water Heater Installation',
        ],

        'أعمال الدهانات' => [
            'رسم جداري' => 'Wall Murals',
            'دهانات عازلة للحرارة' => 'Heat-Reflective Paint',
        ],

        'أعمال الأرضيات' => [
            'تلميع وجلي رخام' => 'Marble Polishing',
            'تركيب نجيل طبيعي' => 'Natural Turf Laying',
            'عزل أسطح وحمامات' => 'Roof & Bathroom Waterproofing',
        ],

        'أعمال الستائر والتنجيد' => [
            'تفصيل مفارش وأغطية' => 'Custom Bedding',
            'تنظيف وغسيل ستائر' => 'Curtain Cleaning',
        ],

        'أعمال التبريد والتكييف' => [
            'تنظيف وتعقيم مكيفات' => 'AC Cleaning & Sanitising',
            'فك وتركيب ونقل مكيف' => 'AC Relocation',
            'عقود صيانة تبريد' => 'Cooling Maintenance Contracts',
        ],

        'أعمال المقاولات' => [
            'مقاولات صيانة' => 'Maintenance Contracting',
            'أعمال معدنية وهناجر' => 'Steel Structures & Hangars',
        ],

        'أعمال البنية التحتية' => [
            'شبكات غاز طبيعي' => 'Natural Gas Networks',
            'محطات رفع' => 'Pumping Stations',
        ],

        /*
        |----------------------------------------------------------------------
        | الخدمات الشخصية والمنزلية
        |----------------------------------------------------------------------
        */

        'الخدمات المنزلية' => [
            'نقل عفش وفك وتركيب' => 'House Moving',
            'تنظيف مطابخ ومداخن' => 'Kitchen & Hood Cleaning',
            'صيانة عامة سريعة' => 'Handyman Service',
        ],

        'خدمات الكوافير والتجميل' => [
            'حنة ونقش' => 'Henna',
            'تركيب رموش' => 'Lash Extensions',
            'تركيب أظافر' => 'Nail Extensions',
        ],

        /*
        |----------------------------------------------------------------------
        | النادى والفندق والمناسبات
        |----------------------------------------------------------------------
        |
        | «خدمات النادي الرياضي» was FOUR rows — a hammam, a trainer, a
        | nutritionist and a crèche — for a trade whose entire income is
        | memberships and classes. It was the thinnest priced list on the
        | platform.
        |
        */

        // «خدمات النادي الرياضي» is NOT extended here. It has a closed
        // per-child authority in data/stray_child_vocabularies.php — four rows,
        // argued one by one, carried by exactly five clubs, with «مدرب» #547
        // deliberately holding only «استشارة تغذية» out of it. Adding rows here
        // reached #547 too and undid that ruling; the group's owner is that
        // file, not this one.

        // «خدمات الفندق» is NOT extended here. It has a closed authority in
        // data/hospitality_child_vocabularies.php, which already grew it from
        // one row to eight and explains each one; a second file adding to it
        // is exactly the two-owners fight [[closed-vocabulary-maps]] warns
        // about.

        'خدمات تنظيم الحفلات' => [
            'ألعاب نارية وثلج جاف' => 'Fireworks & Dry Ice',
            'بالونات وتزيين' => 'Balloons & Decor',
            'مضيفات واستقبال' => 'Hostesses & Ushering',
        ],

        // «أنواع المناسبات» is NOT extended here. It has a closed per-child
        // scope in data/child_option_scopes.php — hardcoded option ids for
        // «قاعة مناسبات» #527 and «مركز مؤتمرات واجتماعات» #528 — and a row
        // added without a matching scope entry is stripped straight back off
        // by `ChildOptionScopeSeeder` on the next run.

        'خدمات التصوير' => [
            'تصوير طعام' => 'Food Photography',
            'جلسة تخرج' => 'Graduation Session',
            'تصوير ٣٦٠ درجة' => '360 Photography',
            'ريتاتش وتعديل صور' => 'Photo Retouching',
        ],

        'المراكب والرحلات النيلية' => [
            'عشاء على النيل' => 'Nile Dinner Cruise',
            'تأجير للتصوير' => 'Photo Shoot Charter',
        ],

        'خدمات السياحة والسفر' => [
            'رحلات مدارس وشركات' => 'School & Corporate Trips',
            'مرشد سياحي' => 'Tour Guide',
            'حجز قطارات وأتوبيسات' => 'Train & Bus Booking',
            'برامج شهر عسل' => 'Honeymoon Packages',
        ],

        /*
        |----------------------------------------------------------------------
        | الأمن والتأمين والشحن
        |----------------------------------------------------------------------
        |
        | «وسيلة الشحن» had three rows — بري، بحري، جوي — and an Egyptian
        | forwarder quotes rail, river and express courier in the same
        | conversation.
        |
        */

        // «وسيلة الشحن» is NOT extended here. Its own file
        // (data/shipping_child_vocabularies.php) is explicit: «Three rows and
        // no fourth» — بري، بحري، جوي — a closed ruling, not a gap.

        'خدمات الأمن والحراسة' => [
            'مرافقة نقل بضائع' => 'Cargo Escort',
            'حراسة مواقع إنشاءات' => 'Construction Site Guarding',
            'استشارات أمنية' => 'Security Consulting',
        ],

        'خدمات التخليص الجمركي' => [
            'شهادات صحية وحجر زراعي' => 'Health & Quarantine Certificates',
            'موافقات الجهات الرقابية' => 'Regulatory Approvals',
        ],

        // «أنواع التأمين» is NOT extended here. It has an owner —
        // data/company_child_vocabularies.php, where it was first created —
        // and the three new lines went into that file's own list instead.

        /*
        |----------------------------------------------------------------------
        | التعليم
        |----------------------------------------------------------------------
        */

        'اللغات' => [
            'تركي' => 'Turkish',
            'كوري' => 'Korean',
            'برتغالي' => 'Portuguese',
        ],
    ],
];
