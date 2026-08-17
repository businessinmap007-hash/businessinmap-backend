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
|
| ── 2026-08-17: «راجع باقي أبناء الصحة بنفس الطريقة» ─────────────────────
|
| Every child reads FLUENT — a real line group each, nothing promoted, nothing
| narrowed to silence. What the sweep found instead is that **«مستشفى» #513 and
| «مركز طبي» #515 hold the identical vocabulary**: the same 41 specialties, the
| same 13 scans, the same 28 tests, the same modifier and the same nine
| facilities. Byte for byte, 92 links each. Nothing a patient can read tells the
| two apart.
|
| And the reason is that the thing which MAKES a hospital a hospital has no word
| anywhere on the platform. A search of every option row for تنويم، عمليات،
| رعاية مركزة، سرير returns hotel bedrooms and a gym creche. A hospital's whole
| vocabulary here is outpatient — who you see, what they scan, what they test —
| and the admission it exists for cannot be named, let alone priced.
|
| So «الرعاية والتنويم», a line group, and it is the axis that separates the
| two: the hospital admits and operates overnight, the medical centre sends the
| patient home the same day. #515 is narrowed to the day-case slice in
| `child_option_scopes.php` — جراحة اليوم الواحد، غسيل كلوي، جلسة علاج كيماوي —
| which is exactly the three a polyclinic really runs, and holds no bed.
|
| What is deliberately NOT here:
|
|   - «الدفع والسداد». Its absence looks like the gap every other root had, and
|     it is not: كاش and تقسيط were withdrawn from all seven by hand on
|     2026-08-10. Handing them back is what the withdrawal record exists to stop.
|   - «طوارئ واستقبال» as a line. Nobody books an emergency, and «خدمة طوارئ ٢٤
|     ساعة» already says it in the facilities axis, where a filter can use it.
|   - «علاج طبيعي وتأهيل» and «مناظير». Both are already specialties in
|     «تخصصات طبية»; saying them twice is a second axis for one answer.
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

        /*
        | The night a patient spends, and everything that only happens because
        | there is a bed behind it.
        |
        | Every row here is priced the way the hotel's «غرفة مزدوجة» is priced —
        | a private room is a nightly rate, an ICU bed is a nightly rate, an
        | operation and a caesarean are each a quoted figure. That is the price
        | test, and it is why this is a `line` and not another facilities tick:
        | «رعاية مركزة» as a descriptive would say a hospital HAS an ICU and
        | still leave the family ringing round for what a night in it costs.
        |
        | Both children are named. The narrowing that keeps them apart is in
        | `child_option_scopes.php`, not here, so the list stays one list — the
        | same reading the sports pools and the furniture group get.
        */
        'الرعاية والتنويم' => [
            'name_en' => 'Inpatient & Critical Care',
            'price_role' => 'line',
            'children' => [513, 515],
            'options' => [
                'تنويم بغرفة خاصة' => 'Private Room Admission',
                'تنويم بغرفة مشتركة' => 'Shared Room Admission',
                'رعاية مركزة' => 'Intensive Care',
                'رعاية متوسطة' => 'Intermediate Care',
                'حضانة حديثي الولادة' => 'Neonatal Incubator',
                'عملية جراحية' => 'Surgical Operation',
                // The one row a مركز طبي shares with the hospital, and the
                // reason it is in this group rather than a group of its own:
                // it is the SAME question — do you keep the patient — with the
                // answer «no, he walks out the same evening».
                'جراحة اليوم الواحد' => 'Day Surgery',
                'ولادة طبيعية' => 'Natural Delivery',
                'ولادة قيصرية' => 'Caesarean Delivery',
                'غسيل كلوي' => 'Dialysis',
                'جلسة علاج كيماوي' => 'Chemotherapy Session',
                'نقل بسيارة إسعاف' => 'Ambulance Transfer',
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
