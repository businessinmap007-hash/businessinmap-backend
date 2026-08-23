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

    /*
    | ── rename, without a merge: «نجار باب وشباك» #84 ─────────────────────
    | Owner, 2026-08-18. The only entry here that folds nothing — #84 swallowed
    | no sibling; it was simply carrying the wrong kind of name.
    |
    | «نجار» names the CRAFTSMAN, and the craftsman already has his own child:
    | «نجار موبيليا» #49, under مهن وحرفيين. #84 stands under ورش ومراكز صيانة
    | and nowhere else — it is a PLACE, and it now reads like the sibling it
    | sits beside, «ورشة أثاث ونجارة» #544. That leaves «باب وشباك» #50 to the
    | trader under مصانع، شركات، المحلات and معارض, which is the split
    | `child_root_detachments.php` was written to protect: the old name argued
    | against it, this one states it.
    |
    | The English is corrected in the same breath: the row came from the live
    | tree as `Door and window Workshop`, with a lower-case `window` that
    | matches nothing else in the table.
    |
    | ⚠ Applied to the live tree BEFORE it was recorded here, which is why this
    | entry exists at all: the rename was real and nothing in the repository
    | knew it, so the next seed would have handed #84 its old name back. The
    | seeder reports it as «بالفعل» and writes nothing — recording what happened
    | is the entire point, not repeating it.
    */
    [
        'id' => 84,
        'from_ar' => 'نجار باب وشباك',
        'to_ar' => 'ورشة باب وشباك',
        'to_en' => 'Door & Window Workshop',
    ],

    /*
    | ── merge: مستلزمات مطاعم + مستلزمات كافيهات ──────────────────────────
    | «ادمج مستلزمات المطاعم والكافيهات تحت كل الاقسام الرئيسية» — owner,
    | 2026-08-23.
    |
    | #247 keeps it. The two lists do not share a single row — ماكينات قهوة،
    | مطاحن بن، شيشة against أفران، شوايات، عجانات — which is exactly why they
    | looked like two trades and exactly why they are one: a supplier who sells
    | an espresso machine sells a display fridge, and no buyer shops for the
    | two in different places. The union is the catalogue, and the union under
    | one name is what the merchant was going to have to advertise anyway.
    |
    | #247 takes «مستلزمات المقاهي» in the same change, or it arrives at its
    | wider name unable to say the half it just swallowed.
    */
    [
        'id' => 247,
        'from_ar' => 'مستلزمات مطاعم',
        'to_ar' => 'مستلزمات مطاعم وكافيهات',
        'to_en' => 'Restaurant & Café Supplies',
    ],

    /*
    | ── the craftsman, not the premises ───────────────────────────────────
    | «الاسم يكون فني صيانة أجهزة منزلية ليكون واضح انه الفرد ويمكن تمييزه عن
    | ورش صيانة الأجهزة المنزلية» — owner, 2026-08-23.
    |
    | Not cosmetic. #22 stands under مهن وحرفيين and #546 «ورشة صيانة أجهزة»
    | under ورش ومراكز صيانة, and «صيانة اجهزة منزلية» names the WORK, which
    | both of them do. A customer choosing between «I call someone out» and «I
    | carry it in» was reading the same words in two places.
    |
    | It also puts the child back in step with its own root: مهن وحرفيين is
    | كهربائي، سباك، نقاش، نجار موبيليا، فني ستائر، متخصص كوافير — every one of
    | them a person. The two children that still name a job rather than a
    | tradesman are this one and «صيانة تبريد وتكييف» #15, which has the same
    | twin one root over («تبريد وتكييف» #240) and is left for the owner to
    | rule on rather than swept along.
    */
    [
        'id' => 22,
        'from_ar' => 'صيانة اجهزة منزلية',
        'to_ar' => 'فني صيانة أجهزة منزلية',
        'to_en' => 'Home Appliance Technician',
    ],
];
