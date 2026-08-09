<?php

/*
|--------------------------------------------------------------------------
| Salons: a trade that had made itself a top-level domain
|--------------------------------------------------------------------------
| Every root on this platform answers WHERE a business stands — مهن وحرفيين،
| ورش، محلات، مصانع، شركات، معارض. Root #443 «كوافير» answered WHAT it does, and
| so it sat in the customer's top row beside «شركات» (194 accounts) and «مصانع»
| (104) holding three.
|
| Worse, it made one trade askable in three places. Root #6 already carries child
| #136 «كوافير» — the correct home, with the correct booking config — while root
| #443 carried «كوافير رجالي» and «كوافير حريمى». A barber had three doors and
| the accounts split between them: 2 on رجالي, 1 on حريمى, 0 on the real one.
|
| And the two twins had already been given the right vocabulary: group #91
| «خدمات الكوافير والتجميل» — قص شعر، صبغة، مكياج، تجهيز عرائس، حلاقة ذقن — is a
| `line` group, i.e. the priced services themselves. Only #136, the child that
| should hold them, had none.
|
| So this is the fashion remodel again ([[fashion-three-axis-remodel]]): the
| child says the trade, the LINE options say what is actually sold, and
| «الجمهور المستهدف» — a `modifier` group that already exists and already reaches
| 11 children — says رجالي or حريمي or أطفال. A salon that serves men and women
| ticks both, which neither of the two children could ever express.
|
| Non-destructive, same contract as every other remodel: the retired children
| keep their master rows and lose only the pivot to root #443, the root itself is
| DEACTIVATED rather than deleted, and each moved account has its audience
| written into `option_user` BEFORE it is re-pointed, so nothing it claimed is
| lost. Re-inserting two pivot rows and one flag undoes the whole thing.
*/

return [

    /** The trade root being retired, and the domain root that absorbs it. */
    'retire_root_id' => 443,
    'target_root_id' => 6,

    /** The one child that survives — already under root #6 with the right config. */
    'keep_child_id' => 136,

    /*
    | The priced services a salon sells. The group is `line`: each is a row on
    | the price screen, not a description. It already exists — it was simply
    | attached to the two children being retired instead of the one that stays.
    */
    'line_group_name_ar' => 'خدمات الكوافير والتجميل',

    /*
    | Who it serves. `modifier`, multi-select — this is the axis that the two
    | children WERE, and the reason a family salon had nowhere to file.
    */
    'audience_group_name_ar' => 'الجمهور المستهدف',

    /*
    | Two facets the twins offered and #136 did not, so nothing a moving account
    | could already say becomes unsayable.
    */
    'carry_options' => ['كاش', 'واي فاي'],

    /*
    | Retired from root #443 => the audience ticked on each account on the way
    | out. A men's salon says رجالي; a women's says حريمي; from now on either may
    | say both.
    */
    'retire' => [
        'كوافير رجالي' => 'رجالي',
        'كوافير حريمى' => 'حريمي',
    ],
];
