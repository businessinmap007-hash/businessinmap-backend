<?php

/*
|--------------------------------------------------------------------------
| «الصحة» — the root that could say everything except what it is like
|--------------------------------------------------------------------------
| Owner, 2026-08-11: «راجع الجذور اللى مش مكتملة فى باقي المحاور».
|
| Every child here can already name its trade, and precisely: «عيادة» carries
| «تخصصات طبية» (41 specialties), «معمل تحاليل» carries «التحاليل الطبية» (28),
| «مراكز أشعة» carries «أنواع الأشعة». The three-axis remodel did that work.
|
| **What six of the seven had was NOTHING descriptive.** A patient choosing
| between two clinics could compare specialty against specialty and learn
| nothing else: not whether either takes his insurance, not whether either has
| a lift, not whether one is open at night. The platform's shared descriptives
| are all goods-shaped — «الاستبدال والإرجاع», «التسليم والاستلام» — and say
| nothing about a clinic.
|
| So: «تسهيلات ومرافق طبية», descriptive, on all seven. It is the health
| counterpart of «نوع العملاء» under «مكاتب» — the axis a searcher filters on
| that never changes a price.
|
| ── The modifier is left alone, and that is deliberate ────────────────────
|
| The audit also reports six of the seven with no `modifier`. A modifier exists
| where the SAME line prices two ways, and «كشف» does not: what changes a
| consultation's price here is the specialty, and the specialty is already the
| line. Inventing one would be the noise this whole sweep has been removing.
*/

return [

    'root' => 'health',

    'name_en_suffix' => 'Medical',

    'groups' => [

        'تسهيلات ومرافق طبية' => [
            'name_en' => 'Medical Facilities & Access',
            'price_role' => 'descriptive',
            'children' => [163, 215, 252, 513, 514, 515, 542],
            'options' => [
                'يقبل التأمين الطبي' => 'Accepts Insurance',
                'حجز مواعيد أونلاين' => 'Online Booking',
                'خدمة طوارئ ٢٤ ساعة' => '24h Emergency',
                'صيدلية داخلية' => 'In-house Pharmacy',
                'معمل تحاليل داخلي' => 'In-house Laboratory',
                'أشعة داخلية' => 'In-house Imaging',
                'زيارة منزلية' => 'Home Visits',
                'مدخل لذوي الاحتياجات' => 'Accessible Entrance',
                'انتظار سيارات' => 'Parking',
                'قسم سيدات' => 'Women\'s Section',
            ],
        ],
    ],
];
