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

        /*
        |----------------------------------------------------------------------
        | 2026-08-17, second pass — «توريد» و«تركيب»
        |----------------------------------------------------------------------
        | Three children, seventy-five links, and a decisions ledger of exactly
        | TWO rows: «خاص» withdrawn from إتصالات and برمجة on 2026-08-14 and kept
        | on أمن وسلامة. Nothing else in this root has ever been ruled on. The
        | twins match too — #233 برمجة and #261 برمجيات now hold the identical
        | five groups, which is what the 2026-08-16 pass below was for.
        |
        | What is missing is not in this root. **The platform has no word for
        | «توريد» or «تركيب».**
        |
        | Searched: «توريد» exists once, as «تجميع وتوريد» inside «نظام التصنيع»,
        | where it means a factory's assembly mode and not a supplier's quote.
        | «تركيب» exists thirteen times and every one of them is welded into a
        | LINE of some craft's own list — «تركيب دش»، «تركيب سخانات»، «تركيب
        | كوالين». The distinction is real enough that thirteen trades each
        | restated it inside their own vocabulary, which is this taxonomy's
        | oldest disease: an AXIS written out longhand in every list instead of
        | factored out once.
        |
        | ── Why it belongs here first ─────────────────────────────────────────
        |
        | «توريد وتركيب» is the phrase on the signage of every camera and
        | network company in Egypt, and it is a genuine second price on one row:
        | «كاميرات مراقبة» costs one thing if he sells you the kit and another
        | if he mounts and configures kit you already own. Same line, two
        | prices — which is the definition of a modifier and the same shape as
        | «نظام التصنيع» three entries above it.
        |
        | Today the lists conflate the two and cannot say which. «أجهزة اتصال
        | لاسلكي»، «طفايات ومعدات إطفاء»، «بوابات وحواجز أمنية» are goods;
        | «صيانة شبكات وأعطال»، «غرف مراقبة ومتابعة» are labour; they sit in one
        | group with no way to tell a supplier from an installer.
        |
        | The merchant list says the same thing out loud. Four of the fifteen
        | accounts on this root sell hardware — «موبيلات» and «isaac store» and
        | «الرحاب لخدمات الكمبيوتر والانترنت» under إتصالات, «الكترونيات» under
        | أمن وسلامة — filed into a root whose every line group is named «خدمات»
        | or «أنظمة». The supply half of the trade had nowhere to stand.
        |
        | ── Two rows, not three ───────────────────────────────────────────────
        |
        | No «توريد وتركيب». A merchant who does both ticks both, and the shop
        | window reads them as one phrase — the heading IS the combination, the
        | same reason «مكتب بسكرتارية» is not a third kind of office.
        |
        | And no «صيانة». All three children already carry maintenance as a
        | LINE they price — «عقود صيانة وفحص دوري»، «صيانة شبكات وأعطال»، «صيانة
        | وتطوير أنظمة قائمة» — so a modifier saying it again would put one word
        | in two groups where a merchant can tick one and not the other.
        |
        | ── Who gets it, and who does not ─────────────────────────────────────
        |
        | إتصالات #67 and أمن وسلامة #254. Their lines are hardware: cameras,
        | cable, PBXs, gates, extinguishers, detectors, radios.
        |
        | **برمجة #233 is left out.** Nobody supplies a website or installs a
        | mobile app; its twelve rows are مواقع، تطبيقات، ERP، UI/UX، استضافة —
        | written work, and the nearest thing to an install is «تكامل وربط
        | الأنظمة», which is already its own priced line. The same evidence-led
        | exclusion as «شحن بري وبحري وجوى» in the carrier root: the cut is read
        | from what the child's own list contains.
        */
        'نطاق العمل' => [
            'name_en' => 'Scope of Work',
            'price_role' => 'modifier',
            'children' => [67, 254],
            'options' => [
                'توريد' => 'Supply',
                'تركيب' => 'Installation',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | «راجع باقي أبناء التكنولوجيا بنفس الطريقة» — owner, 2026-08-16
    |--------------------------------------------------------------------------
    | Three children, all three fluent: a real `line`, a modifier and a
    | descriptive. What the walk found is the same shape as «قطع غيار سيارات»
    | and «أقمشة» before it — the SAME TRADE answering less here than it does
    | under another root.
    |
    | ── «نوع العملاء» ──
    | Every service child of «شركات» carries it and none of these three does.
    | «برمجيات» #261 is «برمجة» #233 under another root and has it; «أمن» #253
    | is «أمن وسلامة» #254 and has it. Nothing was withdrawn — the technology
    | file was written before that group existed, which was built for «مكاتب».
    |
    | These are B2B trades and «who do you serve» is precisely what a customer
    | narrows on: a software house that works for government bodies is a
    | different supplier from one that builds shop websites, and neither could
    | say so.
    |
    | ── «نظام التعاقد» ──
    | All three sell the same commercial shape — install a system, then keep it
    | running — and could name neither half of it. «بالمهمة» is the install,
    | «شهري» the support retainer, «سنوي» the maintenance contract, and every
    | one of the three is quoted on exactly those. «أمن» #253 already has this
    | axis under «شركات» while its twin here does not, which is the same
    | one-trade-two-answers gap the rest of this sweep has been closing.
    |
    | Not «بالساعة» and not the subscription rungs: nobody buys an ERP by the
    | hour, and «ربع سنوي» is a gym's ladder.
    |
    | #261 «برمجيات» is given the basis in company_child_vocabularies.php in the
    | same change, so the trade answers the same under both roots.
    */
    'links' => [
        67 => [
            'نوع العملاء' => 'all',
            'نظام التعاقد' => ['بالمهمة', 'شهري', 'سنوي'],
        ],
        233 => [
            'نوع العملاء' => 'all',
            'نظام التعاقد' => ['بالمهمة', 'شهري', 'سنوي'],
        ],
        254 => [
            'نوع العملاء' => 'all',
            'نظام التعاقد' => ['بالمهمة', 'شهري', 'سنوي'],
        ],
    ],
];
