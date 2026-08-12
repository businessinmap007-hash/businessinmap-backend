<?php

/**
 * Which option groups take part in pricing, and how.
 *
 * The test applied to all 39 live groups was one question: **does the customer
 * pay for this exact thing?** It has three answers, not two.
 *
 *   line        the option IS what is bought and priced
 *   modifier    it never stands alone, but it changes the price of a line
 *   descriptive it is never priced — it only describes the business
 *
 * Groups are keyed by `name_ar`, the name every one of them actually has.
 * Anything absent stays `descriptive`, which is the safe default: it simply
 * never appears in a pricing screen.
 *
 * Applied by \Database\Seeders\OptionPriceRolesSeeder.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | line — the option is the thing bought
    |--------------------------------------------------------------------------
    | Crossed with the service's item type, this is the priced row:
    | «كشف» × «عظام» = 300, «كشف» × «باطنة» = 250. Neither coordinate is a
    | price on its own, which is why merging the two vocabularies was wrong.
    */
    'line' => [
        'بنود المنيو',            // مشويات، ساندوتشات — the heading a customer pays under
        // The three that came out of it on 2026-08-10 (MenuBandSplitSeeder).
        // All still `line`: they are headings a merchant prices under, which is
        // exactly what separates «أقسام السوبر ماركت» from the `modifier`
        // «أصناف المنتجات الغذائية» beside it.
        // Taken apart again on 2026-08-10 (GroceryAisleSplitSeeder) into the
        // five counters its own link data drew — a fishmonger, a bakery, a
        // coffee merchant, a juice bar and a cleaning-supplies shop each
        // answered a different part of the 27 and ignored the rest. The parent
        // is left standing but EMPTY, so it stays named here rather than
        // deleted: a group with no options has no role to reset, and removing
        // the line would only mean a future reader wonders where it went.
        'أقسام السوبر ماركت',
        'أقسام الطازج واللحوم',   // لحوم ودواجن، أجبان، فسيخ
        'بنود المخبوزات والحلويات', // مخبوزات، فطائر — baked on the premises
        'أقسام البقالة الجافة',   // مكرونات وأرز وحبوب، معلبات
        'أقسام المشروبات',        // عصائر، مشروبات
        'أقسام المنزل والعناية',  // منظفات، أدوات منزلية — the non-food half
        'مستلزمات المزارع',       // ماشية وطيور
        'صفوف معروضة',            // مركبة معروضة — one row meaning «what is on display»
        'نوع المركبة',            // سيدان — BMW; the brand needs something to be the brand OF
        // Was «فئات الغرف» until the owner merged the hotel room kinds into the
        // existing «الغرف» (2026-08-05), which already held استوديو/غرفة/غرفتين
        // for property listings. One group now answers both: جناح ≠ غرفة فردية
        // for a hotel, ثلاث غرف ≠ أربع غرف for a flat, and both are what the
        // merchant is actually paid for. Renaming it here was NOT optional —
        // a group missing from this file is reset to `descriptive` on the next
        // OptionPriceRolesSeeder run, which would have silently stopped every
        // hotel and property line from pricing. Fourth time this has bitten.
        'الغرف',
        // The coworking counterpart of «الغرف», created 2026-08-11. A desk, a
        // private office and a course room are the things a customer reserves
        // one OF, and each `bookable_items` row points at one of them. Listed
        // the same day it was created — see the note under «مستوى التشطيب».
        'مساحات العمل',
        /*
         * The five trades under «مكاتب» that could not name what they sell,
         * 2026-08-11 — a printing house could not say it prints and a security
         * company could not say it guards. Each row is a thing a customer pays
         * for: «تنظيف خزانات», «حراسة فعاليات», «تخليص وارد».
         */
        'الخدمات المنزلية',
        'خدمات تنظيم الحفلات',
        'خدمات التخليص الجمركي',
        'خدمات الطباعة',
        'خدمات الأمن والحراسة',
        'خدمات المأذون الشرعي',
        // …and «تكنولوجيا», whose three children were all mute. «أنظمة الأمن
        // والسلامة» is the SYSTEMS half of security; «خدمات الأمن والحراسة»
        // above is the manpower half, on a different child under a different
        // root, and neither list repeats a row of the other.
        'أنظمة الأمن والسلامة',
        'خدمات الاتصالات والشبكات',
        'خدمات البرمجة والتطوير',
        // …and the SERVICE half of «شركات». The root is both, so the rule is
        // applied per child: a contractor's «أعمال خرسانية» is the priced row
        // the way «تنظيف خزانات» is, while a curtain wholesaler's stock is not.
        'أعمال المقاولات',
        // The farm cluster, 2026-08-12: no retail and no catalog behind them,
        // so the type IS what the customer pays for.
        'الآلات والمعدات الزراعية',
        'معدات وتجهيزات المزارع',
        'أنواع الثروة الحيوانية والسمكية',
        'مستلزمات المحاصيل',
        // Written 2026-08-12 for the three «شركات» children the bulk-picker
        // slip left with no vocabulary at all. Lines: the priced row is the job.
        'أنظمة المصاعد والسلالم',
        'الكرفانات والوحدات الجاهزة',
        'معدات السوبر ماركت',
        /*
         * «عدل أصناف المفروشات سطر مسعر» — owner, 2026-08-12, overruling the
         * goods rule that put it in the modifier block below.
         *
         * He is describing the shop, not the schema. A مفروشات merchant quotes
         * «طقم مفارش سرير» as a price with a size and a piece count, the way a
         * hall quotes a booking — the range IS the priced row, and a catalog
         * product behind it would be a second price for one thing.
         */
        'أصناف المفروشات',
        'أعمال البنية التحتية',
        'أنواع التأمين',
        'خدمات التسويق',
        'خدمات الصرافة والتحويل',
        'خدمات السياحة والسفر',
        /*
         * «مهن وحرفيين», the largest debt on the platform — 24 of 27 crafts and
         * 121 merchants, none of whom could say what they do. Booking-only
         * trades, so the offices rule: the JOB is the priced row. A customer
         * pays for «تسليك مجاري» the way he pays for «تنظيف خزانات».
         */
        'أعمال الجبس والأسقف',
        'أعمال البناء والمحارة',
        'أعمال الأرضيات',
        'أعمال الستائر والتنجيد',
        'أعمال الكهرباء',
        'أعمال السباكة',
        'أعمال الدهانات',
        'أعمال الدش والاستقبال',
        /*
         * «فنون و ترفية», the last mute root. Booking-only, so the service
         * rule: the thing booked IS the priced row — an hour of a pool table
         * is paid for the way an hour of a desk is.
         *
         * These four spent one commit in the `modifier` block by mistake, and
         * OptionPriceRoleTest::test_the_seeder_is_idempotent caught it: the
         * next run of OptionPriceRolesSeeder would have demoted all four and
         * stopped a photographer pricing a wedding. Seventh time this file has
         * bitten, and the first time a test got there first.
         */
        'ألعاب ومرافق الترفيه',
        'خدمات التصوير',
        'أنواع الاستوديوهات',
        'المراكب والرحلات النيلية',
        'تخصصات طبية',            // كشف عظام
        'التحاليل الطبية',        // صورة دم كاملة
        'أنواع الأشعة',           // رنين مغناطيسي
        'خدمات الكوافير والتجميل', // قص شعر
        // «خيارات قابلة للتسعير» — the owner's own words for it. A cupping
        // centre performs sessions; a wet cupping session has a price the way a
        // hotel room does.
        'خدمات الحجامة',          // حجامة رطبة
        // Merged into the descriptive «أقسام الصيدلية» on 2026-08-05 and split
        // out again the same day, on the owner's call: while merged, قياس ضغط
        // and حقن were descriptive and a pharmacy could not price them at all.
        // What the shop STOCKS stays descriptive; what the pharmacist DOES is
        // a line. The price test separates the two lists cleanly.
        'خدمات الصيدلية',         // قياس ضغط
        'الأنشطة الرياضية',       // حصة سباحة
        'المواد الدراسية',        // حصة رياضيات
        'مجالات التدريب',         // كورس برمجة
        'اللغات',                  // كورس إنجليزي
        // The workshop benches, 2026-08-10. A garage is BOOKED and the job is
        // what is paid for, so «سمكرة» must carry a price the way «كشف عظام»
        // does. Declared the same day they were created — an undeclared group is
        // pushed back to `descriptive` on this seeder's next run.
        'تخصصات ورش السيارات',    // سمكرة، كهرباء سيارات
        'تخصصات ورش الأثاث',      // تنجيد، مطابخ ودريسنج
        'تخصصات ورش المعادن',     // خراطة، حدادة
        'تخصصات ورش الأجهزة',     // تصليح غسالات وبوتاجازات
        'تخصصات المحاماة',        // استشارة جنائي
        'تخصصات الهندسة',         // تصميم معماري
        'تخصصات المحاسبة',        // إقرار ضريبي
        'تخصصات الدعاية والإعلان', // هوية بصرية
        'تخصصات الديكور',         // تصميم داخلي
        'أثاث وتشطيب منزلي',      // غرفة نوم
        // شاتر كهربائي ≠ باب خشب — the same shape as «أثاث وتشطيب منزلي» above,
        // and carried by the same family of workshops and factories. There is no
        // catalog of door models behind it: the type IS the priced row.
        'أنواع الأبواب والشبابيك',
        'عقارات وممتلكات',        // شقة
        'تعبئة وتغليف ومستلزمات', // أكواب فوم
        'أنواع المناسبات',        // إيجار القاعة ليوم فرح
        'مركبات النقل والركاب',   // رحلة باص 50 راكب
        'موضة وعناية شخصية',      // ملابس، أقمشة، فساتين زفاف — after the split
    ],

    /*
    |--------------------------------------------------------------------------
    | modifier — changes the price of a line, never a line itself
    |--------------------------------------------------------------------------
    | Nobody buys «مودرن». They buy «غرفة نوم مودرن», and it costs more than
    | «غرفة نوم كلاسيك». This is the bucket that made a boolean is_priceable
    | insufficient: nine groups, one of them 43 car marques.
    */
    'modifier' => [
        'طراز الأثاث',            // غرفة نوم مودرن ≠ كلاسيك
        // شقة بيع ≠ شقة إيجار — the most violent one, and the same question a
        // car showroom asks. Renamed from «نوع التعامل العقاري» on 2026-08-08
        // when the vehicle showrooms were given it; see VehicleDealTypeSeeder.
        'نوع التعامل',
        'المراحل التعليمية',      // رياضيات ثانوي ≠ ابتدائي
        'نمط تقديم الخدمة',       // بسائق ≠ بدون · أونلاين ≠ حضوري
        'ماركات السيارات',        // ليموزين مرسيدس ≠ هيونداي
        'ماركات الموتوسيكلات',
        'نظام الوجبات',           // إقامة كاملة ≠ شامل الإفطار
        'إطلالة الوحدة',          // إطلالة بحرية أغلى
        /*
         * The coworking pair (2026-08-11), and they are the reason its units
         * are TWO rows instead of the owner's three: «مكتب بسكرتارية» is not a
         * third kind of office, it is «مكتب منفصل» + سكرتارية, and the heading
         * is the combination. «نظام التعاقد» is «نظام الوجبات» exactly — the
         * same desk at an hourly and a monthly price.
         */
        'خدمات المكتب',           // مكتب + سكرتارية + ريسبشن
        'نظام التعاقد',          // شهري ≠ بالساعة
        // A مأذون who comes to the hall charges for coming; the act signed is
        // the same act. The coworking shape again — line + modifier, not three
        // lines that cannot say «at home» about a divorce registration.
        'مكان العقد',             // بالمنزل ≠ بالمكتب

        /*
         * «مصانع», 2026-08-11. Twenty-six of its forty-four children could not
         * name one thing they make. They are MODIFIERS, not lines, and that is
         * the whole difference between a goods root and a services root:
         * nobody buys the phrase «طوب أحمر», they buy a catalog product, and
         * these say what the trade DEALS IN — the «ماركات السيارات» pattern.
         */
        'نظام التصنيع',           // حسب الطلب ≠ من المخزون
        'أنواع الطوب',
        'مستلزمات النجارة',
        'أنواع السجاد',
        'مواد البناء الأساسية',
        'أنواع أجهزة الكمبيوتر',
        'أصناف مستحضرات التجميل',
        'أنواع الزجاج',
        'الأدوات الصحية',
        'مستلزمات المنزل',
        'الخراطيم والوصلات',
        'المفاتيح والتوزيع الكهربائي',
        'أنواع الرخام والجرانيت',
        'أنواع المراتب',
        'المستلزمات الطبية',
        'الحدايد والبويات',
        'المواد الدوائية',
        'الأكياس والمنتجات البلاستيكية',
        'الصيني والخزف',
        'مستلزمات المطاعم',
        'أنواع الحديد',
        'أنواع الإسفنج',
        'أصناف لعب الأطفال',
        'أنواع الأخشاب',
        'بدائل الخشب والرخام',
        'أنواع الأصواف والخيوط',
        'طباعة العبوات والتغليف',
        // «شركات», the goods half of it — the eight traders that were mute.
        'لوازم الستائر',
        'الأنتيكات والتحف',
        'مستلزمات المقاهي',
        'أعمال التبريد والتكييف',
        'قطع الغيار حسب الآلة',   // renamed from «أنواع قطع الغيار» 2026-08-12
        'درجة قطعة الغيار',       // أصلي وكيل ≠ تجاري — the trade's real price axis
        'الأدوات المكتبية',
        /*
         * «المحلات أو أونلاين» — the fourteen shops that could not name their
         * stock. Goods trades, so modifiers: the priced rows are the catalog
         * products, and these say what is on the shelf.
         */
        'أصناف المجوهرات',
        'أنواع النظارات',
        'أنواع العطور',
        'أصناف المكملات',
        'أقسام المكتبة',
        'مستلزمات الصيد',
        'أجهزة الألعاب',
        'مشتقات التدخين',
        'النباتات ومستلزماتها',
        'المصنوعات الخشبية والديكور',
        'حالة المنتج',            // جديد ≠ مستعمل
        // Created as a modifier by AccessoryMergeSeeder on 2026-08-10 and found
        // sitting as `descriptive` the same day: it was never listed here, so
        // the first run of THIS seeder reset it. Fifth time. What it says is
        // what a shop STOCKS — nobody buys the phrase «اكسسوار موبايل», the
        // priced rows are the catalog products.
        // A PS4 hour and a PS5 hour are two prices for one line.
        'فئة جهاز الألعاب',       // بلايستيشن ٥ ≠ بلايستيشن ٤
        /*
         * The modifiers that were genuinely missing, 2026-08-11. Each is a
         * SECOND answer on one line: the same lorry to Aswan or across town,
         * the same hall morning or evening, the same bean light or dark.
         */
        'نطاق الشحن',             // دولي ≠ داخل المدينة
        'سرعة الشحن',             // نفس اليوم ≠ عادي
        'فترة الحجز',             // يوم كامل ≠ فترة صباحية
        'وحدة البيع',             // بالطن ≠ بالكيلو
        'درجة التحميص والطحن',    // غامق ≠ فاتح
        'حالة الدواجن',           // مقطّع ≠ حي
        'أنواع الإكسسوارات',      // اكسسوار موبايل ≠ اكسسوار سيارات
        'الجمهور المستهدف',       // حريمي / رجالي / أطفال — split out of موضة

        /*
         * These two were created as modifiers by PropertyModifierOptionsSeeder
         * and then silently reset to descriptive on the next run of THIS
         * seeder, because anything unlisted here is pushed back to descriptive.
         * A group that is not named in this file does not keep its role —
         * add it here whenever a seeder creates one.
         */
        // «عدد الغرف» was merged into «الغرف» (2026-08-05) and is listed above
        // as a `line` group now: once a hotel's جناح and a flat's ثلاث غرف sit
        // in one list, that list IS the thing being paid for, not a modifier on
        // it. Left here it would be a third name matching no group.
        'مستوى التشطيب',          // سوبر لوكس ≠ على المحارة

        /*
         * The «ماركات السيارات» pattern, reused. Each says what a trade DEALS
         * IN — it narrows a search and can change what a priced row is worth,
         * but nobody buys the phrase «حبوب وبقوليات». The priced rows are the
         * products themselves, in the catalog.
         *
         * Listed the same day they were created, because the note above is not
         * a warning about the past: «نوع قطع الغيار» was created on 2026-08-09
         * and was already one run away from being reset to descriptive.
         */
        'نوع قطع الغيار',          // فرامل BMW ≠ فرامل فيات
        'أصناف المنتجات الغذائية', // زيوت وسمن ≠ أغذية أطفال
        'أنواع الأجهزة الكهربائية', // إصلاح ثلاجة ≠ إصلاح مكيف
        'أنواع الأجهزة الرياضية',  // مشاية ≠ دمبل
        // The extrusion, not the finished opening: «أنواع الأبواب والشبابيك» is
        // a line because a doors business prices a shutter by the leaf, while a
        // profile is a catalog product sold by the metre.
        'قطاعات ومنتجات الألومنيوم',
        // «مفروشات - اقمشة هم فقراء جدا فى خياراتهم» — owner, 2026-08-12. The
        // fabric list stays a modifier: a bolt of cotton is a catalog product
        // and the fibre qualifies its price. «أصناف المفروشات» went the other
        // way on his instruction — see the `line` block above.
        'أنواع الأقمشة',
        // The platform had no lighting word at all until 2026-08-12; the two
        // نجف children said «تابلوه» and nothing else.
        'أنواع النجف والإضاءة',
    ],

    /*
    |--------------------------------------------------------------------------
    | descriptive — never priced (the default; listed for the record)
    |--------------------------------------------------------------------------
    | These are the WIDEST groups on the platform — الدفع والسداد reaches 240
    | children, التسليم والاستلام 129, نطاق التعامل 113. Left unclassified they
    | would put «كاش» and «ممنوع التدخين» in every merchant's pricing screen.
    |
    | «أقسام الصيدلية» sits here on purpose: «أدوية بشرية» is a shelf, not a
    | product. A pharmacy prices a named medicine out of the catalogue.
    */
    'descriptive' => [
        'الدفع والسداد',
        'التسليم والاستلام',
        'نطاق التعامل',
        'الاستبدال والإرجاع',
        'ملاءمة المكان',
        'مرافق ومعدات',
        'مرافق الإقامة',
        'تصنيف الإقامة',
        'مواصفات المنتج الغذائي',
        'أقسام الصيدلية',
    ],
];
