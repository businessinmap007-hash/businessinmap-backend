<?php

/*
|--------------------------------------------------------------------------
| «معارض» — the one axis it was missing
|--------------------------------------------------------------------------
| Owner, 2026-08-11: «انتقل الى الاب معارض» … «امنحها للاثنين».
|
| **The root needed nothing else, and that was verified rather than assumed.**
| All twenty-eight children could already name their trade — they are the same
| child rows the «مصانع» and «شركات» passes had just given a vocabulary to, so
| the work arrived here ahead of the question. Four other checks came back
| clean: no merchant filed under a (root, child) pairing that does not exist,
| no service wired by one half, no active config with an empty
| `allowed_item_types`, no child without a service.
|
| Eighteen children report "no `line` group" and that is not a gap — they are
| goods traders carrying their vocabulary as a `modifier`, which is what the
| goods rule requires: the priced rows are catalog products.
|
| ── What WAS missing ──────────────────────────────────────────────────────
|
| «نوع التعامل» — بيع وشراء · إيجار · تبديل — reached three of the twenty-eight:
| the two vehicle showrooms and «سيارات». The narrowness is CORRECT and was
| left alone: a carpet showroom neither rents nor part-exchanges, so the axis
| would offer it one possible answer, and a modifier with one answer is noise
| on a pricing screen rather than a question.
|
| Furniture is the exception the owner confirmed. **Renting event furniture is
| a real trade, and taking the old suite against the new one is older still.**
|
| ── Why root-scoped ───────────────────────────────────────────────────────
|
| «آثاث» #116 and «مفروشات» #115 also stand under مصانع، شركات and (for #115)
| المحلات. A furniture FACTORY does not part-exchange your sofa. So these are
| written with `category_id = 21` — the same reasoning as «نظام التصنيع» under
| مصانع, pointing the other way.
*/

return [

    'root' => 'exhibitions',

    'name_en_suffix' => 'Showroom',

    /*
    |--------------------------------------------------------------------------
    | 2026-08-17 — «راجع باقي أبناء المعارض بنفس الطريقة»
    |--------------------------------------------------------------------------
    | Twenty-nine children now, and the second pass found one thing, root-wide.
    |
    | **Twenty-one of the twenty-nine cannot say «كاش» or «تقسيط» here.**
    |
    | It is not a set of withdrawals. The whole root holds exactly ONE payment
    | ruling — «صينى وخزف» #228, pinned on 2026-08-16 03:58 — and «حلويات» #210
    | is blocked from another root. Everything else is simply an absence.
    |
    | And the same children say it everywhere else. «سجاد» #52 carries كاش and
    | تقسيط under المحلات، شركات and مصانع; «آثاث» #116 under شركات and مصانع;
    | «مراتب»، «إسفنج»، «أصواف»، «لعب أطفال»، «مفروشات» the same. The rows are
    | ROOT-SCOPED, so a grant that never ran for #21 leaves the trade answering
    | in three storefronts and silent in the fourth — and the fourth is the
    | showroom, which in Egypt is the one place «تقسيط» is the first question
    | anybody asks. A furniture, appliance, carpet or mattress معرض sells on
    | instalments before it sells anything else.
    |
    | `child_option_groups.php` has said so the whole time: its root bundle for
    | «معارض» is `$goods`, and `$goods` contains `payment_terms`. This does not
    | decide anything new — it delivers a grant the map already declares and
    | that reached three roots out of four.
    |
    | `children: 'all'` is used here for the reason that file states — it is
    | safe only for an axis the ROOT asks, and every showroom on earth takes
    | cash. A child that joins «معارض» tomorrow inherits it, and the withdrawal
    | record still has the last word: #210 is refused on every run.
    */
    'groups' => [
        'الدفع والسداد' => [
            'name_en' => 'Payment Terms',
            'price_role' => 'descriptive',
            'scope' => 'root',
            'children' => 'all',
            'options' => [
                'كاش' => 'Cash',
                'تقسيط' => 'Instalment',
            ],
        ],
    ],

    /*
    | «باب وشباك» #50 took معارض on 2026-08-12 so that two showroom accounts
    | filed under «ألمونتال» — «معرض الوميتال مطابخ و شبابيك و ابواب» and
    | «مطابخ الوميتال وحشب حديثه» — had somewhere to be moved to. The standing
    | was wired by TradeAxesSeeder; the WORDS are wired here.
    |
    | A mirror rather than naming the group: it copies the ids the child already
    | holds under its other three roots, so a narrowing survives. It says the
    | same sixteen things in a showroom as in a factory.
    */
    'mirror_links' => [
        // «حالة المنتج» travels with it: a showroom's floor model is sold
        // second-hand as often as new, and without a modifier the trade would
        // stand in this root able to list sixteen products and price none of
        // them differently.
        50 => ['أنواع الأبواب والشبابيك', 'حالة المنتج'],   // باب وشباك
    ],

    /*
    | Links written against THIS root rather than shared. Same shape as
    | `links`, and every child appears once.
    */
    'root_links' => [
        116 => ['نوع التعامل' => 'all'],   // آثاث
        115 => ['نوع التعامل' => 'all'],   // مفروشات
    ],

    /*
    |--------------------------------------------------------------------------
    | 2026-08-17 — «كسر زيرو»
    |--------------------------------------------------------------------------
    | Owner: «وايضا من ضمن حالات السيارات كسر زيرو وهى وصف للسيارات قليلة
    | الكيلو مترات».
    |
    | It is a third point on the axis that already exists, not a new axis.
    | «حالة المنتج» reads جديد → مستعمل and the Egyptian car market reads
    | جديد → كسر زيرو → مستعمل: a car off the showroom floor with a few thousand
    | kilometres on it, first owner, still under warranty. It is priced BETWEEN
    | the two and a buyer searches for it by name. Sold as «مستعمل» it is
    | undersold, and as «جديد» it is a lie.
    |
    | ── Why it goes in «حالة المنتج» and not a car-only group ────────────────
    |
    | Same question, same answer set. A second group would ask «ما حالة
    | السيارة؟» beside a group asking «ما حالة المنتج؟», and a merchant could
    | tick «مستعمل» in one and «كسر زيرو» in the other and mean nothing by
    | either — the two half-populated versions of one idea that
    | option_group_splits.php names as the disease. It is a `modifier` because
    | the group is: the same model is three prices.
    |
    | ── And why it does not reach the other 111 children ─────────────────────
    |
    | «حالة المنتج» is one of the widest groups on the platform — 113 children
    | hold «جديد» — and a sofa is never «كسر زيرو». The row is minted with
    | `extend`, which creates and links NOTHING, and handed out by name to the
    | three vehicle showrooms alone.
    |
    | It is deliberately NOT added to `product_condition.options` in
    | `child_option_groups.php`. That array is what ChildOptionGroupsSeeder
    | manages — it grants the list per root and prunes it — so a row left out of
    | it is neither handed to every goods child nor deleted from these three.
    | «دفع مسبق» and «الدفع عند الاستلام» both stand in that exact position.
    |
    | ── The three ────────────────────────────────────────────────────────────
    |
    | «سيارات» #53، «معرض سيارات» #188 and «معرض موتوسيكلات» #189 — the only
    | children on the platform that sell a vehicle rather than a service around
    | one, and all three already carry جديد and مستعمل. A low-mileage motorbike
    | is «كسر زيرو» in the same breath as a car.
    |
    | «قطع غيار سيارات» #44 is not among them: a spare part is «أصلي وكيل» or
    | «تجاري» — it has «درجة قطعة الغيار» for that — and mileage says nothing
    | about a part.
    */
    'extend' => [
        'حالة المنتج' => [
            'كسر زيرو' => 'Nearly New',
        ],
    ],

    'links' => [
        53 => ['حالة المنتج' => ['كسر زيرو']],    // سيارات
        188 => ['حالة المنتج' => ['كسر زيرو']],   // معرض سيارات
        189 => ['حالة المنتج' => ['كسر زيرو']],   // معرض موتوسيكلات
    ],
];
