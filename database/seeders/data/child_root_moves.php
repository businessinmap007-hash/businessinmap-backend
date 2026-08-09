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

    /*
    | From the pass over the remaining roots. Each of these was standing in a
    | root whose own children answer a different question, and each holds at
    | most one account, so nothing is disturbed by the move.
    */

    [
        // ورش already holds سمكري، ميكانيكي، كهربائي سيارات، ابواب سيارات،
        // مركز سيارات. This is the sixth of them and was the only one filed
        // among the household crafts.
        'child_name_ar' => 'اصلاح زجاج السيارات',
        'from_root_slug' => 'professions',
        'to_root_slug' => 'workshops',
        'why' => 'ورشة تُصلح سيارة، فمكانها بين السمكري والميكانيكي لا بين النقاش والسباك',
    ],

    [
        // Root 17 is «المحلات أو أونلاين» — things sold over a counter. A studio
        // is a place you book time in, which is what root 9 is for; it already
        // holds «فوتوجرافر».
        'child_name_ar' => 'استوديوهات',
        'from_root_slug' => 'shops-online',
        'to_root_slug' => 'arts-entertainment',
        'why' => 'الاستوديو مكان يُحجز فيه وقت لا محل يبيع، وبجواره «فوتوجرافر»',
    ],

    [
        // Root 7 «الرياضة» holds PLACES you go to and book: جيم، ملاعب، حمام
        // سباحة، أكاديمية، نادي. A supplements seller is a shop, and the root
        // says where the business stands.
        'child_name_ar' => 'مكملات غذائية',
        'from_root_slug' => 'sports',
        'to_root_slug' => 'shops-online',
        'why' => 'جذر الرياضة أماكن تُحجز، وبائع المكملات محل — بجوار «عطور» و«أدوات تجميل»',
    ],

    [
        // ورش ومراكز صيانة is where something broken is repaired. A عفشجى
        // repairs nothing — he moves your furniture, which is root 5's question.
        'child_name_ar' => 'عفشجى',
        'from_root_slug' => 'workshops',
        'to_root_slug' => 'shipping-delivery',
        'why' => 'ينقل العفش ولا يُصلح شيئًا، فهو نقل لا صيانة',
    ],
];
