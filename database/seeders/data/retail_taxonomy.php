<?php

/*
|--------------------------------------------------------------------------
| Retail taxonomy — single source of truth for the 1:1 mirror contract
|--------------------------------------------------------------------------
| Branch key  == platform_service_item_groups.key  == product_categories.slug
| Type key    == platform_service_item_types.key   == product_category_children.slug
|
| Consumed by BOTH RetailBranchesSeeder (service branches + item types) and
| RetailProductTaxonomySeeder (catalog product_categories + children), so the
| two taxonomies cannot drift. See docs/retail-branches-taxonomy.md.
|
| Adding a branch later (e.g. grocery for the food shops) = append an entry
| here + re-run db:seed; both sides pick it up. Never rename a key in place —
| catalog products and configs reference it.
*/

return [
    'fashion_textiles' => [
        'name_ar' => 'ملابس وأقمشة',
        'name_en' => 'Fashion & Textiles',
        'types' => [
            'ready_made_clothes' => ['ملابس جاهزة', 'Ready-made Clothes'],
            'fabrics' => ['أقمشة', 'Fabrics'],
            'leather_bags_shoes' => ['جلود وشنط وأحذية', 'Leather, Bags & Shoes'],
            'wool_yarn' => ['أصواف وخيوط', 'Wool & Yarn'],
            'eyewear' => ['نظارات', 'Eyewear'],
            'bridal_supplies' => ['مستلزمات أفراح وعرايس', 'Bridal & Wedding Supplies'],
        ],
    ],

    'home_furnishings' => [
        'name_ar' => 'أثاث ومفروشات',
        'name_en' => 'Home & Furnishings',
        'types' => [
            'furniture' => ['أثاث وموبيليا', 'Furniture'],
            'home_textiles' => ['مفروشات', 'Home Textiles'],
            'carpets_rugs' => ['سجاد', 'Carpets & Rugs'],
            'mattresses' => ['مراتب', 'Mattresses'],
            'foam_products' => ['إسفنج', 'Foam Products'],
            'curtains_supplies' => ['ستائر ولوازمها', 'Curtains & Supplies'],
            'chandeliers_lighting' => ['نجف وإضاءة', 'Chandeliers & Lighting'],
            'antiques_artifacts' => ['أنتيكات وتحف', 'Antiques & Artifacts'],
            'glassware' => ['زجاج', 'Glassware'],
            'china_housewares' => ['صيني وخزف ومستلزمات بيت', 'China & Housewares'],
            'aluminum_cookware' => ['ألمونتال وأدوات مطبخ', 'Aluminum & Cookware'],
            'wood_decor' => ['مصنوعات خشبية وديكور', 'Woodwork & Decor'],
        ],
    ],

    'electronics_tech' => [
        'name_ar' => 'إلكترونيات وأجهزة',
        'name_en' => 'Electronics & Tech',
        'types' => [
            'home_appliances' => ['أجهزة كهربائية', 'Home Appliances'],
            'appliance_spare_parts' => ['قطع غيار أجهزة', 'Appliance Spare Parts'],
            'computers_laptops' => ['كمبيوتر ولابتوب', 'Computers & Laptops'],
            'mobiles_accessories' => ['موبايلات وإكسسوارات', 'Mobiles & Accessories'],
            'gaming_consoles' => ['أجهزة ألعاب وبلايستيشن', 'Gaming Consoles'],
            'sports_equipment' => ['أجهزة رياضية', 'Sports Equipment'],
        ],
    ],

    'vehicles_parts' => [
        'name_ar' => 'مركبات وقطع غيار',
        'name_en' => 'Vehicles & Parts',
        'types' => [
            'cars_showroom' => ['سيارات', 'Cars'],
            'motorcycles' => ['موتوسيكلات', 'Motorcycles'],
            'auto_spare_parts' => ['قطع غيار سيارات', 'Auto Spare Parts'],
            'auto_accessories' => ['إكسسوارات سيارات', 'Auto Accessories'],
            'tires_rims' => ['جنوط وكاوتش', 'Tires & Rims'],
            'auto_oils_fluids' => ['زيوت سيارات', 'Auto Oils & Fluids'],
        ],
    ],

    'building_hardware' => [
        'name_ar' => 'مواد بناء وعدد',
        'name_en' => 'Building Materials & Hardware',
        'types' => [
            'paints_hardware' => ['حدايد وبويات', 'Paints & Hardware'],
            'rebar_steel' => ['حديد تسليح', 'Rebar & Steel'],
            'cement_building' => ['أسمنت ومواد بناء', 'Cement & Building Materials'],
            'marble_stone' => ['رخام وجرانيت', 'Marble & Granite'],
            'safety_fire' => ['سيفتي ومقاومة حرائق', 'Safety & Fire Protection'],
            'power_hand_tools' => ['عدد وأدوات كهربائية', 'Power & Hand Tools'],
            'hoses_fittings' => ['خراطيم ووصلات', 'Hoses & Fittings'],
            'keys_locks' => ['مفاتيح وأقفال', 'Keys & Locks'],
            'carpentry_supplies' => ['مستلزمات نجارة', 'Carpentry Supplies'],
            'plastic_packaging' => ['بلاستيك وأكياس', 'Plastic & Packaging'],
        ],
    ],

    'beauty_health_retail' => [
        'name_ar' => 'تجميل وصحة',
        'name_en' => 'Beauty & Health',
        'types' => [
            'beauty_cosmetics' => ['أدوات تجميل', 'Cosmetics'],
            'perfumes' => ['عطور', 'Perfumes'],
            'medical_retail' => ['مستلزمات طبية', 'Medical Supplies'],
            // Added 2026-08-10 with «مكملات غذائية» #274, which had no branch
            // entry at all and so was handed the ROOT'S MAJORITY branch —
            // أثاث ومفروشات — by ChildRootMovesSeeder::adoptRootShape(). A
            // supplements shop was being offered chandeliers, antiques, carpets
            // and mattresses, and the move's own stated reason had said «بجوار
            // عطور وأدوات تجميل» all along.
            //
            // The type comes with the branch entry, not after it: this file's
            // own rule is that a child may only be given a branch that HAS a
            // matching item type, and «مستلزمات طبية» is not what a supplements
            // shop sells.
            'supplements' => ['مكملات غذائية', 'Dietary Supplements'],
        ],
    ],

    'jewelry' => [
        'name_ar' => 'مجوهرات',
        'name_en' => 'Jewelry',
        'types' => [
            'gold_jewelry' => ['ذهب', 'Gold'],
            'silver_jewelry' => ['فضة', 'Silver'],
        ],
    ],

    'hobbies_general' => [
        'name_ar' => 'هوايات ومتنوعات',
        'name_en' => 'Hobbies & General',
        'types' => [
            'kids_toys' => ['لعب أطفال', 'Kids\' Toys'],
            'books' => ['كتب', 'Books'],
            'stationery' => ['أدوات مكتبية', 'Stationery'],
            'fishing_hunting' => ['أدوات صيد', 'Fishing & Hunting Gear'],
            'plants_garden' => ['نباتات زينة', 'Plants & Garden'],
            'horeca_supplies' => ['مستلزمات مطاعم وكافيهات', 'Restaurant & Cafe Supplies'],
            'tobacco_products' => ['مشتقات تدخين', 'Tobacco Products'],
            'household_cleaners' => ['منظفات منزلية', 'Household Cleaners'],
        ],
    ],

    // Deliberately keyed 'grocery_retail', NOT 'supermarket' — the key
    // 'supermarket' is already a LIVE platform_service_item_groups.key under
    // the Menu service (16 businesses use it there). PlatformServiceItemGroup
    // is matched by key alone, so reusing 'supermarket' here would have
    // reassigned that live Menu branch to Retail. This branch is the shared
    // master-catalog + price/stock listing path (business_catalog_listings),
    // separate from Menu's free-form items — a business can opt into both.
    'grocery_retail' => [
        'name_ar' => 'بقالة وسوبر ماركت',
        'name_en' => 'Grocery & Supermarket',
        'types' => [
            'dairy_milk' => ['ألبان', 'Milk'],
            'juice' => ['عصائر', 'Juices'],
            'yoghurt' => ['زبادي وروب', 'Yoghurt'],
            'cheese' => ['أجبان', 'Cheese'],
            'water' => ['مياه', 'Water'],
            'soft_drinks' => ['مشروبات غازية', 'Soft Drinks'],
            'tea_coffee' => ['شاي وقهوة', 'Tea & Coffee'],
            'oils_ghee' => ['زيوت وسمن', 'Oils & Ghee'],
            'rice_pasta' => ['أرز ومكرونة', 'Rice & Pasta'],
            'canned' => ['معلبات', 'Canned Food'],
            'sauces' => ['صلصات وكاتشب', 'Sauces & Ketchup'],
            'detergents' => ['منظفات', 'Detergents'],
            'personal_care' => ['عناية شخصية', 'Personal Care'],
            'biscuits_snacks' => ['بسكويت وسناكس', 'Biscuits & Snacks'],
            'chocolate' => ['شوكولاتة', 'Chocolate'],
            'paper_products' => ['منتجات ورقية', 'Paper Products'],
            'legumes_grains' => ['بقوليات وحبوب', 'Legumes & Grains'],
            'spices' => ['بهارات وتوابل', 'Spices'],
            'breakfast' => ['إفطار وحبوب', 'Breakfast'],
            'frozen' => ['مجمدات', 'Frozen'],
            'cleaning_tools' => ['أدوات نظافة', 'Cleaning Supplies'],
            'baby_care' => ['عناية بالطفل', 'Baby Care'],
        ],
    ],
];
