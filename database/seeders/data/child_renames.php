<?php

/*
|--------------------------------------------------------------------------
| A child that has to answer to a wider name after a merge
|--------------------------------------------------------------------------
| «نفذ ١ و٢ و٣ وادمج مواشي وأرانب فقط» — owner, 2026-08-12, approving four
| merges in «زراعية وحيوانية». Every one of them keeps ONE child and folds the
| rest into it (`child_root_detachments.php`), and a keeper that swallows its
| sibling must stop advertising only half of what it now covers: «فواكة» that
| also carries vegetables has to say so.
|
| Renaming is a rename, not a new row — the id survives, so every option link,
| service config, price and account it carries survives with it. That is the
| whole reason a merge keeps a child rather than creating one.
|
| ⚠ This seeder MUST run before ChildRootDetachSeeder: the detachments name
| their destination by the NEW name.
|
| ⚠ And every data file that names a child by `name_ar` has to be updated in
| the same commit — delivery_child_branches، menu_line_bands،
| service_reinstatements and the rest look children up by name, and a rename
| they do not know about silently unwires the child.
*/

return [

    /*
    | ── merge 1: three equipment traders, one trade ──────────────────────
    | «معدات مزارع مواشي» keeps it. The three shared ONE list from 2026-08-12
    | and differed only in which animal — which is what the option says, not
    | what a child says.
    */
    [
        'id' => 171,
        'from_ar' => 'معدات مزارع مواشي',
        'to_ar' => 'معدات وتجهيزات المزارع',
        'to_en' => 'Farm Equipment & Supplies',
    ],

    /*
    | ── merge 2: فواكة + خضروات ───────────────────────────────────────────
    | A 100% option match, and one trade name in Egypt.
    */
    [
        'id' => 114,
        'from_ar' => 'فواكة',
        'to_ar' => 'خضار وفاكهة',
        'to_en' => 'Fruit & Vegetables',
    ],

    /*
    | ── merge 3: تقاوي زراعية + أسمدة ─────────────────────────────────────
    | The crop-inputs shop sells all three, and «مبيدات» had no child at all —
    | the merge closes a gap rather than only removing a row.
    */
    [
        'id' => 14,
        'from_ar' => 'تقاوي زراعية',
        'to_ar' => 'تقاوي وأسمدة ومبيدات',
        'to_en' => 'Seeds, Fertiliser & Pesticides',
    ],

    /*
    | ── merge 4: مواشي + أرانب ────────────────────────────────────────────
    | Only these two. «مزارع سمكية» and «دواجن» stay their own children:
    | aquaculture is a different licence, a different cycle and a different
    | capital, and «دواجن» is a fresh SELLER — it carries «أقسام الطازج
    | واللحوم» and «حالة الدواجن», not a producer's vocabulary.
    */
    [
        'id' => 170,
        'from_ar' => 'مواشي',
        'to_ar' => 'مواشي وأرانب',
        'to_en' => 'Livestock & Rabbits',
    ],
];
