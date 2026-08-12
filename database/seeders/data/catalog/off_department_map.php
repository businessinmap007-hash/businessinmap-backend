<?php

/**
 * Open Food Facts category tags → the 22 departments of the `grocery_retail`
 * catalog branch.
 *
 * Built from what the Egypt export actually contains, not from OFF's full
 * taxonomy: 269 distinct leaf tags appeared, most of them once. A row is placed
 * by scanning its tags from the MOST specific (last) to the most general
 * (first) and taking the first that appears here — which is why `en:sodas` and
 * `en:snacks` can both be listed without the general one swallowing the
 * specific.
 *
 * A tag that is not here places nothing. That is deliberate: 66% of the Egypt
 * rows carry no category at all, and guessing a department from a product name
 * is how a shampoo ends up on the dairy shelf.
 *
 * @return array<string,string>  tag → product_category_children.slug
 */
return [
    // ── water ────────────────────────────────────────────────────────────
    'en:waters' => 'water',
    'en:spring-waters' => 'water',
    'en:mineral-waters' => 'water',
    'en:natural-mineral-waters' => 'water',
    'en:sparkling-waters' => 'water',

    // ── soft drinks ──────────────────────────────────────────────────────
    'en:sodas' => 'soft_drinks',
    'en:colas' => 'soft_drinks',
    'en:diet-cola-soft-drink' => 'soft_drinks',
    'en:carbonated-drinks' => 'soft_drinks',
    'en:soft-drinks' => 'soft_drinks',
    'en:energy-drinks' => 'soft_drinks',
    'en:sweetened-beverages' => 'soft_drinks',
    'en:non-alcoholic-beverages' => 'soft_drinks',

    // ── juice ────────────────────────────────────────────────────────────
    'en:juices' => 'juice',
    'en:fruit-juices' => 'juice',
    'en:orange-juices' => 'juice',
    'en:apple-juices' => 'juice',
    'en:mango-juices' => 'juice',
    'en:nectars' => 'juice',
    'en:fruit-nectars' => 'juice',
    'en:juices-and-nectars' => 'juice',
    'en:fruit-based-beverages' => 'juice',

    // ── tea & coffee ─────────────────────────────────────────────────────
    'en:coffees' => 'tea_coffee',
    'en:instant-coffees' => 'tea_coffee',
    'en:ground-coffees' => 'tea_coffee',
    'en:teas' => 'tea_coffee',
    'en:black-teas' => 'tea_coffee',
    'en:green-teas' => 'tea_coffee',
    'en:herbal-teas' => 'tea_coffee',
    'en:hot-beverages' => 'tea_coffee',
    'en:instant-beverages' => 'tea_coffee',

    // ── yoghurt ──────────────────────────────────────────────────────────
    'en:yogurts' => 'yoghurt',
    'en:greek-yogurts' => 'yoghurt',
    'en:drinkable-yogurts' => 'yoghurt',
    'en:fermented-milk-products' => 'yoghurt',
    'en:fermented-dairy-desserts' => 'yoghurt',

    // ── cheese ───────────────────────────────────────────────────────────
    'en:cheeses' => 'cheese',
    'en:processed-cheeses' => 'cheese',
    'en:spreadable-cheeses' => 'cheese',
    'en:fresh-cheeses' => 'cheese',

    // ── milk & cream ─────────────────────────────────────────────────────
    'en:milks' => 'dairy_milk',
    'en:cow-milks' => 'dairy_milk',
    'en:whole-milks' => 'dairy_milk',
    'en:semi-skimmed-milks' => 'dairy_milk',
    'en:skimmed-milks' => 'dairy_milk',
    'en:chocolate-milks' => 'dairy_milk',
    'en:flavoured-milks' => 'dairy_milk',
    'en:milk-powders' => 'dairy_milk',
    'en:creams' => 'dairy_milk',
    'en:dairies' => 'dairy_milk',

    // ── chocolate & confectionery ────────────────────────────────────────
    'en:chocolates' => 'chocolate',
    'en:milk-chocolates' => 'chocolate',
    'en:dark-chocolates' => 'chocolate',
    'en:white-chocolates' => 'chocolate',
    'en:chocolate-candies' => 'chocolate',
    'en:candy-chocolate-bars' => 'chocolate',
    'en:cocoa-and-its-products' => 'chocolate',
    'en:confectioneries' => 'chocolate',
    'en:candies' => 'chocolate',
    'en:chewing-gum' => 'chocolate',
    'en:sugar-free-chewing-gum' => 'chocolate',

    // ── biscuits & snacks ────────────────────────────────────────────────
    'en:biscuits' => 'biscuits_snacks',
    'en:filled-biscuits' => 'biscuits_snacks',
    'en:biscuits-and-crackers' => 'biscuits_snacks',
    'en:biscuits-and-cakes' => 'biscuits_snacks',
    'en:crackers' => 'biscuits_snacks',
    'en:crackers-appetizers' => 'biscuits_snacks',
    'en:wafers' => 'biscuits_snacks',
    'en:cakes' => 'biscuits_snacks',
    'en:crisps' => 'biscuits_snacks',
    'en:potato-crisps' => 'biscuits_snacks',
    'en:corn-chips' => 'biscuits_snacks',
    'en:chips-and-fries' => 'biscuits_snacks',
    'en:fried-potato-chips' => 'biscuits_snacks',
    'en:salty-snacks' => 'biscuits_snacks',
    'en:puffed-rice-cakes' => 'biscuits_snacks',
    'en:protein-bars' => 'biscuits_snacks',
    'en:bars' => 'biscuits_snacks',
    'en:appetizers' => 'biscuits_snacks',
    'en:sweet-snacks' => 'biscuits_snacks',
    'en:snacks' => 'biscuits_snacks',

    // ── breakfast & spreads ──────────────────────────────────────────────
    'en:breakfast-cereals' => 'breakfast',
    'en:rolled-oats' => 'breakfast',
    'en:oat' => 'breakfast',
    'en:oats' => 'breakfast',
    'en:cereals-and-their-products' => 'breakfast',
    'en:honeys' => 'breakfast',
    'en:jams' => 'breakfast',
    'en:fruit-preserves' => 'breakfast',
    'en:nut-butters' => 'breakfast',
    'en:peanut-butters' => 'breakfast',
    'en:chocolate-spreads' => 'breakfast',
    'en:sesame-halva' => 'breakfast',
    'en:halva' => 'breakfast',
    'en:tahini' => 'breakfast',
    'en:spreads' => 'breakfast',

    // ── rice & pasta ─────────────────────────────────────────────────────
    'en:pastas' => 'rice_pasta',
    'en:durum-wheat-pasta' => 'rice_pasta',
    'en:noodles' => 'rice_pasta',
    'en:instant-noodles' => 'rice_pasta',
    'en:rices' => 'rice_pasta',
    'en:white-rices' => 'rice_pasta',

    // ── canned ───────────────────────────────────────────────────────────
    'en:canned-foods' => 'canned',
    'en:canned-fishes' => 'canned',
    'en:canned-tunas' => 'canned',
    'en:canned-sardines' => 'canned',
    'en:sardines-in-oil' => 'canned',
    'en:canned-vegetables' => 'canned',
    'en:canned-legumes' => 'canned',

    // ── sauces & condiments ──────────────────────────────────────────────
    'en:ketchup' => 'sauces',
    'en:mayonnaises' => 'sauces',
    'en:mustards' => 'sauces',
    'en:sauces' => 'sauces',
    'en:tomato-sauces' => 'sauces',
    'en:vinegars' => 'sauces',
    'en:condiments' => 'sauces',

    // ── oils & fats ──────────────────────────────────────────────────────
    'en:olive-oils' => 'oils_ghee',
    'en:sunflower-oils' => 'oils_ghee',
    'en:vegetable-oils' => 'oils_ghee',
    'en:vegetable-fats' => 'oils_ghee',
    'en:butters' => 'oils_ghee',
    'en:margarines' => 'oils_ghee',

    // ── spices ───────────────────────────────────────────────────────────
    'en:spices' => 'spices',
    'en:herbs-and-spices' => 'spices',
    'en:salts' => 'spices',
    'en:table-salts' => 'spices',

    // ── legumes, grains & nuts ───────────────────────────────────────────
    'en:legumes' => 'legumes_grains',
    'en:pulses' => 'legumes_grains',
    'en:lentils' => 'legumes_grains',
    'en:beans' => 'legumes_grains',
    'en:chickpeas' => 'legumes_grains',
    'en:nuts' => 'legumes_grains',
    'en:dried-fruits' => 'legumes_grains',
    'en:seeds' => 'legumes_grains',

    // ── frozen ───────────────────────────────────────────────────────────
    'en:frozen-foods' => 'frozen',
    'en:ice-creams' => 'frozen',
    'en:frozen-desserts' => 'frozen',
    'en:ice-cream-and-sorbets' => 'frozen',

    // ── personal care ────────────────────────────────────────────────────
    'en:open-beauty-facts' => 'personal_care',
    'en:hair-care' => 'personal_care',
    'en:shampoos' => 'personal_care',
    'en:conditioners' => 'personal_care',
    'en:deodorants' => 'personal_care',
    'en:toothpastes' => 'personal_care',
    'en:soaps' => 'personal_care',
    'en:shower-gels' => 'personal_care',
    'en:skin-care' => 'personal_care',
    'en:body-care' => 'personal_care',
    'en:cosmetics' => 'personal_care',
    'en:hygiene' => 'personal_care',

    // ── baby ─────────────────────────────────────────────────────────────
    'en:baby-foods' => 'baby_care',
    'en:infant-formulas' => 'baby_care',
    'en:baby-milks' => 'baby_care',
    'en:diapers' => 'baby_care',

    // ── household ────────────────────────────────────────────────────────
    'en:laundry-detergents' => 'detergents',
    'en:dishwashing-products' => 'detergents',
    'en:cleaning-products' => 'detergents',
    'en:toilet-papers' => 'paper_products',
    'en:paper-towels' => 'paper_products',
    'en:tissues' => 'paper_products',
];
