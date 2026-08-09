<?php

/*
|--------------------------------------------------------------------------
| A service switched off where it obviously belongs
|--------------------------------------------------------------------------
| Found by comparing each child's service set against the rest of its root:
| a child whose shape disagrees with its siblings is either misfiled or
| misconfigured, and this file is for the second kind.
|
| The first entry is the one that mattered. «مطعم» #245, thirteen accounts,
| under «مطاعم وكافيهات» — with its `menu` link AND config both `is_active = 0`,
| while «كافيه», «مطعم وكافيه» and «مجمع مطاعم» beside it all carry menu with an
| identical config. A restaurant on this platform could not publish a menu.
|
| Nothing here invents a setting: the config is COPIED from the named sibling
| that already offers the service under the same root, so reinstating cannot
| introduce a shape nobody chose.
|
| This is the opposite of a sweep. Every row is a named finding with a reason,
| and a service is never switched ON in bulk — an admin who turned something off
| on purpose is entitled to have that survive.
*/

return [

    [
        'child_name_ar' => 'مطعم',
        'root_slug' => 'restaurants-cafes',
        'service_key' => 'menu',
        'copy_from_child_ar' => 'مطعم وكافيه',
        'why' => 'مطعم بلا منيو — الرابط والإعداد كلاهما معطّل، وكل إخوته في الجذر يحملونه بنفس الإعداد',
    ],

    /*
    | Second pass of the same comparison. Forty-four of «مصانع»'s children carry
    | retail; these two were the exceptions, and neither is a special case — a
    | fish factory and a sweets factory sell goods like the rest of them.
    */

    [
        'child_name_ar' => 'أسماك',
        'root_slug' => 'factories',
        'service_key' => 'retail',
        'copy_from_child_ar' => 'مواد غذائية',
        'why' => 'مصنع أسماك لا يستطيع إدراج منتج، وكل مصنع غذائي بجواره يستطيع',
    ],

    [
        'child_name_ar' => 'حلويات',
        'root_slug' => 'factories',
        'service_key' => 'retail',
        'copy_from_child_ar' => 'مواد غذائية',
        'why' => 'مصنع حلويات بلا تجزئة — نفس الحالة، وبنفس إعداد جاره',
    ],
];
