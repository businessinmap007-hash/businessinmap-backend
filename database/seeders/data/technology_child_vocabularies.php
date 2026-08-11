<?php

/*
|--------------------------------------------------------------------------
| What each child of «تكنولوجيا» actually sells
|--------------------------------------------------------------------------
| Owner, 2026-08-11: «اكمل معجم أمن وسلامة تحت تكنولوجيا».
|
| He named one child. **All three were in the same state** — إتصالات (6
| merchants), برمجة (5) and أمن وسلامة (4) each carried «الدفع والسداد» and
| «نمط تقديم الخدمة» and nothing else. Fifteen businesses on a root where not
| one of them could say what it installs, writes or protects. Leaving two
| identical holes either side of the one he pointed at would have meant coming
| back for them, so all three are here.
|
| ── The line each root neighbour must not cross ───────────────────────────
|
| **أمن وسلامة #254 is the SYSTEMS half. «أمن» #253 under «مكاتب» is the
| MANPOWER half** — guards, patrols, cash in transit — and it got its own list
| the same day. The market sells them together and the platform keeps them
| apart, so «حراسة» appears in neither list here and «كاميرات مراقبة» appears in
| neither list there. A business doing both stands as two, which is what the
| two children are for.
|
| **Cyber security sits with برمجة, not with أمن وسلامة.** A firewall is written,
| not bolted to a wall; #254 is fire, intrusion and access hardware.
|
| **«دش وأقمار صناعية» is a child of «مهن وحرفيين» #251**, so satellite work is
| not a telecom line here — the same rule that kept the plumbers out of the
| home-services agency.
|
| **Accounting SOFTWARE is not accountancy.** «أنظمة محاسبية ومخازن» is a
| product برمجة sells; «تخصصات المحاسبة» on محاسبة #10 is a service a person
| performs. Same words, different trades.
*/

return [

    'root' => 'technology',

    // «Tech» rather than «Office»: it disambiguates a name_en another group
    // already owns, and these rows are the technology reading of it.
    'name_en_suffix' => 'Tech',

    'groups' => [

        'أنظمة الأمن والسلامة' => [
            'name_en' => 'Security & Safety Systems',
            'price_role' => 'line',
            'children' => [254],
            'options' => [
                'كاميرات مراقبة' => 'CCTV Cameras',
                'أنظمة إنذار السرقة' => 'Intruder Alarms',
                'أنظمة إنذار الحريق' => 'Fire Alarm Systems',
                'أنظمة إطفاء الحريق' => 'Fire Suppression Systems',
                'طفايات ومعدات إطفاء' => 'Extinguishers & Firefighting Kit',
                'كاشفات دخان وغاز' => 'Smoke & Gas Detectors',
                'أنظمة التحكم في الدخول' => 'Access Control',
                'بصمة وحضور وانصراف' => 'Attendance & Biometrics',
                'إنتركم وفيديو إنتركم' => 'Intercom Systems',
                'بوابات وحواجز أمنية' => 'Gates & Barriers',
                'أجهزة تفتيش وكشف' => 'Screening Equipment',
                'غرف مراقبة ومتابعة' => 'Control Rooms',
                'عقود صيانة وفحص دوري' => 'Maintenance Contracts',
                'استشارات وتراخيص السلامة' => 'Safety Compliance & Permits',
            ],
        ],

        'خدمات الاتصالات والشبكات' => [
            'name_en' => 'Telecom & Network Services',
            'price_role' => 'line',
            'children' => [67],
            'options' => [
                'شبكات وواي فاي' => 'Networks & WiFi',
                'تمديد كابلات وألياف بصرية' => 'Cabling & Fibre',
                'سنترالات ومقاسم هاتفية' => 'PBX & Phone Systems',
                'أنظمة نداء وصوتيات' => 'Paging & PA Systems',
                'مقويات إشارة' => 'Signal Boosters',
                'أجهزة اتصال لاسلكي' => 'Two-Way Radios',
                'أنظمة مؤتمرات فيديو' => 'Video Conferencing',
                'اشتراكات إنترنت' => 'Internet Subscriptions',
                'صيانة شبكات وأعطال' => 'Network Maintenance',
            ],
        ],

        'خدمات البرمجة والتطوير' => [
            'name_en' => 'Software Development Services',
            'price_role' => 'line',
            'children' => [233],
            'options' => [
                'مواقع إلكترونية' => 'Websites',
                'متاجر إلكترونية' => 'E-commerce Stores',
                'تطبيقات موبايل' => 'Mobile Apps',
                'أنظمة إدارة موارد' => 'ERP Systems',
                'أنظمة نقاط البيع' => 'POS Systems',
                'أنظمة محاسبية ومخازن' => 'Accounting & Inventory Software',
                'استضافة ودومينات' => 'Hosting & Domains',
                'تصميم واجهات وتجربة المستخدم' => 'UI / UX Design',
                'تكامل وربط الأنظمة' => 'Systems Integration',
                'أمن معلومات وحماية سيبرانية' => 'Cyber Security',
                'صيانة وتطوير أنظمة قائمة' => 'Maintenance & Enhancement',
                // Owner, 2026-08-12. NOT «استضافة ودومينات» above: that is
                // reselling someone else's box by the year, this is setting one
                // up, hardening it and running it — a priced job with a scope,
                // and the one every other row on this list eventually needs.
                'برمجة سيرفرات وإدارتها' => 'Server Setup & Administration',
            ],
        ],
    ],
];
