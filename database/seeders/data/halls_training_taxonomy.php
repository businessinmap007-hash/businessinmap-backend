<?php

/*
|--------------------------------------------------------------------------
| Halls + Training taxonomy — the last two sections carrying the disease
|--------------------------------------------------------------------------
| Root 11 «قاعات»: the children were EVENT TYPES (اجتماعات، حفلات، مؤتمرات،
| ندوات مفتوحة) — but a hall hosts a meeting, it does not "be" one. The hall
| item types (قاعة عادية، قاعة VIP، قاعة أفراح…) in the `halls_events` branch
| are already correct and are not touched. قاعات تدريب survives as a child:
| a training-hall venue is genuinely a distinct business.
|
| Root 12 «دورات و تدريب»: mostly healthy (حضانات، سنتر دروس are real business
| types). The sick ones are أكاديمية تعليم قص الشعر and تعليم صيانة — those are
| FIELDS a center teaches, not kinds of business — plus the generic «دورات و
| تدريب» child that just repeats the root's own name. The priced course item
| types (لغات، مواد دراسية، دورات مهنية…) in the `training` branch stay as they
| are: a course sold at a price is exactly axis 3.
|
| Consumed by HallsTrainingRemodelSeeder.
*/

return [

    'halls' => [
        'root_id' => 11,

        'children' => [
            ['name_ar' => 'قاعة مناسبات',          'name_en' => 'Events Hall',                'existing' => false],
            ['name_ar' => 'مركز مؤتمرات واجتماعات', 'name_en' => 'Conference & Meeting Center', 'existing' => false],
            ['name_ar' => 'قاعات تدريب',            'name_en' => 'Training Halls',             'existing' => true],
        ],

        'event_group' => ['name_ar' => 'أنواع المناسبات', 'name_en' => 'Event Types'],

        'events' => [
            'أفراح' => 'Weddings',
            'خطوبة' => 'Engagements',
            'عيد ميلاد' => 'Birthdays',
            'حفلات تخرج' => 'Graduations',
            'مؤتمرات' => 'Conferences',
            'اجتماعات عمل' => 'Business Meetings',
            'ندوات' => 'Seminars',
            'معارض' => 'Exhibitions',
            'حفلات موسيقية' => 'Concerts',
            'عزاء' => 'Funerals',
            'إفطار جماعي' => 'Group Iftar',
            'تصوير مناسبات' => 'Event Photography Sessions',
        ],

        // Which children get the event pool; the training hall hosts
        // sessions, not weddings.
        'skip_event_pool' => ['قاعات تدريب'],

        'detach_children' => ['اجتماعات', 'حفلات', 'مؤتمرات', 'ندوات مفتوحة'],

        'child_to_event' => [
            'اجتماعات' => 'اجتماعات عمل',
            'حفلات' => 'أفراح',
            'مؤتمرات' => 'مؤتمرات',
            'ندوات مفتوحة' => 'ندوات',
        ],

        // Meeting/conference accounts read as conference venues, not wedding
        // halls, so that is where the detached-children accounts land.
        'business_migration_target' => 'مركز مؤتمرات واجتماعات',
    ],

    'training' => [
        'root_id' => 12,

        'children' => [
            ['name_ar' => 'حضانات',      'name_en' => 'Nurseries',        'existing' => true],
            ['name_ar' => 'سنتر دروس',   'name_en' => 'Tutoring Center',  'existing' => true],
            ['name_ar' => 'مركز تدريب',  'name_en' => 'Training Center',  'existing' => false],
        ],

        'field_group' => ['name_ar' => 'مجالات التدريب', 'name_en' => 'Training Fields'],

        'fields' => [
            'لغات' => 'Languages',
            'حاسب آلي' => 'Computer Skills',
            'برمجة' => 'Programming',
            'تصميم وجرافيك' => 'Design & Graphics',
            'تسويق' => 'Marketing',
            'محاسبة' => 'Accounting',
            'إدارة أعمال' => 'Business Administration',
            'تنمية بشرية' => 'Personal Development',
            'قص شعر وتجميل' => 'Hairdressing & Beauty',
            'صيانة' => 'Maintenance & Repair',
            'طبخ' => 'Cooking',
            'خياطة وتفصيل' => 'Sewing & Tailoring',
            'موسيقى' => 'Music',
            'رسم وفنون' => 'Art & Drawing',
            'دروس مدرسية' => 'School Subjects',
            'تحفيظ قرآن' => 'Quran Memorization',
        ],

        // The nursery cares for kids; it does not teach fields for a fee.
        'skip_field_pool' => ['حضانات'],

        'detach_children' => ['أكاديمية تعليم قص الشعر', 'تعليم صيانة', 'دورات و تدريب'],

        'child_to_field' => [
            'أكاديمية تعليم قص الشعر' => 'قص شعر وتجميل',
            'تعليم صيانة' => 'صيانة',
            'دورات و تدريب' => null,
        ],

        'business_migration_target' => 'مركز تدريب',
    ],
];
