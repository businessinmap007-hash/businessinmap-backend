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

    // خضار وفاكهة (فواكة + خضروات, merged 2026-08-12) and دواجن under الزراعة: eleven of their fourteen siblings
    // carry menu, and the same three children carry it under المحلات. Produce is
    // sold off a market list in both places.
    [
        'child_name_ar' => 'خضار وفاكهة',
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


    // The same three trades as COMPANIES. «كرڤان» is the donor because it is the
    // one child under شركات already selling off menu_market.
    [
        'child_name_ar' => 'خضار وفاكهة',
        'root_slug' => 'companies',
        'service_key' => 'menu',
        'copy_from_child_ar' => 'كرڤان',
        'why' => 'شركة فاكهة بلا سطح بيع؛ menu_market هو ما يستخدمه جارها',
    ],

    /*
    | «حلويات» stood here for `menu` under «شركات» and was detached from that
    | root on 2026-08-16 — «حذف من الشركات الابن مفاتيح - حلويات». A sweets shop
    | is a kitchen, which the owner ruled on 2026-08-10 when it took the bakery
    | counter, and it never belonged among the contractors and insurers.
    |
    | The entry goes with the membership rather than being left to fail
    | silently: this file resolves a child under a NAMED root, so a row for a
    | root it has left finds nothing and is skipped, and the skip reads like a
    | data error to whoever runs the seeder next. It keeps `menu` under
    | «المحلات», «معارض» and «مصانع», where it actually stands.
    */


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
        // The donor left «شركات» for «شحن وتوصيل» on 2026-08-16, and it is the
        // same donor either way — the shape being copied is a CARRIER's, not a
        // root's. Without this the seeder looks for it beside the recipient,
        // finds nothing, and skips the entry silently; the two trades stay
        // where they are and the copy is simply never available again.
        'copy_from_root_slug' => 'shipping-delivery',
        'why' => 'ناقل ثقيل بلا أي سطح بيع — إعداد التوصيل عنده هو إعداد النقل الثقيل نفسه، وأخواه في الجذر ينشران الرحلات — ٣ حسابات',
    ],

    /*
    |--------------------------------------------------------------------------
    | Fifth pass, 2026-08-10 — a surface a sibling has and this one does not
    |--------------------------------------------------------------------------
    | Not the «could sell nothing» class: all three of these can already sell,
    | off a menu. What they cannot do is list a catalog product, while the child
    | standing next to them selling the identical goods can.
    |
    | The bar for this pass is deliberately higher than «the majority has it»,
    | because that test alone is noise — 24 service companies under شركات lack
    | retail and should. The rule used here is the one the third pass learned the
    | hard way: THE DONOR'S BRANCH MUST FIT THE TRADE. Each of these three takes
    | a branch built for exactly what it sells.
    |
    | Deliberately left out, and reported instead — the donor branch is broader
    | or narrower than the trade, which is a per-child judgement rather than a
    | copy:
    |   · «بن» — `tea_coffee` fits it exactly, but the other 21 types do not: a
    |     coffee merchant would be offered nappies and detergent.
    |   · «مخابز» — branch 47 has no bread. `breakfast` is cereal.
    |   · «حلويات» — it has `chocolate`, and a sweets shop sells baklava.
    |   · «أسماك»، «دواجن»، «خضروات»، «فواكة»، «مجمدات» — fresh weight goods, and
    |     the branch is packaged groceries. Only `frozen` comes close.
    |   · «عصائر» — ruled a KITCHEN by the owner the same day; giving it a shelf
    |     would undo that.
    */

    /*
     * «سيارات» under معارض was reinstated here on 2026-08-10, copying retail
     * from «معرض سيارات» because the two were the same trade with the same six
     * car item types and one of them could not list a car.
     *
     * On 2026-08-17 the owner drew the conclusion the entry was arguing
     * towards: «خليه معرض سيارات ونفذ الطى والنقل». #53 is folded into #188
     * and retired, and #188 stands under «سيارات», so the entry names a child
     * under a root neither of them is in. Its retail service went with the
     * fold. Removed rather than re-keyed — a reinstatement is a repair, and
     * this one is repaired.
     */

    /*
     * The mirror image of the entry above, found by the same audit and
     * confirmed by the owner on 2026-08-11: «شغله».
     *
     * «آثاث» was the ONLY one of the root's twenty-eight children with
     * `booking` switched off — off since 2026-08-04 00:30, with no data file,
     * scope rule or decision record saying why. Twenty-six merchants, and a
     * showroom's whole model here is that a customer books a viewing.
     * «مفروشات» sits beside it carrying booking and is the same trade one
     * shelf over, which is why it is the donor.
     */
    [
        'child_name_ar' => 'آثاث',
        'root_slug' => 'exhibitions',
        'service_key' => 'booking',
        'copy_from_child_ar' => 'مفروشات',
        'why' => 'معرض أثاث لا يمكن حجز معاينة فيه، و٢٧ من ٢٨ ابنًا في الجذر يمكن — ٢٦ حسابًا',
    ],

    // The three general markets are one trade at three sizes. «سوبر ماركت» has
    // the 22-type grocery branch and the other two have nothing but a market
    // list, though the goods on the shelf are identical.
    [
        'child_name_ar' => 'هايبر ماركت',
        'root_slug' => 'shops-online',
        'service_key' => 'retail',
        'copy_from_child_ar' => 'سوبر ماركت',
        'why' => 'نفس تجارة «سوبر ماركت» بحجم أكبر ولا يستطيع إدراج منتج من الكتالوج — ٦ حسابات',
    ],

    [
        'child_name_ar' => 'مني ماركت',
        'root_slug' => 'shops-online',
        'service_key' => 'retail',
        'copy_from_child_ar' => 'سوبر ماركت',
        'why' => 'نفس الحالة بحجم أصغر — ١٢ حسابًا',
    ],
];
