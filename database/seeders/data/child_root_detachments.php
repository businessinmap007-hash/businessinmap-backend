<?php

/*
|--------------------------------------------------------------------------
| «احذف س من أبناء ص» — a child leaving a root it does not belong under
|--------------------------------------------------------------------------
| Owner, 2026-08-10:
|
|   «حذف آثاث وباب وشباك من ابناء الورش وحذف عفشجي من شحن وتوصيل.»
|
| A move ([[child_root_moves.php]]) says «it belongs somewhere ELSE». This says
| «it does not belong HERE», which is a different instruction and sometimes has
| no destination at all — «عفشجى» stands under one root, so removing it retires
| the trade.
|
| NOTHING IS DELETED. The master row survives, and the `category_parent_child`
| row that goes IS the undo record. What must not survive is an ACCOUNT left
| pointing at a root its child no longer hangs from: that merchant disappears
| from every screen at once. So every entry either holds no account or names
| exactly where its merchants go, and the seeder refuses to detach otherwise.
*/

return [

    /*
    | «آثاث» is a SELLER — a showroom, a company, a factory — and it keeps all
    | three of those roots. Under ورش it was the odd one out: it carried `menu`
    | where every other workshop child takes a booking, which is the shape of a
    | shop, not a bench. Its 29 workshop merchants are furniture WORKSHOPS and
    | that child now exists — «ورشة أثاث ونجارة», built this morning out of
    | تنجيد، استورجى، كوتش، مطابخ ودريسنج.
    */
    [
        'child_name_ar' => 'آثاث',
        'root_slug' => 'workshops',
        'reassign_to' => 'ورشة أثاث ونجارة',
        'why' => 'بائع أثاث لا ورشة؛ ومن يعمل بالورش هو ورشة أثاث ونجارة',
    ],

    /*
    | The doors-and-windows TRADE took شركات and المحلات this morning and stands
    | under مصانع beside them — the three roots a doors business sells from. The
    | workshop form of it has always had its own child, «نجار باب وشباك» #84,
    | which holds the three workshop accounts and keeps the sixteen door types.
    | Both under ورش said the same thing twice.
    */
    [
        'child_name_ar' => 'باب وشباك',
        'root_slug' => 'workshops',
        'reassign_to' => null, // no account stands here
        'why' => 'شكل الورشة له ابنه «نجار باب وشباك»؛ والتجارة تحت مصانع وشركات والمحلات',
    ],

    /*
    | «عفشجى» came here from ورش yesterday, and the owner has since named it
    | among the one-man benches that should be words rather than rows. It stands
    | under this root only, so this retires the child: the row survives, nothing
    | points at it.
    |
    | Its one merchant goes to «مندوب» — the individual tier of this root, beside
    | the شركة and the مكتب — which keeps him on `schedules` and findable.
    |
    | Listed under BOTH roots on purpose. On a fresh database the child starts
    | under ورش (data/categories.php) and the move that brought it here has been
    | withdrawn, so «here» depends on how far the seed has got.
    */
    [
        'child_name_ar' => 'عفشجى',
        'root_slug' => 'shipping-delivery',
        'reassign_to' => 'مندوب',
        'why' => 'المالك: يُحذف من شحن وتوصيل',
    ],
    [
        'child_name_ar' => 'عفشجى',
        'root_slug' => 'workshops',
        'reassign_to' => null,
        'why' => 'ولا يعود إلى الورش — نقلٌ لا صيانة',
    ],

    /*
    |--------------------------------------------------------------------------
    | The six educational stages — «اطوها كالورش» (owner, 2026-08-10)
    |--------------------------------------------------------------------------
    | The workshop shape exactly: six children that were already six OPTIONS
    | standing beside them. «سنتر دروس» #86 carries «المراحل التعليمية» with the
    | same six names — رياض أطفال، ابتدائي، إعدادي، ثانوي عام، ثانوي أزهري،
    | دبلومات فنية — so a tutoring centre teaching primary and secondary had to
    | be two accounts or half a business, and a customer looking for one had to
    | guess which row its owner picked. Every one holds zero accounts.
    |
    | «حضانات» is NOT here: a nursery is a PLACE with three live merchants, and
    | it is the one stage in the matrix that is also a business you walk into.
    | «مركز تدريب» is not here either — it teaches FIELDS, not school subjects.
    |
    | The stage→subject matrix in EducationalStagesSeeder survives untouched. It
    | is the only record of which subjects belong to which stage, the UI that
    | would read it was never built, and folding the rows must not take the
    | design with them.
    */
    [
        'child_name_ar' => 'رياض أطفال',
        'root_slug' => 'training-courses',
        'reassign_to' => null,
        'why' => 'مرحلة لا مكان — وهي خيار قائم في «المراحل التعليمية» على «سنتر دروس»',
    ],
    [
        'child_name_ar' => 'ابتدائي',
        'root_slug' => 'training-courses',
        'reassign_to' => null,
        'why' => 'نفس الحالة',
    ],
    [
        'child_name_ar' => 'إعدادي',
        'root_slug' => 'training-courses',
        'reassign_to' => null,
        'why' => 'نفس الحالة',
    ],
    [
        'child_name_ar' => 'ثانوي عام',
        'root_slug' => 'training-courses',
        'reassign_to' => null,
        'why' => 'نفس الحالة',
    ],
    [
        'child_name_ar' => 'ثانوي أزهري',
        'root_slug' => 'training-courses',
        'reassign_to' => null,
        'why' => 'نفس الحالة',
    ],
    [
        'child_name_ar' => 'دبلومات فنية',
        'root_slug' => 'training-courses',
        'reassign_to' => null,
        'why' => 'نفس الحالة',
    ],

    /*
    |--------------------------------------------------------------------------
    | Two more words standing next to themselves
    |--------------------------------------------------------------------------
    | Found by the detector that found the school stages: a CHILD whose name is
    | already an OPTION carried by a sibling under the same root.
    |
    | Both of these were made by earlier corrections of ours, which is the point
    | worth keeping — a move or a new vocabulary can leave a duplicate behind
    | that neither step could see on its own.
    */

    /*
    | «تجهيز عرائس» was moved shops-online → مهن وحرفيين on 2026-08-08 on the
    | owner's own ruling («خدمة تجميل انقله»). It landed next to «كوافير», which
    | carries «تجهيز عرائس» as one of the fourteen priced services in «خدمات
    | الكوافير والتجميل» — so the move was right and finished one step short.
    |
    | Its three merchants go to «كوافير» with that service ticked, so they say
    | exactly what they said before and gain the thirteen beside it.
    */
    [
        'child_name_ar' => 'تجهيز عرائس',
        'root_slug' => 'professions',
        'reassign_to' => 'كوافير',
        'tick_option' => 'تجهيز عرائس',
        'why' => 'هي خدمة مسعّرة على «كوافير» بالفعل — صفٌّ يقف بجوار كلمته',
    ],

    /*
    | «أبواب مصفحة» is one of the sixteen types in «أنواع الأبواب والشبابيك»
    | created on 2026-08-10, and «باب وشباك» took شركات the same day — so the
    | product and the trade stood side by side under one root. Zero accounts.
    |
    | «بي في سي» #289 is deliberately NOT here. It is the same shape, but it
    | holds THREE live merchants whose whole identity is UPVC, and folding them
    | into the generic trade changes what those businesses are called. That one
    | is the owner's to say.
    */
    [
        'child_name_ar' => 'أبواب مصفحة',
        'root_slug' => 'companies',
        'reassign_to' => null,
        'why' => 'منتَجٌ صار خيارًا في «أنواع الأبواب والشبابيك»، والتجارة نفسها تقف تحت شركات',
    ],
];
