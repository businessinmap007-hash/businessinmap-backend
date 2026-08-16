<?php

/**
 * «لدينا مجموعات كثيرة من الخيارات يمكن ان تعمل تحت اكثر من ابن بدون التكرار
 *  او التداخل» — approved 2026-08-12.
 *
 * A pass over all 209 live children found 80 groups held by exactly ONE child,
 * and many of them are a complete vocabulary for a silent sibling. The shape of
 * the gap is always the same: the child can say WHAT IT DOES and cannot say
 * WITH WHAT — and what it is made of is precisely what sets the price.
 *
 *     ورشة أثاث ونجارة (48 merchants) says تنجيد · دهان · مطابخ · حفر وأويما
 *     and cannot say زان or MDF. «أخشاب» #301 holds that list in full.
 *
 * ## Nothing here is created
 *
 * No option row and no group is written by this file. Every entry is an
 * EXISTING group already held by a donor child, lent to a recipient that
 * answers the same axis. That is why it is safe, and why it needed an explicit
 * approved list rather than a rule.
 *
 * ## The overlap rule that shortened the list
 *
 * «بدون التكرار او التداخل». Every candidate was checked row by row against
 * what the recipient already holds, and five were dropped for genuinely
 * colliding:
 *
 *   كهربائي ← المفاتيح والتوزيع  (لوحات وقواطع on both sides)
 *   سباك ← الأدوات الصحية         (سخانات · مواسير on both)
 *   فني ستائر ← لوازم الستائر     (ستائر رول · بليسيه on both)
 *   باب وشباك ← أنواع الزجاج      (واجهات زجاجية سيكوريت on both)
 *   تنسيق حفلات ← أنواع المناسبات (two `line` groups on one child)
 *
 * ## Scope
 *
 * All shared (`category_id = 0`). A borrowed axis is about the TRADE, not
 * about the root it stands under: a furniture workshop works in beech whether
 * it is filed under ورش or شركات. Where a recipient stands under several roots
 * it gets one row, not four.
 *
 * @see \Database\Seeders\ChildVocabularyBorrowSeeder
 */
return [

    /*
    |--------------------------------------------------------------------------
    | What the woodworking trades are made of
    |--------------------------------------------------------------------------
    | «أنواع الأخشاب» is classified a MODIFIER, and under these four children
    | that is exactly what it is: the workshop sells تنجيد, the species changes
    | what تنجيد costs. (Under «أخشاب» itself the same list is the product —
    | see MerchantOfferingVocabulary's promotion rule. One group, two jobs,
    | decided by the child.)
    */
    [
        'group' => 'أنواع الأخشاب',
        'from' => 301,                       // أخشاب
        'to' => [
            116,  // آثاث — 64 merchants across three roots, the biggest recipient
            544,  // ورشة أثاث ونجارة — 48
            49,   // نجار موبيليا — 22
            302,  // مصنوعات خشبية ومستلزمات ديكور
        ],
        'why' => 'a furniture maker prices by species; the list exists and it could not reach it',
    ],

    /*
    |--------------------------------------------------------------------------
    | What the cloth trades are made of
    |--------------------------------------------------------------------------
    | A bedsheet's price IS its fabric, and a clothing factory's more so.
    | «أصناف المفروشات» says مفارش سرير · لحاف — never قطن or كتان.
    */
    [
        'group' => 'أنواع الأقمشة',
        'from' => 95,                        // أقمشة
        'to' => [
            59,   // ملابس — 44
            60,   // ملابس جاهزة — 21 across three roots
            115,  // مفروشات — 14
        ],
        'why' => 'the fabric is the price driver and none of the three could name one',
    ],

    /*
    |--------------------------------------------------------------------------
    | What an appliance repair actually replaces
    |--------------------------------------------------------------------------
    | Both hold «أنواع الأجهزة الكهربائية» (which appliance) and «تخصصات ورش
    | الأجهزة» (what the workshop does). Neither says كمبروسر or بوردة — and
    | the part is what the invoice is mostly made of.
    */
    [
        'group' => 'قطع غيار الأجهزة المنزلية',
        'from' => 264,                       // قطع غيار أجهزة كهربائية
        'to' => [
            22,   // صيانة اجهزة منزلية
            546,  // ورشة صيانة أجهزة
        ],
        'why' => 'the part being replaced is most of the bill',
    ],

    /*
    |--------------------------------------------------------------------------
    | What a blacksmith works in
    |--------------------------------------------------------------------------
    | «تخصصات ورش المعادن» names the operation — حدادة · خراطة · لحام. The
    | stock (تسليح · زوايا · مواسير · صاج) shares not one row with it.
    */
    [
        'group' => 'أنواع الحديد',
        'from' => 262,                       // حديد تسليح
        'to' => [
            259,  // حداد
            545,  // ورشة حدادة وخراطة
        ],
        'why' => 'the operation was named, the metal never was',
    ],

    /*
    |--------------------------------------------------------------------------
    | The precedent that proves the pattern
    |--------------------------------------------------------------------------
    | #240 «تبريد وتكييف» ALREADY holds both halves — «تخصصات ورش الأجهزة» and
    | «أعمال التبريد والتكييف». #15 «صيانة تكيف» holds only the first, and is
    | the same trade one door down.
    */
    [
        'group' => 'أعمال التبريد والتكييف',
        'from' => 240,                       // تبريد وتكييف
        'to' => [15],                        // صيانة تكيف
        'why' => 'the donor carries both halves already; this one was given half',
    ],

    /*
    |--------------------------------------------------------------------------
    | What fills an aluminium frame
    |--------------------------------------------------------------------------
    | #17 holds the PROFILES (قطاعات سحب · ثرمال بريك · كيرتن وول). The glazing
    | that goes inside them is a different axis entirely and shares no row.
    |
    | «باب وشباك» #50 was deliberately NOT given this: its own list already says
    | «واجهات زجاجية (سيكوريت)», which is the overlap the rule forbids.
    */
    [
        'group' => 'أنواع الزجاج',
        'from' => 126,                       // زجاج
        'to' => [17],                        // ألمونتال
        'why' => 'the frame was described, never what goes in it',
    ],

    /*
    |--------------------------------------------------------------------------
    | Star rating, for the four kinds of stay that never got it
    |--------------------------------------------------------------------------
    | Descriptive, so it prices nothing — it is how a guest filters. «فندق» and
    | «منتجع» hold it; a serviced apartment, a hostel, a guest house and a Nile
    | boat are rated in Egypt too, and the list already carries «غير مصنّف» for
    | the ones that are not.
    */
    [
        'group' => 'تصنيف الإقامة',
        'from' => 536,                       // فندق
        'to' => [
            537,  // شقق فندقية
            539,  // نُزل / هوستل
            540,  // بيت ضيافة
            541,  // فندق عائم / بوت نيلي
        ],
        'why' => 'six kinds of stay, two of them rated',
    ],

];
