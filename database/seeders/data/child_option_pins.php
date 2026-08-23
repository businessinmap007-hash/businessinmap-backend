<?php

/*
|--------------------------------------------------------------------------
| Pins the repository has to know about
|--------------------------------------------------------------------------
| A pin says «the owner put this row on this child by hand; no seeder may drop
| it». It is written by the admin card as he clicks, and until now it lived
| ONLY in `category_child_option_decisions` — nothing in the repository knew a
| pin existed, so a database rebuilt from the seeders came up without any of
| them and every seeder went back to arguing with a decision that had been
| made.
|
| This file is the small half of that gap: the pins that are a RULING rather
| than a click — the ones a reviewer would want to argue with, and the ones
| that a test elsewhere already demands an explanation for.
|
| It does not try to be the whole ledger. The 496 rulings he made through the
| card in August are his working record and belong in the table; what belongs
| here is a pin that some other part of the platform will refuse to accept
| without it.
|
| ⚠ A pin and a withdrawal are a TOGGLE. Recording one deletes the other, so an
| entry here overrides a withdrawal of the same row — which is why every entry
| names its reason.
|
| Consumed by \Database\Seeders\ChildOptionDecisionsSeeder, before it enforces.
*/

return [

    /*
    | ── «تقسيط بدون فوائد» on the three property children ─────────────────
    |
    | «وخيارات الدفع مثل الكاش والتقسيط على ٣ و٥ و٧ و١٠ سنوات وتقسيط بدون
    |  فوائد» — owner, 2026-08-23.
    |
    | Option #204 has been hand-set-only since 2026-08-10: it was the ONE row
    | of «الدفع والسداد» granted per root, which is how it reached 297 children
    | while كاش reached 95 — and «no interest» is a commercial claim only the
    | merchant can make, not a default. PaymentTermsScopeTest enforces that by
    | demanding every child holding it be pinned or ticked by a merchant.
    |
    | Real estate is the one trade in Egypt where the claim is the HEADLINE.
    | Every developer's hoarding carries it, and a buyer comparing two
    | compounds compares it before he compares the price. So it is granted, and
    | granted the way the policy says a grant must be made: by hand, named, and
    | with the instruction that asked for it written beside it.
    |
    | Root 0 — every root these children stand under, which is one.
    */
    [
        'children' => [517, 518, 522],   // مكتب عقاري، مطور عقاري، مالك عقار
        'root_id' => 0,
        'group' => 'الدفع والسداد',
        'options' => ['تقسيط بدون فوائد'],
        'why' => 'العقار هو التجارة التي يكون فيها «بدون فوائد» عنوان الإعلان لا استثناءً',
    ],
];
