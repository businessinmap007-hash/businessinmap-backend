<?php

/*
|--------------------------------------------------------------------------
| A child that must also stand under a second root — usually so it can
| receive a merge
|--------------------------------------------------------------------------
| Owner, 2026-08-23: «ادمج مستلزمات المطاعم ومستلزمات الكافيهات فى مستلزمات
| مطاعم وكافيهات تحت كل الاقسام الرئيسية», and the same «تحت كل الاقسام
| الرئيسية» for «أكياس بلاستيك» into «مواد تعبئة وتغليف».
|
| `child_root_moves.php` moves a child from one root to another and
| `child_root_detachments.php` takes it off one. Neither ADDS a root, and a
| fold cannot happen without that: ChildRootDetachSeeder refuses to reassign
| merchants to a destination that does not stand under the same root — quite
| rightly, since the account keeps its `category_id` and would otherwise land
| on a child its root cannot see.
|
| So «under all parent categories» is two instructions, and this is the first:
| the keeper takes the roots its sibling stood under, THEN the sibling folds
| into it.
|
| ── What travels with the attachment ──────────────────────────────────────
|
| The service wiring, mirrored from a root the child already stands under —
| link and config together, the config copied rather than left empty, because
| an empty config reads everywhere as «every item type on the platform». The
| options do not: a shared row (`category_id = 0`) already reaches the new root
| and a root-scoped row was scoped on purpose.
|
| `mirror_services` can be turned off for an attachment where the new root is
| a genuinely different trade shape. Nothing needs that yet.
|
| Consumed by \Database\Seeders\ChildRootAttachSeeder — which MUST run before
| ChildRootDetachSeeder.
*/

return [

    /*
    | «مستلزمات مطاعم» #247 stands under شركات، معارض، مصانع and swallows
    | «مستلزمات كافيهات» #37, whose only root is المحلات. A supplier who sells
    | an espresso machine sells a display fridge; the two lists never overlapped
    | by a single row, which is what made them look like two trades.
    */
    [
        'child_name_ar' => 'مستلزمات مطاعم وكافيهات',  // #247, renamed first
        'root_slug' => 'shops-online',
        'why' => 'ليستقبل «مستلزمات كافيهات» تحت الجذر الذي كان يقف تحته',
    ],

    /*
    | «مواد تعبئة وتغليف» #204 stands under شركات and مصانع. «اكياس بلاستيك»
    | #221 stands under المحلات and مصانع, and its one merchant is under one of
    | them — so the keeper needs المحلات before the fold can move him.
    */
    [
        'child_name_ar' => 'مواد تعبئة وتغليف',
        'root_slug' => 'shops-online',
        'why' => 'ليستقبل «اكياس بلاستيك» تحت الجذر الذي كان يقف تحته',
    ],
];
