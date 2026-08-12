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
    | The same trade, the same reasoning, the OTHER craftsmen root — found
    | 2026-08-10 by scoring every child's vocabulary against every root's.
    |
    | «مهن وحرفيين» holds twenty-eight one-man crafts — نقاش، سباك، كهربائي،
    | مبلط، حداد، منجد — and every single one of them stands under that root
    | ALONE. Two do not. «رخام وجرانيت» is a marble craftsman who is also a
    | marble factory, five accounts, and that pair is real. «باب وشباك» is the
    | other, and its three other standings are مصانع، شركات، المحلات: all three
    | selling roots, all three carrying retail. A goods trade in a root of
    | trades that are BOOKED.
    |
    | Same answer as ورش, for the same reason and with the same safety: the
    | craftsman form is «نجار باب وشباك» #84, which holds three accounts and
    | carries all sixteen door types, and this standing holds none.
    */
    [
        'child_name_ar' => 'باب وشباك',
        'root_slug' => 'professions',
        'reassign_to' => null, // no account stands here either
        'why' => 'تاجر بضاعة وسط ٢٨ حرفة تُحجز؛ والحرفي هو «نجار باب وشباك» — صفر حسابات',
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
    | «بي في سي» #289 was left out of this file on 2026-08-10 for one reason:
    | it holds three live merchants whose whole identity is UPVC, and folding
    | them changes what those businesses are called. **The owner ruled on
    | 2026-08-12: «ادمج pvc وباب وشباك فهما نفس الخيارات ونفس الهدف».** The
    | entry below is that ruling; see it for what the three merchants keep.
    */
    [
        'child_name_ar' => 'أبواب مصفحة',
        'root_slug' => 'companies',
        'reassign_to' => null,
        'why' => 'منتَجٌ صار خيارًا في «أنواع الأبواب والشبابيك»، والتجارة نفسها تقف تحت شركات',
    ],

    /*
    | «ادمج pvc وباب وشباك فهما نفس الخيارات ونفس الهدف» — owner, 2026-08-12.
    |
    | He is right on both halves. The two children carried the SAME sixteen
    | words — «بي في سي» was given «أنواع الأبواب والشبابيك» whole on 2026-08-10
    | precisely because it is part of the doors trade — and the same three
    | services with the same branches: retail on `building_hardware`, delivery
    | on `delivery_freight`, offers. A child that answers identically to another
    | child is a second door onto one room.
    |
    | And UPVC is not a trade. It is a MATERIAL, and it already stands as row
    | #1181 «بي في سي (UPVC)» inside the list both children carry. That is what
    | `tick_option` writes onto each of the three merchants BEFORE they move:
    | they arrive on «باب وشباك» already saying UPVC, so nothing about what
    | those businesses sell is lost — only the row they were filed under.
    |
    | #289 stands under مصانع ALONE, so this detachment retires the child: the
    | master row survives (nothing here is deleted) and every option link goes,
    | because a child under no root can be reached by nobody.
    */
    [
        'child_name_ar' => 'بي في سي',
        'root_slug' => 'factories',
        'reassign_to' => 'باب وشباك',
        'tick_option' => 'بي في سي (UPVC)',
        'why' => 'خامة لا حرفة — وهي صفٌّ في «أنواع الأبواب والشبابيك» التي يحملها الابنان بالكامل',
    ],

    /*
    |--------------------------------------------------------------------------
    | «زراعية وحيوانية» — 14 children into 10
    |--------------------------------------------------------------------------
    | «نفذ ١ و٢ و٣ وادمج مواشي وأرانب فقط» — owner, 2026-08-12.
    |
    | Every keeper was renamed first (`child_renames.php`), because a child that
    | swallows its sibling must stop advertising half of what it covers.
    |
    | None of the nine children involved holds a single account, so nothing is
    | rehomed and no `tick_option` is needed: the keeper already carries the
    | folded child's whole vocabulary, which is precisely why they merged.
    |
    | «مزارع سمكية» and «دواجن» were on the table and are NOT here. Aquaculture
    | is a different licence and a different cycle, and «دواجن» is a fresh
    | SELLER — «أقسام الطازج واللحوم» and «حالة الدواجن» — not a producer.
    */
    [
        'child_name_ar' => 'معدات مزارع دواجن',
        'root_slug' => 'agriculture-and-animals',
        'reassign_to' => null,
        'why' => 'يتقاسم قائمة «معدات وتجهيزات المزارع» — والفرق هو الحيوان، وهو صفٌّ لا ابن',
    ],
    [
        'child_name_ar' => 'معدات مزارع أرانب',
        'root_slug' => 'agriculture-and-animals',
        'reassign_to' => null,
        'why' => 'نفس القائمة ونفس الحرفة — أقفاص ومشارب',
    ],

    /*
    | «خضروات» stands under THREE roots and has to leave all three, or the row
    | survives where the merge did not reach and a customer meets two names for
    | one shelf. «خضار وفاكهة» #114 already stands in the same three.
    */
    [
        'child_name_ar' => 'خضروات',
        'root_slug' => 'agriculture-and-animals',
        'reassign_to' => null,
        'why' => 'تطابق ١٠٠٪ مع «فواكة» — واسمهما التجاري واحد: خضار وفاكهة',
    ],
    [
        'child_name_ar' => 'خضروات',
        'root_slug' => 'companies',
        'reassign_to' => null,
        'why' => 'نفس الدمج، والجذر الثاني الذي كان يقف فيه',
    ],
    [
        'child_name_ar' => 'خضروات',
        'root_slug' => 'shops-online',
        'reassign_to' => null,
        'why' => 'نفس الدمج، والجذر الثالث',
    ],

    [
        'child_name_ar' => 'أسمدة',
        'root_slug' => 'agriculture-and-animals',
        'reassign_to' => null,
        'why' => 'محل المستلزمات الزراعية يبيع التقاوي والسماد والمبيد معًا',
    ],
    [
        'child_name_ar' => 'أرانب',
        'root_slug' => 'agriculture-and-animals',
        'reassign_to' => null,
        'why' => 'الفرق عن «مواشي» صار صفًّا في «أنواع الثروة الحيوانية والسمكية»',
    ],

    /*
    |--------------------------------------------------------------------------
    | Two more merges the owner approved on 2026-08-12
    |--------------------------------------------------------------------------
    | «نعم نفذها ما عدا السيارات». Both keepers already carry the folded child's
    | ENTIRE vocabulary and the same services on the same branches — which is
    | what the merge audit found and all it can find.
    |
    | ── freight ───────────────────────────────────────────────────────────
    | «نقل دولي» #154 holds no account. It is not renamed away: «شحن بري وبحري
    | وجوى» already names every MODE there is, and «دولي» is a SCOPE — the
    | keeper answers it on «نطاق التعامل» (تصدير · إستيراد) and already carries
    | the `delivery_international` branch. Renaming for a word the child can
    | already say would have meant editing five files for nothing.
    |
    | ── real estate ───────────────────────────────────────────────────────
    | «تسويق عقاري» #238 has the MORE accounts of the two (16 against 7), and
    | is still the one that folds. «مكتب عقاري» #517 is the platform's declared
    | `business_migration_target` in `real_estate_taxonomy.php` — the row every
    | migrating estate business is already sent to — and three tests name it.
    | Folding the target into the source would have unwired all of that to keep
    | a name that means the same trade. The sixteen accounts move.
    |
    | «مالك عقار» #522 stays. An owner letting his own flat is not an agent.
    */
    [
        'child_name_ar' => 'نقل دولي',
        'root_slug' => 'companies',
        'reassign_to' => null,
        'why' => 'نفس المعجم ونفس الخدمات — و«دولي» نطاق تعامل لا وسيلة نقل',
    ],
    [
        'child_name_ar' => 'تسويق عقاري',
        'root_slug' => 'property-and-land',
        'reassign_to' => 'مكتب عقاري',
        'why' => 'تجارة واحدة باسمين — و«مكتب عقاري» هو الوجهة المعلنة في خريطة العقارات',
    ],
];
