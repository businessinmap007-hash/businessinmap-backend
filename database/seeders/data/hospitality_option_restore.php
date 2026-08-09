<?php

/*
|--------------------------------------------------------------------------
| Putting «بيت ضيافة» and «فندق عائم» back
|--------------------------------------------------------------------------
| Owner, 2026-08-09: «عند تجربة تعديل خيارات بيت ضيافة وفندق عائم قمت بالغاء
| تحديد الخيارات بالخطا اعد ترتيبها».
|
| Both were stripped to three rows while testing the new card. Their four
| siblings under «فنادق سياحية» are intact, and comparing them shows one base
| every hospitality child carries and one axis that legitimately differs:
|
|   فندق          مرافق 10 · تصنيف 7 · دفع 1 · ملاءمة 2 · إطلالة 2 · وجبات 3 · غرف 18
|   شقق فندقية    مرافق 10 ·           دفع 1 · ملاءمة 2 · إطلالة 2 · وجبات 3 · غرف 8 · عقارات 1
|   منتجع         مرافق 10 · تصنيف 7 · دفع 1 · ملاءمة 2 · إطلالة 2 · وجبات 3 · غرف 20 · عقارات 2
|   نُزل/هوستل     مرافق 10 ·           دفع 1 · ملاءمة 2 · إطلالة 2 · وجبات 3 · غرف 7
|
| So the BASE below is not invented — it is the intersection of all four.
|
| «الغرف» is deliberately NOT here. `HotelRoomKindOptionsSeeder` already owns
| that axis and states, room by room, which of the six children may say each
| word — a guest house gets a suite, a hostel does not, only the boat says
| «كابينة». My first attempt hand-wrote a room list beside it and immediately
| contradicted it, which is the whole reason that seeder exists. Rooms are
| restored by RE-RUNNING it, not by copying its answers here.
|
| The rows are written SHARED (`category_id = 0`). The accident left behind
| root-scoped duplicates — «إطلالة بحرية» existed twice, once shared and once
| scoped to root 24 — because a replace grants under the root it was launched
| from. A child that hangs from ONE root has nothing to scope against, so the
| seeder collapses those back to shared.
*/

return [

    'root_slug' => 'tourist-hotels',

    /** Present on every one of the four intact siblings. */
    'base_groups' => [
        'مرافق الإقامة',
        'ملاءمة المكان',
        'إطلالة الوحدة',
        'نظام الوجبات',
    ],

    /** From «الدفع والسداد», which is a big group — only this one is shared. */
    'base_options' => [
        'تقسيط بدون فوائد',
    ],

    'children' => [
        'بيت ضيافة',
        'فندق عائم / بوت نيلي',
    ],
];
