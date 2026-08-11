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
*/

return [

    'root' => 'halls',

    'name_en_suffix' => 'Hall',

    'links' => [
        282 => ['مساحات العمل' => ['قاعة كورسات', 'قاعة اجتماعات', 'قاعة مؤتمرات']],
    ],
];
