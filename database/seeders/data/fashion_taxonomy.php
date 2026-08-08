<?php

/*
|--------------------------------------------------------------------------
| Fashion: what a shop SELLS stops being what it IS
|--------------------------------------------------------------------------
| Owner, 2026-08-08: «هناك محلات احذية فقط وكوتشى فقط واكسسوار فقط لكن هناك
| محلات بها كلهم — ملابس زفاف وملابس رسمية ايضا فى محل واحد».
|
| Root #14 «ملابس و اكسسوارات» answered "what does this shop sell?" THREE times:
|   1. the CHILD name       — كوتشي، ملابس رسمي، ملابس النوم…
|   2. the retail item type — 6 keys, IDENTICAL on all nine children
|   3. the line options     — «موضة وعناية شخصية», scoped down per child
|
| And a business has exactly one `category_child_id`, so the shop that sells
| clothes AND shoes AND accessories had no home. The damage was measurable:
| كوتشي carried ZERO line options and could name nothing it sold, ملابس رسمي
| carried two and could not say it also sells shoes, and «ملابس» — the one child
| with all seven — held 27 of the root's 63 businesses.
|
| This is the health remodel again ([[health-three-axis-remodel]]): the child
| says WHERE it sells (shop, factory, showroom, online — which the ROOTS already
| say), the line options say WHAT, multi-select, and «الجمهور المستهدف» says for
| whom. The retail types are left alone: they bucket the central catalog, a
| different question, and all nine children already shared all six.
|
| Non-destructive, exactly like HealthRemodelSeeder: the retired children keep
| their master rows and only lose the pivot to root #14, so re-inserting one row
| undoes the whole move. Every re-pointed business has its former specialty
| written into `option_user` FIRST, so nothing it claimed is lost.
*/

return [

    'root_id' => 14,

    'group_name_ar' => 'موضة وعناية شخصية',

    /*
    | The vocabulary the collapse needs so nothing becomes unsayable. «جلود وشنط
    | وأحذية» #227 stays exactly where it is — children under مصانع، معارض and
    | شركات answer with it — and the finer words are added BESIDE it, not carved
    | out of it. A shoe shop says أحذية; a sneaker shop says كوتشي; the shop with
    | all of it says all of it.
    */
    'new_options' => [
        'أحذية' => 'Shoes',
        'كوتشي' => 'Sneakers',
        'شنط وحقائب' => 'Bags',
        'ملابس رسمية' => 'Formal Wear',
        'ملابس كاجوال' => 'Casual Wear',
        'ملابس رياضية' => 'Sportswear',
        'ملابس نوم' => 'Sleepwear',
    ],

    /*
    | The three that stay under root #14, and why each earns its place:
    |   #59  ملابس            — the general clothing shop (27 businesses)
    |   #168 جلود وشنط وأحذية — also a factory/showroom/company child elsewhere
    |   #8   اكسسوار          — also a factory/company child elsewhere
    |
    | Retiring #168 or #8 from this root would not remove them from those, so
    | keeping all three costs nothing and keeps store-level browsing for anyone
    | who wants it.
    */
    'keep' => [59, 168, 8],

    /*
    | Retired from root #14 => the child its businesses move to, and the option
    | ticked on each of them on the way out so the claim survives the move.
    */
    'retire' => [
        'ملابس كاجوال' => ['to' => 59, 'option' => 'ملابس كاجوال'],
        'ملابس رسمي' => ['to' => 59, 'option' => 'ملابس رسمية'],
        'ملابس النوم' => ['to' => 59, 'option' => 'ملابس نوم'],
        'ملابس رياضية' => ['to' => 59, 'option' => 'ملابس رياضية'],
        'ملابس زفاف' => ['to' => 59, 'option' => 'فساتين زفاف'],
        'كوتشي' => ['to' => 168, 'option' => 'كوتشي'],
    ],

    /*
    | With three children left, scoping them is what silenced كوتشي in the first
    | place — a shop may only tick what it actually sells, and the merchant's own
    | ticks are the narrowing. #95 أقمشة is NOT here: a fabric merchant is a
    | different trade and the owner did not ask for it.
    */
    'unscope' => [59, 168, 8],
];
