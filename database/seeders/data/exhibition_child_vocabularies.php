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
];
