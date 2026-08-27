<?php

/*
|--------------------------------------------------------------------------
| Retail narrowing — which of its branch's types a trade actually sells
|--------------------------------------------------------------------------
| A branch is a SHELF, not a shop. DeliveryChildBranchesSeeder::applyChild()
| expands a branch WHOLE into allowed_item_types, which is right the day a
| branch is created and wrong the moment two trades share it: أثاث ومفروشات
| carries twelve types, so a سجاد shop was offered mattresses, chandeliers and
| china, a ذهب shop was offered silver, and a كتب shop was offered fishing
| tackle. Thirty-eight children under «المحلات أو أونلاين» had no vocabulary of
| their own at all — only the generic commercial groups — and this was why:
| they HAD product types, just not theirs.
|
| Keyed by child name_ar, NOT by root. Every one of these names carries the
| same branch under every root it stands on (معارض / المحلات / شركات / مصانع),
| and a سجاد showroom sells what a سجاد shop sells — the «one trade, many
| roots, one vocabulary» rule. Adding a root to retail_child_branches.php
| therefore narrows there too, with nothing to remember.
|
| Semantics: INTERSECTION with the branch-expanded list, never a replacement.
| A type named here that the child's branch does not carry is ignored, and if
| the intersection comes out empty the seeder keeps the whole branch and warns
| — because an empty allowed_item_types does not mean «nothing», it means
| «everything» (see BoundUnboundedConfigsSeeder), and silently narrowing a
| child to zero would hand it the entire service instead.
|
| Safe to apply as of 2026-08-11: business_catalog_listings is empty, so no
| merchant loses a listing. Once it is not, a narrowing must be checked against
| it the way MerchantOptionCommitments guards option drops.
|
| A trade absent from this file keeps its whole branch, which is the old
| behaviour and the right default for a shop that genuinely spans the shelf
| («سوبر ماركت» takes all 22 grocery types on purpose).
*/

return [

    // ── تجميل وصحة ──
    // عطور and أدوات تجميل are one Egyptian retail sector and are deliberately
    // given the same pair; مستلزمات طبية (wheelchairs, dressings) is not part
    // of it and does not sell supplements either.
    'أدوات تجميل' => ['beauty_cosmetics', 'perfumes'],
    'عطور' => ['perfumes', 'beauty_cosmetics'],
    'مستلزمات طبية' => ['medical_retail'],
    'مكملات غذائية' => ['supplements'],

    // ── مواد بناء وعدد ──
    // «حدايد وبويات» is the general hardware store and legitimately spans four
    // of the ten; the rest of this branch is nine single-line trades that were
    // each being offered the other nine.
    'حدايد وبويات' => ['paints_hardware', 'power_hand_tools', 'keys_locks', 'hoses_fittings'],
    'حديد تسليح' => ['rebar_steel', 'cement_building'],
    'اسمنت' => ['cement_building'],
    'رخام' => ['marble_stone'],
    'سيفتى ومقاومة حرائق' => ['safety_fire'],
    'كبس خراطيم' => ['hoses_fittings'],
    'مفاتيح' => ['keys_locks'],
    // The fittings trade, which is what «carpentry_supplies» actually is. It
    // does NOT get «timber_boards»: selling the hinge is not selling the door.
    'مستلزمات نجارة' => ['carpentry_supplies'],
    // «اكياس بلاستيك» folded into «مواد تعبئة وتغليف» 2026-08-23; the
    // keeper already claims plastic_packaging further down this file.

    'بلاستيك' => ['plastic_packaging'],

    // ── إلكترونيات وأجهزة ──
    'أجهزة بلايستيشن' => ['gaming_consoles'],
    'أجهزه كمبيوتر' => ['computers_laptops', 'gaming_consoles'],

    // ── ملابس وأقمشة ──
    'أصواف' => ['wool_yarn', 'fabrics'],
    'نظارات' => ['eyewear'],

    // ── هوايات ومتنوعات ──
    'كتب' => ['books', 'stationery'],
    'أدوات مكتبية' => ['stationery', 'books'],
    'أدوات صيد' => ['fishing_hunting'],
    'لعب أطفال' => ['kids_toys'],
    'مشتقات التدخين' => ['tobacco_products'],
    'نباتات طبيعية وزينة' => ['plants_garden'],

    // ── أثاث ومفروشات ──
    // The widest branch and the worst offender: twelve types over eleven
    // trades. إسفنج and مراتب are one workshop's two outputs and keep each
    // other; صيني/خزف and زجاج overlap on a shelf and keep each other.
    'ألمونتال' => ['aluminum_cookware'],
    'أنتيكات وتحف' => ['antiques_artifacts'],
    'إسفنج' => ['foam_products', 'mattresses'],
    'مراتب' => ['mattresses', 'foam_products'],
    'زجاج' => ['glassware'],
    // «صيني ومستلزمات بيت» #145 folded in here on 2026-08-16 («دمج صينى
    // ومستلزمات بيت مع صيني وخزف»). It carried one shelf the keeper did not —
    // aluminium cookware — so the shelf comes across rather than the line.
    'صينى وخزف' => ['china_housewares', 'glassware', 'aluminum_cookware'],
    'سجاد' => ['carpets_rugs', 'home_textiles'],
    'ستائر و ديكور' => ['curtains_supplies', 'home_textiles', 'wood_decor'],
    'لوازم ستائر' => ['curtains_supplies'],
    /*
     * «wood_marble_alternatives» was tried here and removed: this map
     * INTERSECTS with what the child's BRANCH offers and never widens it. The
     * decor shop sits on `home_furnishings` and the substitutes live under
     * `building_hardware`, so the row did nothing. Widening the branch would
     * have handed it rebar and cement to reach one shelf.
     */
    'مصنوعات خشبية ومستلزمات ديكور' => ['wood_decor', 'furniture', 'antiques_artifacts'],

    // ── مجوهرات ──
    // Two children, two types, and each was being offered the other's metal.
    'ذهب' => ['gold_jewelry'],
    'فضة' => ['silver_jewelry'],

    // ──────────────────────────────────────────────────────────────────────
    // The thirteen above the waterline
    // ──────────────────────────────────────────────────────────────────────
    // Everything to this point was a child with NO vocabulary of its own —
    // the visible symptom. These thirteen had one, so they never showed up in
    // that count, and were being handed the whole shelf just the same: a
    // منظفات shop offered books and fishing tackle, a زيت سيارات shop offered
    // whole cars. Having something to say about yourself is not the same as
    // being asked the right question.
    'منظفات' => ['household_cleaners'],
    'مفروشات' => ['home_textiles', 'carpets_rugs', 'curtains_supplies', 'mattresses'],
    'نجف و تحف' => ['chandeliers_lighting', 'antiques_artifacts'],
    'أدوات كهربائية' => ['power_hand_tools', 'paints_hardware'],
    'أجهزة رياضية' => ['sports_equipment'],
    'أجهزة كهربائية' => ['home_appliances', 'appliance_spare_parts'],
    'قطع غيار أجهزة كهربائية' => ['appliance_spare_parts'],
    'أقمشة' => ['fabrics', 'wool_yarn'],
    'جنوط وكاوتش سيارات' => ['tires_rims'],
    'زيت سيارات' => ['auto_oils_fluids'],
    'قطع غيار سيارات' => ['auto_spare_parts', 'auto_accessories'],
    'موبيلات و اكسسوار' => ['mobiles_accessories'],
    // The one trade that stands on two different branches: under المحلات it is
    // the electronics counter (phone cases, car mats, watches — see the note
    // in retail_child_branches.php), under ملابس و اكسسوارات it is the rail.
    // Both readings are named here, and the intersection picks the right one
    // per root on its own.
    'اكسسوار' => ['mobiles_accessories', 'auto_accessories', 'leather_bags_shoes'],
    'قطع غيار' => ['auto_spare_parts', 'auto_accessories'],
    // The donors, narrowed with the trades they seeded. ServiceReinstatementTest
    // holds a reinstated child to «lists the same catalog as the shop beside
    // it», so narrowing «سيارات» while its donor «معرض سيارات» kept all six
    // vehicle types broke the pair — and the donor was the one that was wrong.
    // A car showroom does not sell motorcycles; the motorcycle showroom next
    // door is a separate child.
    'معرض سيارات' => ['cars_showroom'],
    'معرض موتوسيكلات' => ['motorcycles'],

    // ──────────────────────────────────────────────────────────────────────
    // The trades that never stand under «المحلات أو أونلاين»
    // ──────────────────────────────────────────────────────────────────────
    // This map is keyed by trade, so every name المحلات shares with معارض،
    // شركات، مصانع or ملابس was narrowed under all of them at once. The names
    // that live ONLY on those other roots were reached by nothing, and kept
    // the whole shelf: a ملابس shop with 44 businesses offered eyewear and
    // bridal supplies, a طوب yard offered marble and locks, a تبريد وتكييف
    // company offered playstations.
    'ملابس' => ['ready_made_clothes'],
    'ملابس جاهزة' => ['ready_made_clothes'],
    'جلود وشنط وأحذية' => ['leather_bags_shoes'],
    'تبريد وتكييف' => ['home_appliances', 'appliance_spare_parts'],
    'مواد دوائية' => ['medical_retail'],
    'مستلزمات مطاعم وكافيهات' => ['horeca_supplies'],   // #37 and #66 folded in
    /*
     * «أخشاب» sells TIMBER. It was narrowed to «مستلزمات نجارة» when that was
     * the only shelf the catalog had for anything wooden, and a timber merchant
     * could not list a plank — see retail_taxonomy.php, 2026-08-11. The
     * fittings stay: a timber yard sells the glue and the edging beside the
     * board.
     */
    'أخشاب' => ['timber_boards', 'wood_marble_alternatives', 'carpentry_supplies'],
    /*
     * «ألمونتال» #17 stands under four roots on two different branches — a
     * home_furnishings shop under المحلات، معارض، شركات and a building_hardware
     * factory under مصانع since 2026-08-12. This map is per CHILD and intersects
     * with whatever branch the root gives it, so both are named: the shop keeps
     * cookware, the factory gets profiles, and neither is offered the other's.
     */
    'ألمونتال' => ['aluminium_profiles', 'aluminum_cookware'],
    'طوب' => ['cement_building'],
    // A marble yard sells the substitute beside the stone; the customer who
    // cannot afford the slab asks for the sheet in the same visit.
    'رخام وجرانيت' => ['marble_stone', 'wood_marble_alternatives'],
    // Renamed from «أدوات صحية» on 2026-08-16 when the ceramics half was
    // written; the shelf it sells off is unchanged.
    'سيراميك وأدوات صحية' => ['hoses_fittings', 'paints_hardware'],
    'مواد تعبئة وتغليف' => ['plastic_packaging'],
    'طباعة مواد تعبئة وتغليف' => ['plastic_packaging'],
    // «آثاث» is the largest child on the platform (64 businesses) and sells
    // the soft furnishings beside the furniture; it does not sell chandeliers,
    // china or mattresses, which are four separate trades on this same branch.
    'آثاث' => ['furniture', 'home_textiles'],
    'نجف' => ['chandeliers_lighting'],

    // The three food trades that stand on مصانع/شركات as MAKERS. The owner's
    // «المخابز والحلويات مطابخ» and «عصائر مطبخ» rulings are about the shops,
    // and live on the menu axis — under المحلات all three are menu-only and
    // have no retail config at all, so nothing here touches those rulings.
    // Whether a fish or sweets FACTORY should list SKUs is still his call;
    // this only says that while it does, it lists fish and sweets.
    //
    // «أسماك» widened 2026-08-27: `fish_seafood` didn't exist when this was
    // first narrowed, so frozen/canned were the closest available proxies
    // (a fish shop plausibly also sells frozen or canned fish) — additive,
    // frozen/canned were never wrong, just incomplete.
    'حلويات' => ['chocolate', 'biscuits_snacks'],
    'أسماك' => ['frozen', 'canned', 'fish_seafood'],
    'عصائر' => ['juice', 'soft_drinks', 'water'],

    // Two more shops that were stuck with the WHOLE generic 22 for lack of a
    // real type of their own (same gap `fish_seafood` closed above) — a fruit
    // & veg trader had no business being offered cheese and detergents any
    // more than a rug shop should be offered mattresses. Added 2026-08-27
    // once `fruits`/`vegetables`/`poultry` existed to narrow to.
    'خضار وفاكهة' => ['fruits', 'vegetables'],
    'دواجن' => ['poultry'],

    // Deliberately NOT narrowed:
    //   «مواد غذائية» / «مواد غذائية ومنظفات» — the whole grocery range is
    //     exactly what a food wholesaler carries, same as the markets.
    //   «بي في سي» #289 — folded into «باب وشباك» on 2026-08-12, so it reaches
    //     no root and no narrowing here can reach it either. Kept as the record
    //     of why it was never narrowed: building_hardware has no UPVC or
    //     profile type, so any narrowing would have been a guess.
    //   «باب وشباك» — building_hardware carries no doors/windows type at all,
    //     so any narrowing here is a guess. It has its own option group
    //     («أنواع الأبواب والشبابيك») and no merchant accounts. The branch, not
    //     the map, is what is missing; left whole and flagged.
    //   «سوبر ماركت» / «مني ماركت» / «هايبر ماركت» — all 22 grocery types on
    //     purpose. A market IS the whole shelf.
];
