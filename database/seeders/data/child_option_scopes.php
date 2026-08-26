<?php

/**
 * The children that can only answer PART of a group they carry.
 *
 * Same fix the sports pools got, generalised. A group is attached to a child as
 * a whole, so «نجف و تحف» was offered غرفة نوم and سفرة, a wedding hall was
 * offered مؤتمرات وندوات, a car wash was offered معدات ثقيلة, and a pyjama shop
 * was offered فساتين زفاف. None of those is a wrong GROUP — it is the right
 * group handed over whole to a child that can only use a slice.
 *
 * Scoping, not splitting: the group stays one list, and only this child's view
 * of it narrows. That is the same conclusion the sports/medical fold-back
 * reached — per-CHILD scoping is what makes a long list usable, not headings.
 *
 * Shape: group name_ar => [child id => [option ids the child may answer]].
 * A child absent from a group's map keeps the whole list.
 *
 * Read by ChildOptionScopeSeeder, and honoured by the two seeders that would
 * otherwise hand the whole group back: LinkCategoryChildrenToOptionsSeeder and
 * VehicleOptionGroupsSeeder.
 *
 * This narrows a child's list under EVERY root at once — it answers "this child
 * cannot use that option at all". The other, finer question — "this child
 * answers differently under مصانع than under معارض" — is per-root and lives on
 * `category_child_option.category_id`; see App\Services\CategoryChildOptionScope.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | أثاث وتشطيب منزلي
    |--------------------------------------------------------------------------
    | #39 غرفة نوم · #64 سجاد ومفروشات · #77 غرفة أطفال · #113 سفرة · #169 آثاث
    | #188 آثاث فندقي · #219 أدراج ووحدات مطبخ · #266 آثاث مكتبي · #312 صالون
    | #337 أنتريه · #355 تابلوه · #398 ركنه
    */
    'أثاث وتشطيب منزلي' => [
        56 => [355],           // نجف — a chandelier shop hangs a tableau, not a dining set
        57 => [355],           // نجف و تحف
        // #160 «مطابخ و دريسنج» became a bench inside #544 on 2026-08-10, and
        // the workshop makes everything the joinery #49 makes — but weaves no
        // carpet. Without the entry, rule 3 («اثاث|…|مطابخ») hands it the whole
        // list back, سجاد ومفروشات and all.
        544 => [39, 77, 113, 169, 188, 219, 266, 312, 337, 355, 398], // ورشة أثاث ونجارة
        /*
         * «وليس من ضمن المفروشات الصالون - الانترية - الطابلوة ولكن مفارش
         *  واصناف شبيهه» — owner, 2026-08-12.
         *
         * It had five rows and four of them were the FURNITURE trade next door:
         * صالون، أنتريه، ركنه، تابلوه. «سجاد ومفروشات» is the only one that was
         * ever about soft furnishing, and what it deals in is now said properly
         * by «أصناف المفروشات» — مفارش، ملايات، لحاف، مناشف.
         */
        115 => [64], // مفروشات — the soft-furnishing row, and only it
        49 => [39, 77, 113, 169, 188, 219, 266, 312, 337, 355, 398], // نجار موبيليا — makes all of it but weaves no carpet
        // #116 آثاث keeps the whole list: as a showroom, workshop, factory and
        // company child it covers every piece there is
    ],

    /*
    |--------------------------------------------------------------------------
    | أنواع المناسبات
    |--------------------------------------------------------------------------
    | #675 أفراح · #676 خطوبة · #677 عيد ميلاد · #678 حفلات تخرج · #679 مؤتمرات
    | #680 اجتماعات عمل · #681 ندوات · #682 معارض · #683 حفلات موسيقية
    | #684 عزاء · #685 إفطار جماعي · #686 تصوير مناسبات
    */
    'أنواع المناسبات' => [
        527 => [675, 676, 677, 678, 683, 684, 685, 686], // قاعة مناسبات
        528 => [678, 679, 680, 681, 682, 685, 686],      // مركز مؤتمرات واجتماعات
    ],

    /*
    |--------------------------------------------------------------------------
    | مركبات النقل والركاب
    |--------------------------------------------------------------------------
    | #51 باص 50 راكب · #184 معدات ثقيلة · #214 جامبو · #220 كوتش
    | #248 ميكروباص 15 · #250 ميني باص 25 · #251 ميني ڤان 7 · #280 ربع نقل
    | #281 ربع نقل صندوق · #365 مقطورة
    */
    'مركبات النقل والركاب' => [
        // passenger fleets
        278 => [51, 220, 248, 250, 251],   // نقل ركاب — «كوتش» #220 withdrawn by hand 2026-08-14
        /*
         * «خدمة ليموزين» #169 — declared EMPTY on 2026-08-17.
         *
         * The three it was scoped to are the three the owner withdrew from it
         * on 2026-08-14 00:32: كوتش، ميكروباص ١٥، ميني ڤان ٧. The scope granted
         * them, the ledger took them back, and the entry had been asking for a
         * minibus on behalf of a limousine service on every run since.
         *
         * His reading is the trade's: a ليموزين company hires out a CAR, and
         * «نوع المركبة» — سيدان، SUV — is its whole line. The fleet sizes
         * belong to «نقل ركاب» standing beside it, which is why both children
         * exist.
         */
        169 => [],                         // خدمة ليموزين
        /*
         * «سائق» #85, added 2026-08-11 when the last mute children were given
         * a vocabulary. Declaring it here was NOT optional: linking the five
         * passenger sizes without saying so leaves the child UNSCOPED, and
         * VehicleOptionGroupsSeeder hands the whole list back on its next run —
         * a driver-for-hire offered a trailer and a load of heavy plant.
         */
        85 => [51, 220, 248, 250, 251],    // سائق — he drives you, not your cargo

        // freight fleets
        284 => [184, 214, 280, 281, 365],  // سيارات نقل
        68 => [184, 214, 280, 281, 365],   // شركة شحن
        198 => [184, 214, 280, 281, 365],  // مكتب شحن
        // «شحن بري وبحري وجوى» #166 folded into «شركة» #68 on 2026-08-18. Its
        // narrowing goes with it: #68 already holds the same five vehicles.
        // «نقل دولي» #154 folded into «شحن بري وبحري وجوى» #166 on 2026-08-12
        // and reaches no root; a narrowing cannot reach it either.
        243 => [251, 280, 281],            // مندوب — a courier, not a convoy
        139 => [184, 214, 365],            // معدات ثقيلة

        // what a bay can physically take
        46 => [248, 250, 251, 280, 281],   // مغسلة سيارات
        /*
         * «جراج» #119 → nothing, 2026-08-12. Even narrowed to five sizes this
         * was the wrong axis: the list says which vehicles a HAULIER hires out,
         * and a garage parks a bus rather than hiring one out. It says «خدمات
         * الجراج والانتظار» now, and the bus survives there as «انتظار حافلات
         * ونقل» — a row about the SPACE.
         */
        119 => [],                         // جراج
        // #244 ونش إنقاذ keeps the whole list — a rescue winch tows all of it
    ],

    /*
    |--------------------------------------------------------------------------
    | مرافق ومعدات — #568 واي فاي · #569 وايت بورد
    |--------------------------------------------------------------------------
    */
    'مرافق ومعدات' => [
        64 => [568],   // كافيه
        245 => [568],  // مطعم
        246 => [568],  // مطعم وكافيه
        // «انترنت كافيه» #155 stood here until the owner folded it into
        // «مركز ترفيهي» — «غير منتشر حاليا» (`child_root_detachments.php`).
        // It stands under no root any more.
        // halls, conference centres and training rooms keep both
    ],

    /*
    |--------------------------------------------------------------------------
    | موضة وعناية شخصية — the PRODUCT half
    |--------------------------------------------------------------------------
    | #21 اكسسوارات · #89 ملابس · #135 أقمشة · #181 صناعة يدوية
    | #227 جلود وشنط وأحذية · #349 بدلة زفاف · #382 فساتين زفاف
    |
    | A pyjama shop was being offered فساتين زفاف. The audience rows that used
    | to sit in this group left for «الجمهور المستهدف» below — one group was
    | answering WHO it is for and WHAT is sold at once, and only the second is
    | a priced line.
    */
    /*
    |--------------------------------------------------------------------------
    | أنواع الإكسسوارات — a phone shop is not offered a handbag
    |--------------------------------------------------------------------------
    | «موبيلات و اكسسوار» #186 (17 merchants) was given «أجهزة الموبايل
    | وملحقاتها» on 2026-08-16 because it had no word for a phone. What was not
    | done that day is take away the list it had been answering instead, and
    | the two overlap badly: اكسسوار موبايل، شواحن وكابلات، سماعات and أغطية
    | وحافظات are said twice, once in each group, and what does NOT repeat is
    | حقائب وشنط، مجوهرات، إكسسوار شعر وتجميل، إكسسوار رياضي — a fashion
    | accessory shop's stock on a phone counter.
    |
    | Declared empty rather than narrowed to the four that overlap, because the
    | overlap is the reason: everything a mobile shop sells is already in its
    | own thirteen-row list, said once. «اكسسوار» #8 keeps the group whole —
    | that IS the fashion accessory trade, and it is the child this list was
    | written for.
    */
    'أنواع الإكسسوارات' => [
        186 => [],               // موبيلات و اكسسوار — its own list says it all
    ],

    'موضة وعناية شخصية' => [
        /*
         * «أقمشة» #95 — a fabric merchant is a different trade, and the
         * narrowing was right while it left him with the wrong ONE row.
         *
         * Cut to #135 «أقمشة», this group became a one-row line that says only
         * «I sell fabric», which the child's own name already says. And a line
         * present, however empty, pre-empts the promotion rule: under «المحلات»
         * and «مصانع» he was offered that single row to price, while under
         * «معارض» and «شركات» — where the row had never been written — the
         * reader promoted «أنواع الأقمشة» and he priced قطن، كتان، حرير، دنيم,
         * all fifteen. The same trade, two pricing screens, and the two worse
         * ones were the roots most fabric merchants stand under.
         *
         * A declared EMPTY says what the comment always meant: he answers none
         * of the fashion product list. «أخشاب» #301 shows the finished shape —
         * its timber list is a `line` in its own right and needs no promotion —
         * and «أنواع الأقمشة» cannot follow it, because it is a shared modifier
         * that genuinely qualifies a garment for ملابس and مفروشات. Promotion
         * is the mechanism for exactly that case; this only stops blocking it.
         */
        95 => [],                // أقمشة — none of the fashion list; he sells cloth
        /*
         * «اكسسوار» #8 joined it on 2026-08-17, and for once the owner had
         * already done nine tenths of the work.
         *
         * On 2026-08-14 00:45 he took NINE of its ten rows off by hand —
         * أحذية، كوتشي، شنط وحقائب، the four garment rows and both wedding
         * rows. What survived is #21 «اكسسوارات»: one row, on a child called
         * «اكسسوار», saying the child's own name back at it. Under its other
         * three roots he had already cleared the group completely.
         *
         * Unlike #95 this costs no promotion — #8 has «أنواع الإكسسوارات» and
         * fourteen real rows to price — so the row is only noise on a pricing
         * screen. Declared empty so the tenth follows the nine.
         *
         * What that same save ALSO did, and what this review nearly undid: it
         * withdrew «كاش» and «تقسيط» from #8 under root 14 alone. The child
         * carries both under مصانع، شركات and المحلات, so the accessory shop
         * answers «how do I pay you» in three storefronts and stays silent in
         * the fourth — which reads exactly like the per-root drift that
         * `category_child_option.category_id` makes easy to get wrong, and is
         * a deliberate root-scoped ruling. The seeder refused the links; the
         * mistake was reading a `tail`-truncated ledger dump for the second
         * time in one sweep.
         */
        8 => [],                 // اكسسوار — its own name is not a product line
        // #59 ملابس and #168 جلود وشنط وأحذية keep the WHOLE list as of
        // 2026-08-08: root #14 collapsed to those three, and scoping them is
        // exactly what left «كوتشي» unable to name a single thing it sold. The
        // shop that carries clothes AND shoes AND accessories must be able to
        // say all three; the narrowing is the merchant's own ticks now.
        // #54، #112، #258، #267، #297 left the root entirely — FashionRemodelSeeder.
        //
        // #60 ملابس جاهزة still keeps the whole list: as a factory and showroom
        // child it does carry every piece there is, wedding wear included.
    ],

    /*
    |--------------------------------------------------------------------------
    | تعبئة وتغليف ومستلزمات
    |--------------------------------------------------------------------------
    | #27 أطباق فويل · #93 أكياس قهوة · #147 أكواب فوم · #148 أطباق فوم
    | #150 للطباعة · #246 علبة معدن · #271 مواد تعبئة وتغليف · #273 أكواب ورقية
    | #284 أكواب بلاستيك · #293 مطبوع · #344 أدوات مكتبية
    |
    | The first EMPTY list in this file, and it is deliberate. Absence here means
    | "keep the whole group", so the only way to say "this child answers none of
    | it" is to say so out loud. Child #232 «طباعة مواد تعبئة وتغليف» was retired
    | from the group by the owner (approved 2026-08-08); the add-only
    | LinkCategoryChildrenToOptionsSeeder handed all 11 back on every run until
    | this line existed.
    |
    | It prints packaging, it does not sell it: the sibling #204 «مواد تعبئة
    | وتغليف» is the one that stocks cups and foil, and it keeps the whole list.
    */
    'تعبئة وتغليف ومستلزمات' => [
        232 => [],  // طباعة مواد تعبئة وتغليف — a printer, not a supplier
    ],

    /*
    |--------------------------------------------------------------------------
    | ماركات السيارات — the 43 makes, a modifier group
    |--------------------------------------------------------------------------
    | The same retirement, one root over. Child #43 «قطع غيار سيارات» sits under
    | #23 alongside #232, and the owner stripped it to «التسليم والاستلام» alone
    | in the same pass. VehicleOptionGroupsSeeder is the add-only one here, and
    | it handed all 43 makes back every run.
    |
    | It is NOT the spare-parts child that matters: #44, same name and same root
    | as the rest of the vehicle trade, keeps every make and is the one a real
    | business (1 of them) sits on. #43 is its older duplicate — «Car spare
    | parts» vs «Car Sspare parts», created two days apart in 2020 — and carries
    | no business at all.
    */
    'ماركات السيارات' => [
        // «قطع غيار سيارات» #43 (the empty duplicate) — hard-deleted by the
        // owner 2026-08-26. #44 is the live one.
    ],

    /*
    |--------------------------------------------------------------------------
    | الجمهور المستهدف — #141 حريمي · #232 رجالي · #217 أطفال
    |--------------------------------------------------------------------------
    | Unscoped as of 2026-08-08. It used to narrow «ملابس زفاف» and «ملابس رسمي»
    | to adults, and both left root #14 in the fashion collapse — the shop that
    | dresses grooms now says so with a line option instead of a category, and
    | the same shop may well dress children too.
    */
    'الجمهور المستهدف' => [
        // every clothing child keeps all three
    ],

    /*
    |--------------------------------------------------------------------------
    | عقارات وممتلكات
    |--------------------------------------------------------------------------
    | An empty list STRIPS the group — the only entry here that means «this
    | child may answer none of it».
    |
    | Rule 9 in LinkCategoryChildrenToOptionsSeeder matches the word «ورش[ةه]»,
    | and rightly so: a workshop is a PROPERTY a real-estate business lists for
    | rent. But «ورشة سيارات» is not a unit for rent, it is the garage itself,
    | and the day the workshop domains were created (2026-08-10) that rule was
    | about to offer a metal shop شقة، فيلا، أرض — thirteen properties it does
    | not sell. The name matched; the meaning did not.
    */
    'عقارات وممتلكات' => [
        544 => [], // ورشة أثاث ونجارة
        545 => [], // ورشة حدادة وخراطة
        546 => [], // ورشة صيانة أجهزة
        // «ورشة سيارات» #543 never reaches the rule — its exclude pattern
        // catches «سيار» first.
    ],

    /*
    |--------------------------------------------------------------------------
    | أصناف المنتجات الغذائية — the same question, asked twice
    |--------------------------------------------------------------------------
    | «راجع التكرار والتشابه بينهم» — owner, 2026-08-10.
    |
    | This `modifier` list of twenty food ranges and the `line` aisle list beside
    | it are one vocabulary written twice. «زيوت وسمن» and «معلبات» are the same
    | word in both, and thirteen of the twenty restate an aisle: «حبوب وبقوليات»
    | + «أرز» + «مكرونة» against «مكرونات وأرز وحبوب», «ألبان وأجبان» against
    | «ألبان وبيض» + «أجبان», and so on down the list.
    |
    | It is not redundant everywhere, which is why the group survives. Three of
    | its eight children carry no market list at all — مواد غذائية،
    | مواد غذائية ومنظفات، استيراد وتصدير — and «which ranges do you deal in» is
    | a real question for a wholesaler with no priced heading behind it. That is
    | what a modifier is for.
    |
    | The five below carried BOTH and were being asked twice, once priced and
    | once not. An empty list is the declared way to say «this child answers
    | none of this group», and ChildOptionScopeTest checks that it actually
    | holds.
    |
    | ── 2026-08-21: the owner resolved the duplication the other way ──────────
    |
    | This file chose the AISLES for the three market children and struck the
    | ranges. By hand, over an hour, he did the reverse: he pinned all twenty
    | ranges onto سوبر ماركت، مني ماركت and هايبر ماركت, and withdrew from the
    | two aisle lists exactly the rows the ranges already say — «مواد غذائية»،
    | «مكرونات وأرز وحبوب»، «زيوت وسمن»، «بهارات»، «لحوم ودواجن»، «أجبان».
    |
    | And the aisle rows he KEPT are the tell: خضار وفاكهة، فسيخ، رنجة، مجمدات،
    | ألبان وبيض. Those are counters — weighed, cut, sold by the kilo behind
    | glass — and a counter is a priced heading. «معلبات» is not a counter; it
    | is a range, and it belongs in the list that describes ranges. He kept the
    | line where the shop does work and the modifier where it just stocks.
    |
    | So the three come out of this block. It is his answer to this file's own
    | question, arrived at from the other side, and it is the better one.
    */
    'أصناف المنتجات الغذائية' => [
        113 => [], // مجمدات — 1 account; carries the fresh counters only
        128 => [], // حبوب وغلال — 0
    ],

    /*
    |--------------------------------------------------------------------------
    | The two aisle groups, taken off the two producers, 2026-08-16
    |--------------------------------------------------------------------------
    | «حبوب وغلال - دواجن الخيارات بها هى خيارات السوبر ماركت وليست انواع الحبوب
    | الحقيقة ولا الدواجن من فراخ وسمان وبط وحمام الخ».
    |
    | An aisle is a place a SHOPPER looks; these two sell the goods themselves,
    | wholesale and for export. Both now have a real line of their own in
    | `agriculture_child_vocabularies.php` — «أنواع الدواجن والطيور» and «أنواع
    | الحبوب والغلال» — and the aisle is no longer a question they are asked.
    |
    | The groups keep every other carrier: the three markets and the grocery
    | trades answer them correctly, and «خضار وفاكهة» #114 went the same way on
    | 2026-08-14. Nothing is deleted; only these children's view narrows.
    */
    'أقسام الطازج واللحوم' => [
        229 => [], // دواجن — sells birds, not cheese, herring and frozen goods
    ],

    'أقسام البقالة الجافة' => [
        128 => [], // حبوب وغلال — «مواد غذائية» was its whole trade in one word
    ],

    /*
    |--------------------------------------------------------------------------
    | The curtain fittings go back to the fittings shop, 2026-08-16
    |--------------------------------------------------------------------------
    | «ستائر وديكور المفروض به انواع الستائر وليس لوازم الستائر».
    |
    | #76 had been borrowing this list from «لوازم ستائر» #9 since 2026-08-11.
    | The two were ruled separate trades the next day — «لا تدمج لوازم ستائر
    | وستائر وديكور فهم بندين مختلفين» — and this is the other half of that
    | ruling: #9 sells the rails, rings and brackets; #76 sells and hangs the
    | curtain, and now has «أنواع الستائر والديكور» to say so.
    |
    | #9 is not listed, so it keeps the whole list — it is the list's owner.
    */
    'لوازم الستائر' => [
        76 => [], // ستائر و ديكور
    ],


    /*
    |--------------------------------------------------------------------------
    | قطع الغيار حسب الآلة
    |--------------------------------------------------------------------------
    | #1742 سيارات · #1743 معدات ثقيلة · #1744 أجهزة منزلية · #1745 مكن صناعي
    | #1746 مصاعد · #1747 تبريد وتكييف · #1748 دراجات · #1749 مستوردة
    | #1750 مستعملة
    |
    | The list answers WHICH MACHINE. The last two answer neither — «مستوردة» is
    | «نطاق التعامل / إستيراد» and «مستعملة» is «حالة المنتج / مستعمل», and
    | «قطع غيار» #263 already carries both groups. A merchant asked the same
    | thing in two places will sooner or later answer it two different ways.
    |
    | The rows stay in the group; only this child's view of it narrows.
    */
    'قطع الغيار حسب الآلة' => [
        263 => [1742, 1743, 1744, 1745, 1746, 1747, 1748],
    ],

    /*
    |--------------------------------------------------------------------------
    | أنواع الأبواب والشبابيك
    |--------------------------------------------------------------------------
    | «المونتال … هو لبيع قطاعات الالمونتال نفسها وليس الشباك والباب» — owner,
    | 2026-08-12.
    |
    | «ألمونتال» #17 had seven of the sixteen door types because the two trades
    | stand beside each other. It sells the EXTRUSION to the workshop that makes
    | the window; it does not make the window. Its own list is «قطاعات ومنتجات
    | الألومنيوم», written the same day.
    |
    | The second declared empty in this file, and for the same reason as the
    | first: «طباعة مواد تعبئة وتغليف» #232 PRINTS packaging, it does not SELL
    | it. A merchant's own tick still outranks this, as everywhere else.
    */
    'أنواع الأبواب والشبابيك' => [
        17 => [],
    ],


    /*
    |--------------------------------------------------------------------------
    | أنواع الأجهزة الكهربائية
    |--------------------------------------------------------------------------
    | «أدوات كهربائية» #87 — «Electric Tools», and the name_en had said so all
    | along — was offering ثلاجات and غسالات because it had borrowed the
    | appliance shop's list for want of one. It has «العدد والأدوات
    | الكهربائية» now.
    |
    | «قطع غيار أجهزة كهربائية» #264 KEEPS the list and is not here: for a parts
    | dealer it is the right axis — WHICH MACHINE — and what it was missing is
    | the second one, «قطع غيار الأجهزة المنزلية». Same two-axis shape as «قطع
    | غيار سيارات» #44, which says both the marque and the system.
    */
    'أنواع الأجهزة الكهربائية' => [
        87 => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | مستلزمات المزارع
    |--------------------------------------------------------------------------
    | «اكمل بعنقود المزارع» — owner, 2026-08-12.
    |
    | Three rows: «مستلزمات زراعية»، «ماشية وطيور»، «معدات ومستلزمات». They
    | restate the child's own name rather than saying anything about it, and
    | seven children had them and nothing else. All seven now name their trade
    | properly — machinery, farm equipment, or the stock they raise — so being
    | asked a fourth time whether they deal in «معدات ومستلزمات» is noise.
    |
    | Declared empty rather than deleted: #14 تقاوي and #99 أسمدة keep the group.
    | They are bulk traders whose other axis is «وحدة البيع», and for them
    | «مستلزمات زراعية» is at least true.
    |
    | #107 أعلاف was on that list too, on the same reasoning, until 2026-08-16 —
    | when a review of the whole root found it was the LAST child of «زراعية
    | وحيوانية» whose line was still this grab-bag. «at least true» is not a
    | thing to price on: a feed merchant is asked WHICH FEED before anything
    | else and these two words cannot answer it. It has «أنواع الأعلاف» now.
    */
    'مستلزمات المزارع' => [
        12 => [],   // معدات زراعية
        102 => [],  // مزارع سمكية
        107 => [],  // أعلاف — has «أنواع الأعلاف» since 2026-08-16
        170 => [],  // مواشي
        171 => [],  // معدات مزارع مواشي
        // «معدات مزارع دواجن» #230، «معدات مزارع أرانب» #235، «أرانب» #236 —
        // all three hard-deleted by the owner 2026-08-26 (rootless list
        // review); folded into «معدات وتجهيزات المزارع»/«مواشي وأرانب» well
        // before that.
    ],

    /*
    |--------------------------------------------------------------------------
    | معدات وتجهيزات المزارع
    |--------------------------------------------------------------------------
    | It held three narrowings for one day. On 2026-08-12 the owner merged the
    | three equipment children into one, and #171 — now «معدات وتجهيزات
    | المزارع» — serves every animal, so it takes the WHOLE list: milking
    | parlour and incubator both. #230 and #235 hang from no root, and a
    | narrowing cannot reach a child nobody can see.
    |
    | So the group is absent from the map, which reads as «no narrowing» — and
    | that is exactly right. The entry is kept as a comment because the three
    | slices are the record of what each animal actually needed, and the next
    | person to split them apart should start from here:
    |
    |   مواشي  حلابة وسيلوهات، بلا حضانة — البقرة تُولد
    |   دواجن  حضانات وفقاسات، بلا حلابة
    |   أرانب  أقفاص ومشارب، بلا هذه ولا تلك
    */

    /*
    |--------------------------------------------------------------------------
    | أنواع الثروة الحيوانية والسمكية
    |--------------------------------------------------------------------------
    | One question — «what do you raise» — and three answers that do not
    | overlap at all.
    |
    | #2150 أبقار · #2151 جاموس · #2152 أغنام · #2153 ماعز · #2154 عجول تسمين
    | #2155 جمال · #2156 خيول · #2157 أرانب تربية · #2158 أرانب تسمين
    | #2159 بلطي · #2160 بوري · #2161 قراميط · #2162 زينة · #2163 زريعة
    */
    'أنواع الثروة الحيوانية والسمكية' => [
        // «مواشي وأرانب» since the 2026-08-12 merge, so it takes the rabbit
        // rows too — the fold's whole point is that the difference was a row.
        170 => [2150, 2151, 2152, 2153, 2154, 2155, 2156, 2157, 2158],
        // «مزارع سمكية» stayed its own child: a different licence, a different
        // cycle, and not one row in common with the four-legged stock.
        102 => [2159, 2160, 2161, 2162, 2163],
    ],

    /*
    |--------------------------------------------------------------------------
    | تسهيلات ومرافق طبية
    |--------------------------------------------------------------------------
    | #1973 يقبل التأمين الطبي · #1974 حجز مواعيد أونلاين · #1975 خدمة طوارئ ٢٤
    | ساعة · #1976 صيدلية داخلية · #1977 معمل تحاليل داخلي · #1978 أشعة داخلية
    | #1980 مدخل لذوي الاحتياجات · #1981 انتظار سيارات · #1982 قسم سيدات
    |
    | The list was handed to all seven health children whole, and the owner then
    | went through it by hand on 2026-08-12 on ONE rule: keep what is true of
    | this trade. A lab lost «أشعة داخلية» and kept «معمل تحاليل داخلي». A
    | radiology suite lost «معمل تحاليل داخلي» and kept «أشعة داخلية». A clinic
    | lost all three. A pharmacy lost the whole block.
    |
    | He reached four of the seven. «مركز حجامة» was one of the three he did not,
    | and it was still claiming an in-house pharmacy, an in-house laboratory, an
    | in-house imaging department and a 24-hour emergency service — a cupping
    | room advertising an MRI. This applies his rule to it and stops there:
    | «مستشفى» and «مركز طبي» are the other two he did not reach, and for them
    | all nine are plausible, so neither is narrowed.
    |
    | Recorded here rather than as a withdrawal because a scope is the right
    | shape for it — the child may not answer these at all, under any root — and
    | because `health_child_vocabularies.php` names #542 in the group's own
    | `children` list and would hand them straight back on the next seed.
    */
    'تسهيلات ومرافق طبية' => [
        542 => [1973, 1974, 1980, 1981, 1982], // مركز حجامة
    ],

    /*
    |--------------------------------------------------------------------------
    | الرعاية والتنويم
    |--------------------------------------------------------------------------
    | #4004 تنويم بغرفة خاصة · #4005 تنويم بغرفة مشتركة · #4006 رعاية مركزة
    | #4007 رعاية متوسطة · #4008 حضانة حديثي الولادة · #4009 عملية جراحية
    | #4010 جراحة اليوم الواحد · #4011 ولادة طبيعية · #4012 ولادة قيصرية
    | #4013 غسيل كلوي · #4014 جلسة علاج كيماوي · #4015 نقل بسيارة إسعاف
    |
    | The group was added on 2026-08-17 because «مستشفى» and «مركز طبي» were
    | indistinguishable — identical specialties, identical scans, identical
    | tests — and nothing on the platform had a word for an admission.
    |
    | It was also narrowed here, on the reading that the difference between the
    | two is whether the patient sleeps there: #515 was cut to the three rows a
    | day-case unit runs. **The owner pinned all nine back the same hour**
    | (2026-08-17 01:00, `category_child_option_decisions`), which is him saying
    | an Egyptian «مركز طبي» does keep beds — and it does; the word covers
    | everything from a polyclinic to a small private hospital.
    |
    | So the entry is gone rather than argued with, and the group is left in the
    | map with no children so the next reader does not re-derive the same
    | narrowing. A pin is restored dead-last by `ChildOptionDecisionsSeeder`, so
    | leaving the scope in place would only have made the two seeders take the
    | rows off and put them back on every seed — the exact fight this file has
    | caused before.
    */
    'الرعاية والتنويم' => [
        // deliberately empty — see above
    ],

    /*
    |--------------------------------------------------------------------------
    | ملاءمة المكان
    |--------------------------------------------------------------------------
    | #137 عائلي · #264 ممنوع التدخين · #2389 سيدات · #2390 رجال · #2391 ميكس
    |
    | The last three arrived on 2026-08-13 for the twenty trades where the ROOM
    | is segregated — a gym, a pool, a hall, a trainer. «فندق عائم / بوت نيلي»
    | is not one of them; it holds them only because
    | `HospitalityOptionRestoreSeeder` restores whole groups and this group grew
    | after that file was written. A Nile cruiser sells cabins to a mixed
    | manifest, and its four intact siblings hold exactly the two rows below.
    |
    | The restore file was corrected in the same change so it names the two
    | rather than the group — this is what removes the links already granted,
    | since a restore only ever adds. «بيت ضيافة» is deliberately NOT here: a
    | بيت ضيافة للسيدات is a real listing, and it re-declares the three in
    | `hospitality_child_vocabularies.php` on purpose.
    */
    'ملاءمة المكان' => [
        541 => [137, 264], // فندق عائم / بوت نيلي
    ],
];
