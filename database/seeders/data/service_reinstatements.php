<?php

/*
|--------------------------------------------------------------------------
| A service switched off where it obviously belongs
|--------------------------------------------------------------------------
| Found by comparing each child's service set against the rest of its root:
| a child whose shape disagrees with its siblings is either misfiled or
| misconfigured, and this file is for the second kind.
|
| The first entry is the one that mattered. «مطعم» #245, thirteen accounts,
| under «مطاعم وكافيهات» — with its `menu` link AND config both `is_active = 0`,
| while «كافيه», «مطعم وكافيه» and «مجمع مطاعم» beside it all carry menu with an
| identical config. A restaurant on this platform could not publish a menu.
|
| Nothing here invents a setting: the config is COPIED from the named sibling
| that already offers the service under the same root, so reinstating cannot
| introduce a shape nobody chose.
|
| This is the opposite of a sweep. Every row is a named finding with a reason,
| and a service is never switched ON in bulk — an admin who turned something off
| on purpose is entitled to have that survive.
*/

return [

    [
        'child_name_ar' => 'مطعم',
        'root_slug' => 'restaurants-cafes',
        'service_key' => 'menu',
        'copy_from_child_ar' => 'مطعم وكافيه',
        'why' => 'مطعم بلا منيو — الرابط والإعداد كلاهما معطّل، وكل إخوته في الجذر يحملونه بنفس الإعداد',
    ],

    /*
    | Second pass of the same comparison. Forty-four of «مصانع»'s children carry
    | retail; these two were the exceptions, and neither is a special case — a
    | fish factory and a sweets factory sell goods like the rest of them.
    */

    [
        'child_name_ar' => 'أسماك',
        'root_slug' => 'factories',
        'service_key' => 'retail',
        'copy_from_child_ar' => 'مواد غذائية',
        'why' => 'مصنع أسماك لا يستطيع إدراج منتج، وكل مصنع غذائي بجواره يستطيع',
    ],

    [
        'child_name_ar' => 'حلويات',
        'root_slug' => 'factories',
        'service_key' => 'retail',
        'copy_from_child_ar' => 'مواد غذائية',
        'why' => 'مصنع حلويات بلا تجزئة — نفس الحالة، وبنفس إعداد جاره',
    ],

    /*
    |--------------------------------------------------------------------------
    | Third pass, 2026-08-10 — children that could sell NOTHING
    |--------------------------------------------------------------------------
    | Not the menu-versus-retail drift the shape detector is full of. These seven
    | carried `delivery` and `business_offers` and nothing else at all: no
    | booking, no menu, no retail. A merchant registering under one of them can
    | be found, can be delivered from, and has no way to say what he sells.
    |
    | Every one holds ZERO accounts, so nothing changes for anybody today, and
    | every donor was checked to carry a surface that fits the trade — the
    | standing trap here is handing a child a branch with no matching item type
    | and calling it fixed («حلويات» → hobbies_general, which has toys and books
    | and no sweets).
    |
    | Deliberately NOT included in that pass: «معدات ثقيلة» under شركات (3
    | accounts) — see the fourth pass below, which is where it was answered.
    */

    // فواكة، دواجن، خضروات under الزراعة: eleven of their fourteen siblings
    // carry menu, and the same three children carry it under المحلات. Produce is
    // sold off a market list in both places.
    [
        'child_name_ar' => 'فواكة',
        'root_slug' => 'agriculture-and-animals',
        'service_key' => 'menu',
        'copy_from_child_ar' => 'حبوب وغلال',
        'why' => 'بائع فاكهة تحت الزراعة لا يستطيع عرض شيء، و١١ من إخوته يحملون المنيو',
    ],

    [
        'child_name_ar' => 'دواجن',
        'root_slug' => 'agriculture-and-animals',
        'service_key' => 'menu',
        'copy_from_child_ar' => 'حبوب وغلال',
        'why' => 'نفس الحالة — سطح السوق (menu_market) هو ما يبيع به جيرانه',
    ],

    [
        'child_name_ar' => 'خضروات',
        'root_slug' => 'agriculture-and-animals',
        'service_key' => 'menu',
        'copy_from_child_ar' => 'حبوب وغلال',
        'why' => 'نفس الحالة',
    ],

    // The same three trades as COMPANIES. «كرڤان» is the donor because it is the
    // one child under شركات already selling off menu_market.
    [
        'child_name_ar' => 'فواكة',
        'root_slug' => 'companies',
        'service_key' => 'menu',
        'copy_from_child_ar' => 'كرڤان',
        'why' => 'شركة فاكهة بلا سطح بيع؛ menu_market هو ما يستخدمه جارها',
    ],

    [
        'child_name_ar' => 'حلويات',
        'root_slug' => 'companies',
        'service_key' => 'menu',
        'copy_from_child_ar' => 'كرڤان',
        'why' => 'نفس الحالة',
    ],

    [
        'child_name_ar' => 'خضروات',
        'root_slug' => 'companies',
        'service_key' => 'menu',
        'copy_from_child_ar' => 'كرڤان',
        'why' => 'نفس الحالة',
    ],

    /*
    | The one entry here with LIVE merchants, and the oldest open finding on the
    | board — flagged twice in earlier passes and never resolved.
    |
    | «مكتب» is the shipping office. Both of its siblings under شحن وتوصيل —
    | «شركة» and «مندوب» — carry `schedules`, which is how a carrier publishes a
    | trip leg and gets found by route and date. The office was the only one that
    | could not, and fourteen merchants stand on it. Reinstating a service only
    | ever ADDS a surface; nobody is moved and nothing is taken away.
    */
    [
        'child_name_ar' => 'مكتب',
        'root_slug' => 'shipping-delivery',
        'service_key' => 'schedules',
        'copy_from_child_ar' => 'شركة',
        'why' => 'مكتب شحن لا يستطيع نشر رحلة، وأخواه «شركة» و«مندوب» يستطيعان — ١٤ حسابًا',
    ],

    // A printing company is BOOKED, not stocked — you bring it a job. Thirteen
    // service companies under this root are booked the same way.
    [
        'child_name_ar' => 'طباعة',
        'root_slug' => 'companies',
        'service_key' => 'booking',
        'copy_from_child_ar' => 'دعاية وإعلان',
        'why' => 'شركة طباعة تُحجز بموعد كجارتها «دعاية وإعلان»، ولم تكن تملك أي سطح',
    ],

    /*
    |--------------------------------------------------------------------------
    | Fourth pass, 2026-08-10 — the last standing on the platform that could
    | sell nothing
    |--------------------------------------------------------------------------
    | «معدات ثقيلة» was held back from the third pass for a decision, and the
    | question it was held for turned out to be the wrong question: «which retail
    | donor fits heavy equipment?» has no good answer because the trade does not
    | sell goods at all.
    |
    | What it is was written down in its own configuration the whole time. Its
    | `delivery` shape is the haulage one — bulk_reservation, crane_winch,
    | full_truckload, partial_load — which is the first four types of «شحن بري
    | وبحري وجوى» beside it, exactly. And the one `line` group it carries is
    | «مركبات النقل والركاب»: معدات ثقيلة، جامبو، مقطورة. That group is a FLEET,
    | not a shelf. Every other child carrying it is a carrier, and every carrier
    | sells the same way — by publishing a leg with a date and a route.
    |
    | So the donor is its sibling, and the service is `schedules`, which is the
    | same answer «مكتب» got in the third pass for the same reason.
    |
    | Note what is NOT claimed: whether a heavy-equipment company should also
    | SELL or RENT a machine outright is a real question and still the owner's —
    | that would be a new surface, and nothing here invents one. This entry only
    | stops three live merchants from standing on a child that offers no way to
    | say anything at all.
    */
    [
        'child_name_ar' => 'معدات ثقيلة',
        'root_slug' => 'companies',
        'service_key' => 'schedules',
        'copy_from_child_ar' => 'شحن بري وبحري وجوى',
        'why' => 'ناقل ثقيل بلا أي سطح بيع — إعداد التوصيل عنده هو إعداد النقل الثقيل نفسه، وأخواه في الجذر ينشران الرحلات — ٣ حسابات',
    ],
];
