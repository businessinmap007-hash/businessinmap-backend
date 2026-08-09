<?php

/*
|--------------------------------------------------------------------------
| What a trade actually deals in — the «ماركات السيارات» pattern, four more times
|--------------------------------------------------------------------------
| Owner, 2026-08-09:
|
|   «قم باضافة مركز حجامة فى المصحة واضف الخيارات الخاصة به فى مجموعة خاصة به
|    وهى خيارات قابلة للتسعير. قائمة خيارات بالمنتجات الغذائية مثل عصائر حبوب
|    زيوت وايضا قائمة بالاجهزة الكهربائية والرياضية. بنفس نمط ماركات السيارات
|    والصحة.»
|
| «ماركات السيارات» is the template: ONE group, many options, multi-select,
| attached to every child of the trade whatever root it stands under — and each
| business narrows it to what it really carries. Three of the groups below are
| that exactly, and they are `modifier`: they say what a shop STOCKS, they do not
| price it. The priced rows are the products themselves, in the catalog.
|
| A fifth group was added on 2026-08-10 for «باب وشباك»; see its own comment
| below for why it is `line` and not a modifier like the three above.
|
| The fourth is different and the owner said so: «خيارات قابلة للتسعير». A
| cupping centre does not stock things, it PERFORMS them — a wet cupping session
| has a price the way a hotel room does. So «خدمات الحجامة» is `line`, like
| «خدمات الكوافير والتجميل» beside it.
|
| `options.name_en` is UNIQUE across the whole table, so every English name here
| is qualified enough to survive beside the ones already in use. The seeder warns
| and skips rather than crashing if one still clashes.
*/

return [

    /*
    | A new child, because the owner asked for a place that does not exist yet.
    | Its services are COPIED from «عيادة» under the same root: a cupping centre
    | is booked by appointment exactly as a clinic is, and copying beats
    | inventing a booking config.
    */
    'new_children' => [
        [
            'name_ar' => 'مركز حجامة',
            'name_en' => 'Cupping Center',
            'root_slug' => 'health',
            'copy_services_from' => 'عيادة',
        ],
    ],

    'groups' => [

        /*
        | The cupping centre's own priced menu. `line`: each is a session a
        | customer books and pays for, so it becomes a row on the price screen.
        */
        [
            'name_ar' => 'خدمات الحجامة',
            'name_en' => 'Cupping Services',
            'price_role' => 'line',
            'children' => ['مركز حجامة'],
            'options' => [
                'حجامة رطبة' => 'Wet Cupping',
                'حجامة جافة' => 'Dry Cupping',
                'حجامة متحركة' => 'Massage Cupping',
                'حجامة رياضية' => 'Sports Cupping',
                'حجامة تجميلية' => 'Cosmetic Cupping',
                'حجامة الوجه' => 'Facial Cupping',
                'العلاج بالإبر الصينية' => 'Acupuncture Session',
                'العلاج بالعسل' => 'Apitherapy Session',
                'العلاج بالأعشاب' => 'Herbal Therapy Session',
                'تدليك علاجي' => 'Therapeutic Massage Session',
                'جلسة استشارة' => 'Cupping Consultation',
            ],
        ],

        /*
        | What a food business trades in. Attached to the children that carry a
        | RANGE — a supermarket, a food factory, a grain merchant — not to the
        | single-product trades beside them: a «عصائر» shop ticking «عصائر
        | ومشروبات» and nothing else says less than its own name already does.
        */
        [
            'name_ar' => 'أصناف المنتجات الغذائية',
            'name_en' => 'Food Product Ranges',
            'price_role' => 'modifier',
            'children' => [
                'مواد غذائية',
                'مواد غذائية ومنظفات',
                'حبوب وغلال',
                'سوبر ماركت',
                'مني ماركت',
                'هايبر ماركت',
                'مجمدات',
                'استيراد وتصدير',
            ],
            'options' => [
                'عصائر ومشروبات' => 'Juices & Beverages',
                'حبوب وبقوليات' => 'Grains & Pulses',
                'أرز' => 'Rice',
                // «Pasta» is taken by menu band #925 «مكرونة / باستا», which is
                // a dish a restaurant cooks, not a packet a grocer stocks.
                'مكرونة' => 'Packaged Pasta',
                'زيوت وسمن' => 'Cooking Oils & Ghee',
                'سكر ومحليات' => 'Sugar & Sweeteners',
                'دقيق' => 'Flour',
                'بهارات وتوابل' => 'Spices & Seasonings',
                'معلبات' => 'Canned Goods',
                'ألبان وأجبان' => 'Dairy & Cheese',
                'لحوم ودواجن مجمدة' => 'Frozen Meat & Poultry',
                'أسماك ومأكولات بحرية' => 'Fish & Seafood Products',
                'شاي وقهوة' => 'Tea & Coffee',
                'حلويات وشوكولاتة معبأة' => 'Packaged Confectionery',
                'عسل ومربى' => 'Honey & Jam',
                'صلصات وشوربات' => 'Sauces & Soups',
                'أغذية أطفال' => 'Baby Food',
                'مخبوزات معبأة' => 'Packaged Bakery',
                'مكسرات وتسالي' => 'Nuts & Snacks',
                'خل ومخللات' => 'Vinegar & Pickles',
            ],
        ],

        /*
        | Electrical appliances. The repair workshops are here on purpose: a
        | «تصليح أجهزة كهربائية» that can name WHICH appliances it repairs is the
        | difference between being findable and being a phone number.
        */
        [
            'name_ar' => 'أنواع الأجهزة الكهربائية',
            'name_en' => 'Home Appliance Types',
            'price_role' => 'modifier',
            'children' => [
                'أجهزة كهربائية',
                'أدوات كهربائية',
                'تصليح أجهزة كهربائية',
                'تصليح غسالات وبتوجازات',
                'صيانة اجهزة منزلية',
                'قطع غيار أجهزة كهربائية',
            ],
            'options' => [
                'ثلاجات' => 'Refrigerators',
                'ديب فريزر' => 'Deep Freezers',
                'غسالات ملابس' => 'Washing Machines',
                'غسالات أطباق' => 'Dishwashers',
                'بوتاجازات' => 'Cookers',
                'أفران وميكروويف' => 'Ovens & Microwaves',
                'مكيفات' => 'Air Conditioners',
                'سخانات مياه' => 'Water Heaters',
                'مراوح' => 'Fans',
                'شفاطات' => 'Extractor Hoods',
                'تلفزيونات وشاشات' => 'Televisions & Screens',
                'مكانس كهربائية' => 'Vacuum Cleaners',
                'خلاطات ومضارب' => 'Blenders & Mixers',
                'غلايات وكاتيل' => 'Kettles',
                'مكاوي' => 'Irons',
                'أجهزة مطبخ صغيرة' => 'Small Kitchen Appliances',
                'صوتيات وسماعات' => 'Audio & Speakers',
                'أجهزة عناية شخصية' => 'Personal Care Appliances',
            ],
        ],

        /*
        | Owner, 2026-08-10:
        |
        |   «اريد اضافة فى باب وشباك الابواب المصفحة والشاتر واذا كان هناك انواع
        |    اخرى تضاف فى مجموعة خيارات جديدة تتماشى مع upvc وابواب وشباك سواء
        |    مصانع او شركات او محلات او ورش.»
        |
        | `line`, NOT modifier — and the difference is the whole reason the group
        | exists. The nearest thing on the platform is «أثاث وتشطيب منزلي», which
        | is `line` and is carried by exactly this family (نجار موبيليا، مطابخ
        | ودريسنج، آثاث، مفروشات): a joinery workshop prices «غرفة نوم» there, so
        | a doors workshop prices «شاتر كهربائي» here. There is no catalog of door
        | models to price against — the type IS what the customer pays for, by the
        | metre or by the leaf. Compare «أنواع الأجهزة الكهربائية» beside it,
        | which is a modifier precisely because the priced row there is a named
        | fridge in the catalog.
        |
        | Two of these already stand as CHILDREN — «بي في سي» #289 under مصانع and
        | «أبواب مصفحة» #23 under شركات — which is why both are listed here: until
        | the owner rules on whether they fold into «باب وشباك», they must be able
        | to say the same words as the trade they are part of.
        */
        [
            'name_ar' => 'أنواع الأبواب والشبابيك',
            'name_en' => 'Door & Window Types',
            'price_role' => 'line',
            'children' => [
                'باب وشباك',
                'نجار باب وشباك',
                'بي في سي',
                'أبواب مصفحة',
            ],
            'options' => [
                'بي في سي (UPVC)' => 'UPVC Doors & Windows',
                'ألومنيوم' => 'Aluminium Doors & Windows',
                'أبواب خشب' => 'Wooden Doors',
                'أبواب مصفحة' => 'Armored Doors',
                'أبواب حديد ومشغولات' => 'Iron Doors & Ironwork',
                'بوابات ومداخل' => 'Gates & Entrances',
                'شاتر يدوي' => 'Manual Shutters',
                'شاتر كهربائي' => 'Electric Shutters',
                'أبواب أوتوماتيك' => 'Automatic Doors',
                'أبواب ونوافذ سحب' => 'Sliding Doors & Windows',
                'واجهات زجاجية (سيكوريت)' => 'Glass Facades',
                'أبواب حريق' => 'Fire Doors',
                'شيش وحصيرة' => 'Rolling Blinds',
                'سواتر ومظلات' => 'Sunshades & Awnings',
                'شبك حماية وناموسية' => 'Insect & Security Screens',
                'كوالين ومقابض وإكسسوارات' => 'Locks, Handles & Fittings',
            ],
        ],

        /*
        | Sports equipment — the axis deliberately left open on 2026-08-09,
        | because «الأنشطة الرياضية» is `line` and belongs to the FACILITIES that
        | sell a session. This one says what an equipment seller stocks.
        */
        [
            'name_ar' => 'أنواع الأجهزة الرياضية',
            'name_en' => 'Sports Equipment Types',
            'price_role' => 'modifier',
            'children' => ['أجهزة رياضية'],
            'options' => [
                'مشايات كهربائية' => 'Treadmills',
                'دراجات ثابتة' => 'Exercise Bikes',
                'أوربتراك' => 'Elliptical Trainers',
                'أجهزة تجديف' => 'Rowing Machines',
                'أثقال ودمبل' => 'Dumbbells & Weights',
                'بار وأوزان' => 'Barbells & Plates',
                'بنش وأجهزة متعددة' => 'Benches & Multi-Gyms',
                'أجهزة كابل وكروس' => 'Cable & Cross Machines',
                'حبال وأحزمة مقاومة' => 'Resistance Bands',
                'حصائر يوجا' => 'Yoga Mats',
                'أكياس ملاكمة' => 'Punching Bags',
                'طاولات تنس' => 'Table Tennis Tables',
                'أجهزة كارديو منزلية' => 'Home Cardio Machines',
                'إكسسوارات لياقة' => 'Fitness Accessories',
                'ملابس وأحذية رياضية' => 'Sportswear & Trainers',
            ],
        ],
    ],
];
