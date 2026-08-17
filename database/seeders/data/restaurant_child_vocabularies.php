<?php

/*
|--------------------------------------------------------------------------
| «مطاعم وكافيهات» — the café that sold less than the cart parked outside it
|--------------------------------------------------------------------------
| Owner, 2026-08-17: «راجع باقي أبناء المطاعم والكافيهات بنفس الطريقة».
|
| Six children, all fluent, all on «بنود المنيو», and the band lists are
| curated: «أكل بيتى» had مشروبات ساخنة and باردة withdrawn by hand on
| 2026-08-10 (a home kitchen does not run a drinks counter), and «عربية قهوة
| ومأكولات» was rebuilt from the owner's own pins on 2026-08-16 18:17.
|
| ONE link — the rest of the review's work belongs in other files, and finding
| that out is most of what this file records.
|
| ── «كافيه» #64 had four bands, and this file is NOT where they live ─────
|
| إفطار، حلويات، مشروبات ساخنة، مشروبات باردة — and nothing else, while
| «عربية قهوة ومأكولات», which is a CART, carries eleven. The café was outsold
| by the pavement outside it, and nothing in the ledger explained it.
|
| The four missing bands were written here first and the seeder duly added
| them — and `MenuLineOptionsSeeder` took all four off again on its next run.
| «بنود المنيو» has its own per-child authority in `menu_line_bands.php`, which
| is a CLOSED map: a child carries the bands it names and no others. Two
| seeders, one adding and one removing, on every seed.
|
| So the café's counter is argued in that file, where it can be read beside
| «عصائر» and «سوبر ماركت» and the other rulings about what a shop stocks
| versus what a kitchen prepares. Check for a closed map before writing a link
| into a group somebody else owns.
|
| ── The cart that cannot say «تيك أواى», and why it stays that way ───────
|
| «عربية قهوة ومأكولات» #65 carries «توصيل طلبات» and nothing else from
| «التسليم والاستلام» — no «تيك أواى» — which reads backwards for a trade whose
| whole business is handing a cup through a window, and its five siblings all
| carry the row.
|
| It was written into this file as a link, and the seeder refused it: the owner
| withdrew #356 from #65 by hand on 2026-08-10, together with «توصيل مجانى»,
| «شحن», both payment terms and five menu bands. So it is not drift, it is a
| ruling, and the entry is gone.
|
| Kept as a note because the shape recurs: a cart, a window and no takeaway row
| looks exactly like a gap, and the only thing that tells them apart is the
| decisions ledger. Read it BEFORE writing the link, not after.
|
| ── A food court with no wifi ────────────────────────────────────────────
|
| «واي فاي» #568 sits on كافيه، مطعم and مطعم وكافيه and not on «مجمع مطاعم»,
| which is the one of the four with a seating area big enough for it to matter.
| «عربية قهوة» and «أكل بيتى» are rightly without: a cart and a home kitchen
| have no room to sit in, which is the same reason both skip «ملاءمة المكان».
*/

return [

    'root' => 'restaurants-cafes',

    'name_en_suffix' => 'Cafe',

    /*
    | Shared rows. «كافيه» and «مجمع مطاعم» each hang from this root alone, so
    | there is no second root for a scoped row to say anything different to.
    */
    'links' => [

        // The food court's seating area.
        108 => ['مرافق ومعدات' => ['واي فاي']],
    ],
];
