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
                // «زيارة منزلية» left this list on 2026-08-16 — see
                // option_group_splits.php. It is not named here any more and
                // must not be: this seeder matches an option by GROUP and
                // Arabic name, so the name left behind would build a second
                // «زيارة منزلية» inside the facilities group on the next run and
                // the five links would stay on the original.
                'مدخل لذوي الاحتياجات' => 'Accessible Entrance',
                'انتظار سيارات' => 'Parking',
                'قسم سيدات' => 'Women\'s Section',
            ],
        ],
    ],

    /*
    | The row that moved out still belongs to these children, and the link is
    | maintained from here so the move does not quietly cost them it.
    |
    | All seven are named and the withdrawal record decides: «مراكز أشعة» and
    | #215 do not carry it today and will not be handed it back — an X-ray suite
    | does not travel. Naming the group by its new home rather than its old one
    | is the whole point; `links` resolves an option by group and name, so this
    | line reads «the medical children answer «زيارة منزلية» in the service-mode
    | axis», which is where it can finally carry a price.
    */
    'links' => [
        163 => ['نمط تقديم الخدمة' => ['زيارة منزلية']],   // معمل تحاليل
        215 => ['نمط تقديم الخدمة' => ['زيارة منزلية']],
        252 => ['نمط تقديم الخدمة' => ['زيارة منزلية']],   // مراكز أشعة
        513 => ['نمط تقديم الخدمة' => ['زيارة منزلية']],   // مستشفى
        514 => ['نمط تقديم الخدمة' => ['زيارة منزلية']],   // عيادة
        515 => ['نمط تقديم الخدمة' => ['زيارة منزلية']],   // مركز طبي
        542 => ['نمط تقديم الخدمة' => ['زيارة منزلية']],   // مركز حجامة
    ],
];
