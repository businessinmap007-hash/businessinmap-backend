<?php

/*
|--------------------------------------------------------------------------
| «سياحة وفنادق» — the root widens by a name, and by the one trade it had no
| room for
|--------------------------------------------------------------------------
| Owner, 2026-08-23:
|
|   «هل نغير القسم الرئيسي فنادق سياحية الى سياحة وفنادق ونضيف تحته المدن
|    السياحية … المصايف والعقارات والاراضي للشقق السكنية والمحلات والمكاتب؟»
|   «المدن السياحية والمصايف التي بها شقق مصيفية، اين نضعها بحيث يسهل على
|    اصحاب الشقق والمكاتب العقارية عرضها وعلى المستخدم ايجادها؟»
|
| ── The rename: yes, and the slug does not move ───────────────────────────
|
| «فنادق سياحية» named the biggest of its six children and excluded the other
| five: a hostel is not a hotel, a Nile boat is not a hotel, and a private
| chalet is neither. «سياحة وفنادق» is what the root has actually held since
| the day it took «نُزل» and «بيت ضيافة».
|
| `slug` stays `tourist-hotels`. Twenty-one places resolve this root by slug —
| booking branch maps, the capability guard, the vocabulary files — and a slug
| is an address, not a label. Renaming it would be a silent unwiring dressed as
| tidying, which is the landmine this taxonomy keeps re-laying.
|
| ── «مدن سياحية»: no, and this is the substantive answer ──────────────────
|
| A tourist city is a PLACE. Every root on this platform answers «what kind of
| business is this», and a child that answers «where is it» puts الساحل الشمالي
| in the same list as فندق — so a hotel in الجونة would have to choose which of
| the two it is, and a guest searching «فندق فى الجونة» would have to know
| which one the merchant picked.
|
| The axis already exists and is populated: `cities` holds 1,339 rows and every
| resort town is in it — الغردقة، شرم الشيخ، العلمين، الجونة، دهب، مرسى مطروح،
| رأس سدر. A summer let is found the same way a dentist is: the trade on one
| axis, the city on the other.
|
| ── «شقق مصيفية»: the DEAL decides the root, not the season ───────────────
|
| A chalet in الساحل is two different listings and the difference is not where
| it stands:
|
|   let by the night   → this root. It is a stay: nightly price, calendar,
|                        blocked dates, guests, an add-on — the machinery
|                        «فندق» already runs.
|   sold, or let yearly → «عقارات و أراضي» #18. It is a listing: an area, a
|                        finish level, a deal type, an instalment plan.
|
| Both are found by the same city filter, which is what «يسهل على المستخدم
| ايجادها» needs, and neither borrows the other's screens.
|
| ── What was missing, then ────────────────────────────────────────────────
|
| Not a place and not a season: a PERSON. All six children of this root are
| operators — a hotel, a serviced-apartment company, a resort, a hostel, a
| guest house, a cruiser. The man with one chalet he lets out for six weeks a
| year had nowhere to stand, and he is «اصحاب الشقق» in the question.
|
| «عقارات و أراضي» has exactly this child already — «مالك عقار» #522, beside
| «مكتب عقاري» and «مطور عقاري» — and it was added for exactly this reason: the
| «من المالك» side of the market that buyers filter for explicitly.
|
| So the root gains its twin. Consumed by \Database\Seeders\TourismRootSeeder.
*/

return [

    'root_slug' => 'tourist-hotels',

    'rename_to' => ['ar' => 'سياحة وفنادق', 'en' => 'Tourism & Hotels'],

    /*
    | The new child, and the sibling it is shaped from.
    |
    | «شقق فندقية» #537 is the closest: a flat let by the night, no spa, no
    | day-use pool, no meeting room, no excursion desk. What the owner does NOT
    | have that #537 does is a reception and a kitchen — hence no «نظام
    | الوجبات» below, and no star rating, which nobody awards a private chalet.
    */
    'child' => [
        'name_ar' => 'مالك وحدة مصيفية',
        'name_en' => 'Vacation Rental Owner',
        'shaped_from' => 'شقق فندقية',
        'booking_branch' => 'hotel',
        'booking_pattern' => 'stay',
    ],

    /*
    | Its vocabulary, row by row.
    |
    | «الغرف» is the unit he lets, and it is named rather than granted whole:
    | the group holds twenty-eight rows including جناح رئاسي and كابينة
    | ديلوكس, and a man with a chalet is not offered a presidential suite.
    |
    | «إطلالة الوحدة» is the axis this whole request is about — «ممكن غرفة D117
    | تكون على المسبح و D118 تطل على البحر» is exactly a row of chalets — and it
    | is a UNIT attribute, ticked per unit and priced once, not asked of the
    | guest.
    |
    | «مرافق الإقامة» is what the place has; «نظام الوجبات» is deliberately
    | absent, because a private let feeds nobody, and offering it would put a
    | breakfast price on a screen with no kitchen behind it.
    */
    'vocabulary' => [
        'الغرف' => ['استوديو', 'شاليه', 'بنجلو', 'غرفة', 'غرفتين', 'ثلاث غرف', 'أربع غرف', 'خمس غرف فأكثر'],
        'إطلالة الوحدة' => 'all',
        'مرافق الإقامة' => 'all',
        'الدفع والسداد' => ['كاش', 'تقسيط'],
    ],
];
