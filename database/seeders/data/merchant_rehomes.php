<?php

/*
|--------------------------------------------------------------------------
| An account filed under the wrong child
|--------------------------------------------------------------------------
| Not a taxonomy change — the children are both right and both stay. This is
| one business standing in the wrong one, which no seeder can infer: the child
| is correct for the trade it names, and only reading the business tells you it
| is a different trade.
|
| NOTHING IS DELETED and nothing about the business changes except which child
| it hangs from. `tick_option` is written BEFORE the move, so what the old child
| was saying about it survives the move as the merchant's own answer — the same
| rule ChildRootDetachSeeder follows when it folds a child away.
|
| The destination must stand under the account's OWN root, or the merchant
| disappears from every screen. The seeder refuses the entry otherwise.
*/

return [

    /*
    | «المونتال … هو لبيع قطاعات الالمونتال نفسها وليس الشباك والباب» — owner,
    | 2026-08-12, followed by «انقلهما الى باب وشباك».
    |
    | Both read as the window trade in their own names: «معرض الوميتال مطابخ و
    | شبابيك و ابواب» and «مطابخ الوميتال وحشب حديثه». They MAKE the opening;
    | «ألمونتال» sells the extrusion to whoever does. Both keep «ألومنيوم»,
    | which is the word «ألمونتال» was saying about them and is one of the
    | sixteen door types on the destination.
    |
    | «باب وشباك» #50 took معارض in the same commit — without it there was
    | nowhere under their root to move them to.
    |
    | ⚠ Neither list says «مطابخ». A kitchen is «أدراج ووحدات مطبخ» in «أثاث
    | وتشطيب منزلي», which is a different trade's vocabulary. Left for the owner.
    */
    [
        'user_id' => 1519,
        'from_child_ar' => 'ألمونتال',
        'to_child_ar' => 'باب وشباك',
        'tick_option' => 'ألومنيوم',
        'why' => 'معرض شبابيك وأبواب — يصنع الفتحة ولا يبيع القطاع',
    ],
    [
        'user_id' => 1313,
        'from_child_ar' => 'ألمونتال',
        'to_child_ar' => 'باب وشباك',
        'tick_option' => 'ألومنيوم',
        'why' => 'مطابخ ألومنيوم وخشب — يصنع الفتحة ولا يبيع القطاع',
    ],
];
