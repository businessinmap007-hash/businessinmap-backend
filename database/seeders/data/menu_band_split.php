<?php

/*
|--------------------------------------------------------------------------
| «بنود المنيو» was four vocabularies wearing one name
|--------------------------------------------------------------------------
| Owner, 2026-08-10: «مجموعة بنود منيو أظن حان الوقت لتقسيمها ولدينا جزء منها
| مميز جدا وهو اصناف المواد الغذائية».
|
| The 47 bands were never one list. Reading which CHILDREN carry each one — not
| the names, the use — they fall into four sets with almost no overlap:
|
|   14  restaurant headings   → carried by مطعم، كافيه، مجمع مطاعم، أكل بيتى
|   27  grocery aisles        → carried by سوبر/مني/هايبر ماركت and the
|                               single-product food shops beside them
|    3  farm supplies         → أعلاف، مواشي، معدات المزارع
|    3  listing rows          → a showroom using the menu as a listing surface
|
| «بنود المنيو» keeps the fourteen it was named for. The other three move out.
|
| A band belonging to two worlds keeps ONE row and stays where it is most at
| home: «ساندوتشات» and «مشروبات ساخنة» are restaurant headings that a
| supermarket also sells, and the child link is what decides who sees them — a
| supermarket keeps carrying them out of «بنود المنيو» and nothing is lost.
|
| Applied by \Database\Seeders\MenuBandSplitSeeder. Moving an option between
| groups is safe here: MenuLineOptionsSeeder matches an existing band by
| `name_en` and leaves its group alone, so the split is not undone on the next
| run — it only ever creates a band that does not exist yet.
*/

return [

    'groups' => [

        /*
        | What a grocer sells, aisle by aisle. `line`, like the group it comes
        | out of: these ARE the headings a supermarket prices under, which is
        | what separates them from «أصناف المنتجات الغذائية» — that one is a
        | `modifier` saying what a trade DEALS IN, and the owner named it as the
        | part of this group that was already right.
        */
        [
            'name_ar' => 'أقسام السوبر ماركت',
            'name_en' => 'Supermarket Aisles',
            'price_role' => 'line',
            'bands' => [
                'خضار وفاكهة', 'مواد غذائية', 'ألبان وبيض', 'أجبان', 'لحوم ودواجن',
                'مجمدات', 'مكرونات وأرز وحبوب', 'معلبات', 'زيوت وسمن', 'بهارات',
                'سناكس وتسالي', 'حلويات وشوكولاتة', 'مشروبات', 'عصائر', 'مخبوزات',
                'وافل', 'آيس كريم', 'سلطة فواكة', 'فسيخ', 'رنجة', 'فحم',
                'منظفات', 'عناية شخصية', 'منتجات أطفال', 'مستلزمات حيوانات أليفة',
                'أدوات منزلية',
            ],
        ],

        /*
        | The farm. Three bands that reached the menu group because a feed store
        | and a rabbit farm sell through the same surface a restaurant does —
        | which says nothing at all about what they sell.
        */
        [
            'name_ar' => 'مستلزمات المزارع',
            'name_en' => 'Farm Supplies',
            'price_role' => 'line',
            'bands' => ['مستلزمات زراعية', 'ماشية وطيور', 'معدات ومستلزمات'],
        ],

        /*
        | Not headings at all — a single row meaning «the thing on display».
        | A showroom listing a car, a flat or a wardrobe has one band and prices
        | each unit under it. Two of the three sit on no child today; they are
        | kept because that is what a showroom will reach for.
        */
        [
            'name_ar' => 'صفوف معروضة',
            'name_en' => 'Listing Rows',
            'price_role' => 'line',
            'bands' => ['مركبة معروضة', 'وحدة معروضة', 'قطعة أثاث'],
        ],
    ],

    /*
    | Left in «بنود المنيو» — the fourteen it was named for, and the two added
    | since. Listed so the split can be checked in one place rather than
    | inferred from what is missing.
    |
    | This list is ENFORCED, not reported. It used to be documentation, and
    | documentation is not enough for a band that has to come BACK: «فطائر» was
    | sent out with the aisles in the first split and out again into «بنود
    | المخبوزات والحلويات» in the second, so nothing short of a rule that says
    | «these belong to the menu» could return it. `reclaim()` reads this list and
    | pulls its bands home from any group the two splits created — and only from
    | those, so a band deliberately filed elsewhere is still nobody's business
    | but the file that filed it.
    |
    | «فطائر» — «اضف فطائر كبند فى بنود المنيو», owner, 2026-08-16, answering the
    | question this file's own rule had left open: «a band belonging to two
    | worlds keeps ONE row and stays where it is most at home». A pie is a menu
    | section a food court and a pie shop sell hot, and a grocer carries it out
    | of the menu group — which is exactly what «ساندوتشات» does in the other
    | direction, and the reason there is still ONE «فطائر» and not two.
    |
    | Nobody loses it: «مخابز» and «حلويات» keep the same option id and both
    | already carry «ساندوتشات» out of this group, so not one child sees a
    | heading it was not already showing.
    |
    | «كريب» was added by MenuLineOptionsSeeder on 2026-08-16 and belonged here
    | from the start; unlisted, it tripped the leftovers warning on every run.
    */
    'kept' => [
        'مقبلات', 'سلطات', 'شوربة', 'مشويات', 'أطباق رئيسية', 'ساندوتشات', 'بيتزا',
        'مكرونة / باستا', 'مأكولات بحرية', 'إفطار', 'حلويات', 'مشروبات ساخنة',
        'مشروبات باردة', 'وجبات أطفال', 'كريب', 'فطائر',
    ],
];
