<?php

/**
 * «راجع باقي مجموعات الخيارات وأضف إليها ما ينقصها مثل الفواكه والخضروات»
 *  — المالك، 2026-08-25.
 *
 * ── What this file is, and what it is NOT ───────────────────────────────────
 *
 * It only ADDS ROWS to groups that already exist. It creates no group, hands
 * no group to any child, and takes nothing away. Rows and links are two
 * tables: a merchant who never carried «أنواع السجاد» still does not carry it
 * after this runs — the ones who do simply stop having to write «سجاد تركي»
 * by hand in a free-text name.
 *
 * ── The rule the list follows ───────────────────────────────────────────────
 *
 * The same one the fruit and vegetable pass followed: a thing the customer
 * chooses BETWEEN, and whose stock can run out on its own, is a row. «سجاد
 * تركي» و«سجاد بلجيكي» are two rows because the shop can be out of one and
 * full of the other. A property that qualifies a row it does not replace
 * (a size, a grade, a colour) is NOT here — that is a modifier, and modifiers
 * live in their own groups.
 *
 * ── Why some obvious names are missing ──────────────────────────────────────
 *
 * `options.name_en` is UNIQUE platform-wide, so a name already spent somewhere
 * else cannot be spent twice. Where a name was taken by a row that means the
 * same thing, the row was left where it is instead of cloned:
 *
 *   - «واقي شمس» is already in «أصناف العناية الشخصية» — not repeated under
 *     مستحضرات التجميل.
 *   - «ألعاب تعليمية» is already in «أصناف لعب الأطفال» — not repeated under
 *     أقسام المكتبة.
 *   - «مواد عزل» is already in «الحدايد والبويات» — مواد البناء got the
 *     narrower «عزل مائي» instead.
 *
 * The seeder REFUSES to write a colliding row rather than mint a suffix, so a
 * name spent since this was written shows up as a warning, not as
 * «Turkish Carpet (2)».
 */

return [

    /*
    |--------------------------------------------------------------------------
    | الأدوية والمستلزمات الطبية
    |--------------------------------------------------------------------------
    |
    | The pharmacy was the thinnest real shop on the platform: five services
    | and eight shelves for a trade that does more at the counter than most
    | clinics do in a room.
    |
    */

    'extend' => [

        'أقسام الصيدلية' => [
            // «إسعافات أولية» is not here: the row already exists inside
            // «المستلزمات الطبية». Borrowed, not cloned.
            'مستلزمات مرضى السكر' => 'Diabetes Care',
            // «Elderly Care» is spent on «رعاية مسنين» — a home SERVICE, not a
            // shelf — so the shelf carries the longer name.
            'مستلزمات كبار السن' => 'Elderly Care Supplies',
            'العناية بالفم والأسنان' => 'Oral Care',
            'منتجات الحمية والتغذية' => 'Diet & Nutrition',
            'مستلزمات الأم والطفل' => 'Mother & Baby Care',
        ],

        'خدمات الصيدلية' => [
            'قياس حرارة' => 'Temperature Check',
            'قياس الأكسجين' => 'Oxygen Saturation Check',
            'قياس الوزن والطول' => 'Weight & Height Check',
            'تطعيمات ولقاحات' => 'Vaccinations',
            'تغيير ضمادات' => 'Dressing Change',
            'تحضير وتركيب أدوية' => 'Compounding',
            'متابعة أدوية الأمراض المزمنة' => 'Chronic Medication Follow-up',
            'توصيل الأدوية للمنزل' => 'Medicine Home Delivery',
        ],

        'المستلزمات الطبية' => [
            'ضمادات وشاش' => 'Bandages & Gauze',
            'حقن وسرنجات' => 'Syringes & Needles',
            'قفازات وكمامات' => 'Gloves & Masks',
            'عكازات ومشايات' => 'Crutches & Walkers',
            'مراتب ووسائد طبية' => 'Medical Mattresses',
            'أجهزة علاج طبيعي' => 'Physiotherapy Devices',
            'محاليل وريدية' => 'IV Fluids',
            'مستلزمات تعقيم' => 'Sterilisation Supplies',
        ],

        'أصناف المكملات' => [
            'أوميجا ٣' => 'Omega 3',
            'كولاجين' => 'Collagen',
            'جلوتامين' => 'Glutamine',
            'مكملات ما قبل التمرين' => 'Pre-Workout',
            'بدائل الوجبات' => 'Meal Replacement',
            'بروبيوتيك' => 'Probiotics',
            'مكملات الشعر والأظافر' => 'Hair & Nail Supplements',
            'مكملات الأطفال' => 'Kids Supplements',
        ],

        'المواد الدوائية' => [
            'كبسولات فارغة' => 'Empty Capsules',
            'سواغات دوائية' => 'Pharmaceutical Excipients',
            'مواد حافظة' => 'Preservatives',
            'مستخلصات نباتية' => 'Plant Extracts',
            'زيوت طبية' => 'Medicinal Oils',
        ],

        /*
        |----------------------------------------------------------------------
        | مواد البناء والتشطيب
        |----------------------------------------------------------------------
        |
        | Eight to nine rows each, for trades whose whole price list is these
        | rows. A builders' merchant sells adhesive and admixtures every day
        | and had nowhere to put either.
        |
        */

        'أنواع الطوب' => [
            'طوب مصمت' => 'Solid Brick',
            'طوب مفرغ' => 'Hollow Brick',
            'طوب هوردي' => 'Hordi Block',
            'طوب دبش' => 'Rubble Stone',
            'بلاطات خرسانية' => 'Concrete Slabs',
        ],

        'مواد البناء الأساسية' => [
            'لاصق سيراميك' => 'Tile Adhesive',
            'إضافات خرسانة' => 'Concrete Admixtures',
            'شبك تسليح' => 'Reinforcement Mesh',
            'عزل مائي' => 'Waterproofing',
            'مونة ترميم' => 'Repair Mortar',
        ],

        'الأدوات الصحية' => [
            'مراحيض إفرنجي' => 'Western Toilets',
            'مراحيض بلدي' => 'Squat Toilets',
            'شطافات' => 'Bidet Sprays',
            'وحدات حمام' => 'Vanity Units',
            'مرايا حمام' => 'Bathroom Mirrors',
            'صفايات وبلاعات' => 'Floor Drains',
            'فلاتر مياه' => 'Water Filters',
        ],

        'أنواع الزجاج' => [
            'زجاج عادي شفاف' => 'Clear Float Glass',
            'زجاج مصنفر' => 'Frosted Glass',
            'زجاج لاميناتد' => 'Laminated Glass',
            'زجاج منحني' => 'Curved Glass',
            'زجاج مطبوع ديجيتال' => 'Digital Printed Glass',
            'إكسسوار وحلوق زجاج' => 'Glass Fittings',
        ],

        'أنواع الرخام والجرانيت' => [
            'أحواض ومغاسل' => 'Stone Basins',
            'عتبات وشبابيك' => 'Sills & Thresholds',
            // «Stone Facades» is spent on «واجهات حجرية», which is the
            // BUILDER's job. This is the marble yard's product.
            'مداخل وواجهات رخام' => 'Marble Facades',
            'حجر بازلت' => 'Basalt',
            'حجر مايكا' => 'Mica Stone',
            'رخام مقابر وشواهد' => 'Grave Marble',
        ],

        'أنواع السيراميك والبورسلين' => [
            'بلاط أسمنتي' => 'Cement Tiles',
            'حجر صناعي' => 'Artificial Stone',
            'سيراميك مسابح' => 'Pool Tiles',
            'بورسلين كبير المقاس' => 'Large Format Porcelain',
        ],

        'أنواع الحديد' => [
            'حديد كمر' => 'Steel Beams',
            'حديد مجلفن' => 'Galvanised Steel',
            'شبك حديد ممدد' => 'Expanded Metal Mesh',
            'خردة حديد' => 'Scrap Iron',
        ],

        'الحدايد والبويات' => [
            'تنر ومذيبات' => 'Thinners & Solvents',
            'فرش ورولات دهان' => 'Brushes & Rollers',
            'صنفرة ومعجون' => 'Sandpaper & Filler',
            'سيليكون ولواصق' => 'Silicone & Adhesives',
            'شرائط لاصقة' => 'Adhesive Tapes',
            'أسلاك وسلاسل' => 'Wires & Chains',
        ],

        'أنواع الأخشاب' => [
            'بلوط' => 'Oak',
            'جوز' => 'Walnut',
            'ماهوجني' => 'Mahogany',
            'تيك' => 'Teak',
            // «صنوبر» is not here: «أرو» in this same group already IS pine.
        ],

        /*
        |----------------------------------------------------------------------
        | المفروشات والمنزل
        |----------------------------------------------------------------------
        |
        | «سجاد تركي» و«سجاد بلجيكي» are the two words an Egyptian carpet shop
        | actually says. The list had «سجاد مشجر» and «سجاد سادة» — a pattern,
        | not an origin — and nothing to hang the price on.
        |
        */

        'أنواع السجاد' => [
            'سجاد تركي' => 'Turkish Carpet',
            'سجاد بلجيكي' => 'Belgian Carpet',
            'سجاد عجمي' => 'Persian Carpet',
            'سجاد شاجي' => 'Shaggy Carpet',
            'سجاد يدوي' => 'Handmade Carpet',
            'ممرات (رانر)' => 'Runners',
            'سجاد مساجد' => 'Mosque Carpet',
        ],

        'أنواع المراتب' => [
            'مرتبة سوست منفصلة' => 'Pocket Spring Mattress',
            'مرتبة هوائية' => 'Air Mattress',
            'مرتبة قابلة للطي' => 'Folding Mattress',
            'مراتب فنادق' => 'Hotel Mattress',
            'مرتبة بمقاس خاص' => 'Custom Size Mattress',
        ],

        'أنواع الإسفنج' => [
            'إسفنج عزل صوت' => 'Acoustic Foam',
            'إسفنج سيارات' => 'Automotive Foam',
            'إسفنج طبي' => 'Medical Foam',
            'إسفنج رول' => 'Foam Roll',
            'قص إسفنج حسب المقاس' => 'Cut-to-Size Foam',
        ],

        'الصيني والخزف' => [
            'صواني تقديم' => 'Serving Trays',
            'أطباق تقديم' => 'Serving Platters',
            'سلطانيات' => 'Bowls',
            'مزهريات' => 'Vases',
            'أطقم توزيعات مناسبات' => 'Party Favour Sets',
        ],

        'الأنتيكات والتحف' => [
            'فوانيس ومشكاوات' => 'Lanterns',
            // «أرابيسك ومشربية» in «تخصصات ورش الأثاث» is the craft. This is
            // the piece on the shelf.
            'تحف أرابيسك ومشربية' => 'Arabesque Antiques',
            'صناديق مطعمة' => 'Inlaid Boxes',
            'عملات وطوابع قديمة' => 'Old Coins & Stamps',
            'شمعدانات' => 'Candelabra',
        ],

        /*
        |----------------------------------------------------------------------
        | الأقمشة والخيوط
        |----------------------------------------------------------------------
        |
        | «أنواع الأقمشة» became a priced line yesterday. A priced line of
        | fifteen names is short for a trade that sells by the metre — these
        | ten are the ones a draper is asked for by name.
        |
        */

        'أنواع الأقمشة' => [
            'جوخ' => 'Broadcloth',
            'تويل' => 'Twill',
            'بوبلين' => 'Poplin',
            'ليكرا' => 'Lycra',
            // «كريب» also exists in «بنود المنيو» — that one is the pancake.
            'كريب' => 'Crepe Fabric',
            'أورجانزا' => 'Organza',
            'خام قطني' => 'Calico',
            'تفتا' => 'Taffeta',
            'شانتون' => 'Shantung',
            'صوف مخلوط' => 'Wool Blend',
        ],

        'أنواع الأصواف والخيوط' => [
            'خيوط حرير' => 'Silk Thread',
            'خيوط كتان' => 'Linen Yarn',
            'خيوط تطريز' => 'Embroidery Thread',
            'خيوط خياطة' => 'Sewing Thread',
            'صوف مرينو' => 'Merino Wool',
            'خيوط مكرمية' => 'Macrame Cord',
        ],

        /*
        |----------------------------------------------------------------------
        | التجميل والعطور والنظارات والمجوهرات
        |----------------------------------------------------------------------
        */

        'أصناف مستحضرات التجميل' => [
            'عدسات تجميلية' => 'Cosmetic Lenses',
            'رموش صناعية' => 'False Lashes',
            'أظافر صناعية' => 'Artificial Nails',
            'مزيلات مكياج' => 'Makeup Removers',
            'العناية باللحية' => 'Beard Care',
        ],

        'أنواع العطور' => [
            'عطور رجالية' => "Men's Perfumes",
            'عطور نسائية' => "Women's Perfumes",
            'عطور أطفال' => 'Kids Perfumes',
            'دهن عود' => 'Oud Oil',
            'كولونيا' => 'Cologne',
            'بخاخات معطرة للجسم' => 'Body Sprays',
        ],

        'أنواع النظارات' => [
            'نظارات قراءة' => 'Reading Glasses',
            'عدسات ملونة' => 'Coloured Lenses',
            'نظارات حماية من الأشعة الزرقاء' => 'Blue Light Glasses',
            'نظارات سباحة' => 'Swimming Goggles',
            'نظارات سلامة' => 'Safety Glasses',
            'محاليل وعناية بالعدسات' => 'Lens Care Solutions',
        ],

        'أصناف المجوهرات' => [
            'فضة' => 'Silver Jewellery',
            'أحجار كريمة' => 'Gemstones',
            'خلاخيل' => 'Anklets',
            'دلايات وتعليقات' => 'Pendants',
            'تيجان وإكسسوار عرائس' => 'Bridal Accessories',
            'إكسسوار مطلي' => 'Plated Accessories',
        ],

        /*
        |----------------------------------------------------------------------
        | المكتبة والقرطاسية واللعب
        |----------------------------------------------------------------------
        */

        'أقسام المكتبة' => [
            'كتب أجنبية' => 'Foreign Books',
            'كتب تنمية بشرية' => 'Self-Development Books',
            'مصاحف وكتب تراث' => 'Qurans & Heritage Books',
            'كتب طبخ' => 'Cookbooks',
            'هدايا وتغليف' => 'Gifts & Wrapping',
            'تصوير مستندات وطباعة' => 'Photocopy & Printing',
            'أدوات هندسية' => 'Drawing Instruments',
        ],

        'الأدوات المكتبية' => [
            'دباسات وخرامات' => 'Staplers & Punches',
            'آلات حاسبة' => 'Calculators',
            'سبورات ولوحات' => 'Boards',
            'أختام وأحبار ختم' => 'Stamps & Ink Pads',
            'حقائب ومقلمات' => 'Bags & Pencil Cases',
            'ورق تغليف' => 'Wrapping Paper',
        ],

        'أصناف لعب الأطفال' => [
            'دراجات وسكوتر أطفال' => 'Kids Bikes & Scooters',
            'مجسمات وأكشن فيجر' => 'Action Figures',
            'ألعاب لوحية وورقية' => 'Board Games',
            'ألعاب رمل وبحر' => 'Beach & Sand Toys',
            'مكعبات وفك وتركيب' => 'Building Blocks',
        ],

        'أجهزة الألعاب' => [
            'أجهزة نينتندو' => 'Nintendo Consoles',
            'كراسي ومكاتب جيمنج' => 'Gaming Chairs & Desks',
            'أجهزة مستعملة' => 'Used Consoles',
        ],

        /*
        |----------------------------------------------------------------------
        | ما بقي ناقصًا في رفوف الطعام
        |----------------------------------------------------------------------
        |
        | Four lists written yesterday came out short. The sweeteners had six
        | rows, baby food six, and the dairy counter had no لبنة — which is the
        | one thing a cheese shop is asked for before it is asked for anything
        | else.
        |
        */

        'أنواع السكر والمحليات' => [
            'سكر خشن' => 'Coarse Sugar',
            'سكر قصب خام' => 'Raw Cane Sugar',
            'ستيفيا' => 'Stevia',
            'فركتوز' => 'Fructose',
            'شراب الذرة' => 'Corn Syrup',
        ],

        'أنواع أغذية الأطفال' => [
            'حليب متابعة' => 'Follow-on Formula',
            'وجبات جاهزة للأطفال' => 'Baby Ready Meals',
            'زبادي أطفال' => 'Baby Yoghurt',
            'سناكس أطفال' => 'Baby Snacks',
        ],

        'أنواع الشاي والقهوة' => [
            'شاي كرك' => 'Karak Tea',
            'شاي أسود سيلاني' => 'Ceylon Black Tea',
            'قهوة تركي' => 'Turkish Coffee',
            'قهوة عربي' => 'Arabic Coffee',
            'قهوة خضراء' => 'Green Coffee',
            'شوكولاتة ساخنة' => 'Hot Chocolate',
        ],

        // «أنواع الألبان والأجبان» is NOT extended here. It is declared in
        // data/shop_child_vocabularies.php, which is its authority, and the six
        // cheeses this pass adds went in there. A group with an owner is
        // extended in its owner's file — otherwise two files describe one list
        // and the test that reads the file calls the database wrong.

        /*
        |----------------------------------------------------------------------
        | معدات المحلات والمقاهي
        |----------------------------------------------------------------------
        */

        'مستلزمات المقاهي' => [
            'أكواب تيك أواي' => 'Takeaway Cups',
            'بلندر وعصارات' => 'Blenders & Juicers',
            'ثلاجات مشروبات' => 'Beverage Coolers',
        ],

        'معدات السوبر ماركت' => [
            'ماكينات نقاط بيع' => 'POS Machines',
            'شرائح أسعار ولافتات' => 'Price Rails & Signage',
            // «أرفف تخزين مخازن» was written and taken back out: «أرفف
            // وجندولات» in this same group is already the shelving.
            'ماكينات تغليف وشرنك' => 'Shrink Wrap Machines',
        ],

        /*
        |----------------------------------------------------------------------
        | المركبات
        |----------------------------------------------------------------------
        |
        | «نوع المركبة» carried seven car children on three rows — سيدان، SUV
        | وبيك أب. A hatchback is the most common car on an Egyptian street and
        | there was no way to say it.
        |
        | «ميكروباص» و«نص نقل» exist once already inside «مركبات النقل والركاب»
        | as «ميكروباص 15» و«ربع نقل» — those are load sizes on a haulier's
        | list, not the body of a car, so these are their own rows here.
        |
        */

        'نوع المركبة' => [
            'هاتشباك' => 'Hatchback',
            'كوبيه' => 'Coupe',
            'ستيشن' => 'Station Wagon',
            'كروس أوفر' => 'Crossover',
            'مكشوفة' => 'Convertible',
            'ميني ڤان' => 'Minivan',
            'دفع رباعي' => 'Four Wheel Drive',
            'ميكروباص' => 'Microbus',
            'نص نقل' => 'Light Truck',
        ],
    ],
];
