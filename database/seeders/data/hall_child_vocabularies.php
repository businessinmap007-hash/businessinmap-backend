<?php

/*
|--------------------------------------------------------------------------
| «قاعات» — the one child that had to register rooms it could not name
|--------------------------------------------------------------------------
| «قاعات تدريب» #282 has been the standing entry in
| `CoworkingWorkspaceTest::NAMELESS_UNITS` since that list was written: it is
| classified `units` — a training centre holds room A for you — it carries
| `requires_bookable_item = true`, and it had no `line` group for a
| `bookable_items` row to point at. Its two siblings both carry «أنواع
| المناسبات»; a training hall hosts no wedding, so it never got one.
|
| It borrows the three room rows of «مساحات العمل», written for the coworking
| desks the same day. A course room is a course room whether it is rented by
| the hour from a coworking space or by the day from a training centre.
|
| ── 2026-08-17: «راجع باقي أبناء القاعات بنفس الطريقة» ───────────────────
|
| Three children, nineteen ledger rows, and two of the three are finished:
| «قاعة مناسبات» sells eight occasions and «مركز مؤتمرات واجتماعات» seven, each
| narrowed by hand — he took «واي فاي» and «وايت بورد» off the wedding hall on
| 2026-08-14 and «عزاء» off the conference centre on the 15th.
|
| #282 was left holding ONE line row.
|
| Two of the three this file hands it were withdrawn by hand at 00:13:37 on
| 2026-08-14 — «قاعة اجتماعات» and «قاعة مؤتمرات» — and the ruling is right: a
| training hall is not sold as a meeting room, and the child that IS a meeting
| venue stands beside it. The file kept naming them anyway, so the seeder
| refused two links on every run and the disagreement lived only in the log.
| Trimmed to what he left.
|
| That leaves «قاعة كورسات» alone, and one row is a line with no choice. It
| matters more here than anywhere else in the sweep because #282 is classified
| `units`: it registers real rooms and each one points at a line option to be
| priced by. With one option, room A and room B are the same money by
| construction — the exact failure «bookable unit kind» exists to prevent.
|
| An Egyptian training centre lets three rooms and prices them apart:
|
|   قاعة كورسات    the ordinary classroom, twenty seats round a table
|   معمل كمبيوتر   machines on every desk, and the dearest of the three
|   قاعة محاضرات   the tiered hall, sold for a conference day
|
| Minted with `extend` and linked to #282 alone: «منطقة عمل مشتركة» owns this
| group and a coworking floor lets desks, not a computer lab.
*/

return [

    'root' => 'halls',

    'name_en_suffix' => 'Hall',

    /*
    | ── and the axis every hall prices on ─────────────────────────────────
    |
    | «أنواع المناسبات» is the line — a wedding, a conference — and the SAME
    | hall for the SAME occasion is two prices depending on the slot. A morning
    | booking and a full day are not the same money anywhere in Egypt, and none
    | of the three could say so.
    */
    'groups' => [
        'فترة الحجز' => [
            'name_en' => 'Booking Slot',
            'price_role' => 'modifier',
            'children' => [527, 528, 282],
            'options' => [
                'فترة صباحية' => 'Morning Slot',
                'فترة مسائية' => 'Evening Slot',
                'يوم كامل' => 'Full Day',
                'نهاية الأسبوع' => 'Weekend',
                'بالساعة' => 'Hourly',
            ],
        ],
    ],

    /*
    | Minted only. The per-child list below decides who lets each room, and
    | the coworking child must not inherit a computer lab from this.
    */
    'extend' => [
        'مساحات العمل' => [
            'معمل كمبيوتر' => 'Computer Lab',
            'قاعة محاضرات' => 'Lecture Hall',
        ],
    ],

    'links' => [
        // «قاعة اجتماعات» and «قاعة مؤتمرات» were here until 2026-08-17 and
        // are his withdrawals of 2026-08-14 — see the header.
        282 => ['مساحات العمل' => ['قاعة كورسات', 'معمل كمبيوتر', 'قاعة محاضرات']],
    ],
];
