<?php

/*
|--------------------------------------------------------------------------
| «دورات و تدريب» — the nursery that could not say half-day
|--------------------------------------------------------------------------
| Owner, 2026-08-17: «راجع باقي أبناء دورات وتدريب بنفس الطريقة».
|
| Three children and **two ledger rows in the whole root** — the lightest
| curation anywhere in this sweep, so an absence here is far more likely to be
| an absence than a ruling.
|
| Two of the three are finished, and finished well:
|
|   سنتر دروس #86   38 subjects as the LINE and «المراحل التعليمية» as the
|                    modifier, which is the real price axis of the trade — a
|                    ثانوي عام lesson is not a ابتدائي lesson's money.
|   مركز تدريب #529  21 training fields plus the eight languages, and
|                    فردي/أونلاين/خاص for how the course is run. He withdrew
|                    «فريق عمل» from it by hand on 2026-08-14.
|
| ── «حضانات» #195 ────────────────────────────────────────────────────────
|
| Its five subjects are NOT thin by accident: `EducationalStagesSeeder` holds
| a closed per-stage matrix and gives the nursery the same foundation set as
| «رياض أطفال» — تأسيس قراءة وكتابة، لغة عربية، لغة إنجليزية، رياضيات، تحفيظ
| قرآن. That is argued in the seeder and stays exactly as it is.
|
| What it had no word for is how the place is actually bought.
|
| No Egyptian parent asks a nursery what it teaches before asking the two
| questions that decide the fee: **نص يوم ولا يوم كامل**, and **الاشتراك شهري
| ولا سنوي**. #195's only modifier was «نمط تقديم الخدمة», which answers
| neither, and every price in the trade is quoted as the pair — half day on a
| monthly subscription is one figure, full day on a term is another.
|
| Both axes already exist and both are borrowed rather than invented:
| «فترة الحجز» is what the halls and the leisure venues price on, and «نظام
| التعاقد» is the gym's subscription ladder, already answering for a coworking
| desk, a maid and a lawyer.
|
| Narrow slices of each, because most of both lists is somebody else's trade:
|
|   فترة الحجز   صباحية · مسائية · يوم كامل  — «نهاية الأسبوع» and «بالساعة»
|                stay out: a nursery closes at the weekend and does not sell an
|                hour of a three-year-old.
|   نظام التعاقد يومي · شهري · سنوي — «بالساعة» and «أسبوعي» are not how any
|                nursery quotes, and «بالمهمة» is a contractor's word.
*/

return [

    'root' => 'training-courses',

    'name_en_suffix' => 'Education',

    'links' => [
        195 => [
            'فترة الحجز' => ['فترة صباحية', 'فترة مسائية', 'يوم كامل'],
            'نظام التعاقد' => ['يومي', 'شهري', 'سنوي'],
        ],
    ],
];
