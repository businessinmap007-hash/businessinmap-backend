<?php

/**
 * The children that can only answer PART of a group they carry.
 *
 * Same fix the sports pools got, generalised. A group is attached to a child as
 * a whole, so «نجف و تحف» was offered غرفة نوم and سفرة, a wedding hall was
 * offered مؤتمرات وندوات, a car wash was offered معدات ثقيلة, and a pyjama shop
 * was offered فساتين زفاف. None of those is a wrong GROUP — it is the right
 * group handed over whole to a child that can only use a slice.
 *
 * Scoping, not splitting: the group stays one list, and only this child's view
 * of it narrows. That is the same conclusion the sports/medical fold-back
 * reached — per-CHILD scoping is what makes a long list usable, not headings.
 *
 * Shape: group name_ar => [child id => [option ids the child may answer]].
 * A child absent from a group's map keeps the whole list.
 *
 * Read by ChildOptionScopeSeeder, and honoured by the two seeders that would
 * otherwise hand the whole group back: LinkCategoryChildrenToOptionsSeeder and
 * VehicleOptionGroupsSeeder.
 *
 * This narrows a child's list under EVERY root at once — it answers "this child
 * cannot use that option at all". The other, finer question — "this child
 * answers differently under مصانع than under معارض" — is per-root and lives on
 * `category_child_option.category_id`; see App\Services\CategoryChildOptionScope.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | أثاث وتشطيب منزلي
    |--------------------------------------------------------------------------
    | #39 غرفة نوم · #64 سجاد ومفروشات · #77 غرفة أطفال · #113 سفرة · #169 آثاث
    | #188 آثاث فندقي · #219 أدراج ووحدات مطبخ · #266 آثاث مكتبي · #312 صالون
    | #337 أنتريه · #355 تابلوه · #398 ركنه
    */
    'أثاث وتشطيب منزلي' => [
        56 => [355],           // نجف — a chandelier shop hangs a tableau, not a dining set
        57 => [355],           // نجف و تحف
        // #160 «مطابخ و دريسنج» became a bench inside #544 on 2026-08-10, and
        // the workshop makes everything the joinery #49 makes — but weaves no
        // carpet. Without the entry, rule 3 («اثاث|…|مطابخ») hands it the whole
        // list back, سجاد ومفروشات and all.
        544 => [39, 77, 113, 169, 188, 219, 266, 312, 337, 355, 398], // ورشة أثاث ونجارة
        115 => [64, 312, 337, 398, 355], // مفروشات — soft furnishing, no kitchen units
        49 => [39, 77, 113, 169, 188, 219, 266, 312, 337, 355, 398], // نجار موبيليا — makes all of it but weaves no carpet
        // #116 آثاث keeps the whole list: as a showroom, workshop, factory and
        // company child it covers every piece there is
    ],

    /*
    |--------------------------------------------------------------------------
    | أنواع المناسبات
    |--------------------------------------------------------------------------
    | #675 أفراح · #676 خطوبة · #677 عيد ميلاد · #678 حفلات تخرج · #679 مؤتمرات
    | #680 اجتماعات عمل · #681 ندوات · #682 معارض · #683 حفلات موسيقية
    | #684 عزاء · #685 إفطار جماعي · #686 تصوير مناسبات
    */
    'أنواع المناسبات' => [
        527 => [675, 676, 677, 678, 683, 684, 685, 686], // قاعة مناسبات
        528 => [678, 679, 680, 681, 682, 685, 686],      // مركز مؤتمرات واجتماعات
    ],

    /*
    |--------------------------------------------------------------------------
    | مركبات النقل والركاب
    |--------------------------------------------------------------------------
    | #51 باص 50 راكب · #184 معدات ثقيلة · #214 جامبو · #220 كوتش
    | #248 ميكروباص 15 · #250 ميني باص 25 · #251 ميني ڤان 7 · #280 ربع نقل
    | #281 ربع نقل صندوق · #365 مقطورة
    */
    'مركبات النقل والركاب' => [
        // passenger fleets
        278 => [51, 220, 248, 250, 251],   // نقل ركاب
        169 => [220, 248, 251],            // خدمة ليموزين
        /*
         * «سائق» #85, added 2026-08-11 when the last mute children were given
         * a vocabulary. Declaring it here was NOT optional: linking the five
         * passenger sizes without saying so leaves the child UNSCOPED, and
         * VehicleOptionGroupsSeeder hands the whole list back on its next run —
         * a driver-for-hire offered a trailer and a load of heavy plant.
         */
        85 => [51, 220, 248, 250, 251],    // سائق — he drives you, not your cargo

        // freight fleets
        284 => [184, 214, 280, 281, 365],  // سيارات نقل
        68 => [184, 214, 280, 281, 365],   // شركة شحن
        198 => [184, 214, 280, 281, 365],  // مكتب شحن
        166 => [184, 214, 280, 281, 365],  // شحن بري وبحري وجوى
        154 => [184, 214, 280, 281, 365],  // نقل دولي
        243 => [251, 280, 281],            // مندوب — a courier, not a convoy
        139 => [184, 214, 365],            // معدات ثقيلة

        // what a bay can physically take
        46 => [248, 250, 251, 280, 281],   // مغسلة سيارات
        119 => [248, 250, 251, 280, 281],  // جراج
        // #244 ونش إنقاذ keeps the whole list — a rescue winch tows all of it
    ],

    /*
    |--------------------------------------------------------------------------
    | مرافق ومعدات — #568 واي فاي · #569 وايت بورد
    |--------------------------------------------------------------------------
    */
    'مرافق ومعدات' => [
        64 => [568],   // كافيه
        245 => [568],  // مطعم
        246 => [568],  // مطعم وكافيه
        155 => [568],  // انترنت كافيه — a whiteboard is meeting-room kit, not café kit
        // halls, conference centres and training rooms keep both
    ],

    /*
    |--------------------------------------------------------------------------
    | موضة وعناية شخصية — the PRODUCT half
    |--------------------------------------------------------------------------
    | #21 اكسسوارات · #89 ملابس · #135 أقمشة · #181 صناعة يدوية
    | #227 جلود وشنط وأحذية · #349 بدلة زفاف · #382 فساتين زفاف
    |
    | A pyjama shop was being offered فساتين زفاف. The audience rows that used
    | to sit in this group left for «الجمهور المستهدف» below — one group was
    | answering WHO it is for and WHAT is sold at once, and only the second is
    | a priced line.
    */
    'موضة وعناية شخصية' => [
        95 => [135],             // أقمشة — a fabric merchant is a different trade
        // #59 ملابس، #168 جلود وشنط وأحذية and #8 اكسسوار keep the WHOLE list as of
        // 2026-08-08: root #14 collapsed to those three, and scoping them is
        // exactly what left «كوتشي» unable to name a single thing it sold. The
        // shop that carries clothes AND shoes AND accessories must be able to
        // say all three; the narrowing is the merchant's own ticks now.
        // #54، #112، #258، #267، #297 left the root entirely — FashionRemodelSeeder.
        //
        // #60 ملابس جاهزة still keeps the whole list: as a factory and showroom
        // child it does carry every piece there is, wedding wear included.
    ],

    /*
    |--------------------------------------------------------------------------
    | تعبئة وتغليف ومستلزمات
    |--------------------------------------------------------------------------
    | #27 أطباق فويل · #93 أكياس قهوة · #147 أكواب فوم · #148 أطباق فوم
    | #150 للطباعة · #246 علبة معدن · #271 مواد تعبئة وتغليف · #273 أكواب ورقية
    | #284 أكواب بلاستيك · #293 مطبوع · #344 أدوات مكتبية
    |
    | The first EMPTY list in this file, and it is deliberate. Absence here means
    | "keep the whole group", so the only way to say "this child answers none of
    | it" is to say so out loud. Child #232 «طباعة مواد تعبئة وتغليف» was retired
    | from the group by the owner (approved 2026-08-08); the add-only
    | LinkCategoryChildrenToOptionsSeeder handed all 11 back on every run until
    | this line existed.
    |
    | It prints packaging, it does not sell it: the sibling #204 «مواد تعبئة
    | وتغليف» is the one that stocks cups and foil, and it keeps the whole list.
    */
    'تعبئة وتغليف ومستلزمات' => [
        232 => [],  // طباعة مواد تعبئة وتغليف — a printer, not a supplier
    ],

    /*
    |--------------------------------------------------------------------------
    | ماركات السيارات — the 43 makes, a modifier group
    |--------------------------------------------------------------------------
    | The same retirement, one root over. Child #43 «قطع غيار سيارات» sits under
    | #23 alongside #232, and the owner stripped it to «التسليم والاستلام» alone
    | in the same pass. VehicleOptionGroupsSeeder is the add-only one here, and
    | it handed all 43 makes back every run.
    |
    | It is NOT the spare-parts child that matters: #44, same name and same root
    | as the rest of the vehicle trade, keeps every make and is the one a real
    | business (1 of them) sits on. #43 is its older duplicate — «Car spare
    | parts» vs «Car Sspare parts», created two days apart in 2020 — and carries
    | no business at all.
    */
    'ماركات السيارات' => [
        43 => [],  // قطع غيار سيارات (the empty duplicate) — #44 is the live one
    ],

    /*
    |--------------------------------------------------------------------------
    | الجمهور المستهدف — #141 حريمي · #232 رجالي · #217 أطفال
    |--------------------------------------------------------------------------
    | Unscoped as of 2026-08-08. It used to narrow «ملابس زفاف» and «ملابس رسمي»
    | to adults, and both left root #14 in the fashion collapse — the shop that
    | dresses grooms now says so with a line option instead of a category, and
    | the same shop may well dress children too.
    */
    'الجمهور المستهدف' => [
        // every clothing child keeps all three
    ],

    /*
    |--------------------------------------------------------------------------
    | عقارات وممتلكات
    |--------------------------------------------------------------------------
    | An empty list STRIPS the group — the only entry here that means «this
    | child may answer none of it».
    |
    | Rule 9 in LinkCategoryChildrenToOptionsSeeder matches the word «ورش[ةه]»,
    | and rightly so: a workshop is a PROPERTY a real-estate business lists for
    | rent. But «ورشة سيارات» is not a unit for rent, it is the garage itself,
    | and the day the workshop domains were created (2026-08-10) that rule was
    | about to offer a metal shop شقة، فيلا، أرض — thirteen properties it does
    | not sell. The name matched; the meaning did not.
    */
    'عقارات وممتلكات' => [
        544 => [], // ورشة أثاث ونجارة
        545 => [], // ورشة حدادة وخراطة
        546 => [], // ورشة صيانة أجهزة
        // «ورشة سيارات» #543 never reaches the rule — its exclude pattern
        // catches «سيار» first.
    ],

    /*
    |--------------------------------------------------------------------------
    | أصناف المنتجات الغذائية — the same question, asked twice
    |--------------------------------------------------------------------------
    | «راجع التكرار والتشابه بينهم» — owner, 2026-08-10.
    |
    | This `modifier` list of twenty food ranges and the `line` aisle list beside
    | it are one vocabulary written twice. «زيوت وسمن» and «معلبات» are the same
    | word in both, and thirteen of the twenty restate an aisle: «حبوب وبقوليات»
    | + «أرز» + «مكرونة» against «مكرونات وأرز وحبوب», «ألبان وأجبان» against
    | «ألبان وبيض» + «أجبان», and so on down the list.
    |
    | It is not redundant everywhere, which is why the group survives. Three of
    | its eight children carry no market list at all — مواد غذائية،
    | مواد غذائية ومنظفات، استيراد وتصدير — and «which ranges do you deal in» is
    | a real question for a wholesaler with no priced heading behind it. That is
    | what a modifier is for.
    |
    | The five below carry BOTH and were being asked twice, once priced and once
    | not. They keep the priced one. An empty list is the declared way to say
    | «this child answers none of this group», and ChildOptionScopeTest checks
    | that it actually holds.
    */
    'أصناف المنتجات الغذائية' => [
        272 => [], // سوبر ماركت — 16 accounts
        185 => [], // مني ماركت — 12
        149 => [], // هايبر ماركت — 6
        113 => [], // مجمدات — 1
        128 => [], // حبوب وغلال — 0
    ],

    /*
    |--------------------------------------------------------------------------
    | قطع الغيار حسب الآلة
    |--------------------------------------------------------------------------
    | #1742 سيارات · #1743 معدات ثقيلة · #1744 أجهزة منزلية · #1745 مكن صناعي
    | #1746 مصاعد · #1747 تبريد وتكييف · #1748 دراجات · #1749 مستوردة
    | #1750 مستعملة
    |
    | The list answers WHICH MACHINE. The last two answer neither — «مستوردة» is
    | «نطاق التعامل / إستيراد» and «مستعملة» is «حالة المنتج / مستعمل», and
    | «قطع غيار» #263 already carries both groups. A merchant asked the same
    | thing in two places will sooner or later answer it two different ways.
    |
    | The rows stay in the group; only this child's view of it narrows.
    */
    'قطع الغيار حسب الآلة' => [
        263 => [1742, 1743, 1744, 1745, 1746, 1747, 1748],
    ],

    /*
    |--------------------------------------------------------------------------
    | أنواع الأبواب والشبابيك
    |--------------------------------------------------------------------------
    | «المونتال … هو لبيع قطاعات الالمونتال نفسها وليس الشباك والباب» — owner,
    | 2026-08-12.
    |
    | «ألمونتال» #17 had seven of the sixteen door types because the two trades
    | stand beside each other. It sells the EXTRUSION to the workshop that makes
    | the window; it does not make the window. Its own list is «قطاعات ومنتجات
    | الألومنيوم», written the same day.
    |
    | The second declared empty in this file, and for the same reason as the
    | first: «طباعة مواد تعبئة وتغليف» #232 PRINTS packaging, it does not SELL
    | it. A merchant's own tick still outranks this, as everywhere else.
    */
    'أنواع الأبواب والشبابيك' => [
        17 => [],
    ],
];
