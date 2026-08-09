<?php

/*
|--------------------------------------------------------------------------
| A child filed under the wrong root
|--------------------------------------------------------------------------
| Owner, 2026-08-09: «انقل مأذون شرعى من مهن وحرفيين الى مكاتب».
|
| Not a cleanup and not a remodel — a filing correction. The child, its
| vocabulary, its services and its accounts all stay exactly as they are; only
| the root it hangs from changes, because that root is where a customer looks.
|
| A مأذون is not a craftsman you call to the house. He keeps a register and
| receives you at his office, next to «محاماه» — which root #19 already carries.
|
| Declared here rather than written into a one-off script so the next move is one
| line, and so the record of what moved and why survives the commit message.
|
| Rules the seeder enforces:
|   - the child must currently hang from `from_root`, or the move is skipped;
|   - service links, configs and fee rows move WITH it, keeping their stored
|     config and provenance — a move must not silently rewrite an admin's work;
|   - any account sitting on the child has its `category_id` moved too, so
|     nobody is left pointing at a root the child no longer belongs to.
*/

return [

    [
        'child_name_ar' => 'مأذون شرعى',
        'from_root_slug' => 'professions',   // مهن وحرفيين
        'to_root_slug' => 'offices',         // مكاتب
        'why' => 'يستقبل في مكتبه ويحرّر عقدًا، فهو إلى جانب «محاماه» لا إلى جانب الحرفيين',
    ],
];
