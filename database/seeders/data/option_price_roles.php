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
        /*
         * «رقّي ماركات الموتوسيكلات الى سطر» — owner, 2026-08-16, and it is the
         * exception «نوع المركبة» proves rather than breaks.
         *
         * A car showroom prices on the TYPE — سيدان، SUV، بيك أب — and the
         * marque qualifies it, which is why «ماركات السيارات» is still a
         * modifier one block down. A motorcycle answers none of those types and
         * the platform has no list of its own for them, so after «مركبة
         * معروضة» was withdrawn on 2026-08-16 17:48 the marque was the only
         * axis #189 had left and it could price nothing at all.
         *
         * The brand IS the heading a motorcycle showroom sells under — ياماها
         * against هوندا is the whole of the choice — so this is the shorter of
         * the two ways out, and the one that invents no words.
         */
        'ماركات الموتوسيكلات',    // ياماها، بينيلي — the heading a bike showroom sells under
        /*
         * «قطع الغيار حسب الآلة» — «راجع باقي أبناء الشركات», 2026-08-16, and
         * the same reasoning one line up.
         *
         * Held by «قطع غيار» #263 alone, the any-machine wholesaler, and it IS
         * what he sells: قطع غيار سيارات، معدات ثقيلة، أجهزة منزلية، مصاعد. As
         * a modifier it left him with no line at all, and the reader's
         * promotion rule then lifted BOTH his modifiers — so «أصلي وكيل» from
         * «درجة قطعة الغيار» was offered as a thing to price, which it is not.
         * Promotion is all-or-nothing by design; giving the child a real line
         * is what stops it, and the grade goes back to qualifying the part.
         *
         * Costs nothing elsewhere: one carrier. Its sibling #44 «قطع غيار
         * سيارات» prices on «نوع قطع الغيار» (WHICH SYSTEM) and is untouched —
         * the two lists are different axes, which company_child_vocabularies.php
         * settled on 2026-08-12.
         */
        'قطع الغيار حسب الآلة',   // قطع غيار مصاعد ≠ قطع غيار دراجات
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
        // «جراج» #119, 2026-08-12: booking and no retail, so the priced row is
        // the stay itself — an hour, a night, a monthly space.
        'خدمات الجراج والانتظار',
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
        /*
         * Moved out of `modifier` on 2026-08-16, by the owner's hand in the
         * admin and recorded here rather than argued with.
         *
         * The file kept it a modifier on a reading that was true of the group
         * and blind to its carriers: «which ranges do you deal in» is a real
         * question for a wholesaler, and answering it does not say what he
         * sells. But «مواد غذائية» #109 carries this group and NOTHING else
         * that prices — a modifier with no line under it, which is the exact
         * shape this whole sweep has been closing, and the last surviving
         * example of it.
         *
         * grocery_aisle_split.php still calls it a modifier in prose and that
         * prose is now history, not instruction; the two duplicate-vocabulary
         * findings it records are untouched by this.
         */
        'أصناف المنتجات الغذائية', // زيوت وسمن، أغذية أطفال
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
        // A night in a private room is a nightly rate and an operation is a
        // quoted figure — the same shape as «غرفة مزدوجة», which is why this
        // is not another facilities tick. Added 2026-08-17: until then the
        // platform had no word for admission at all, and a hospital and a
        // medical centre carried identical vocabularies.
        'الرعاية والتنويم',       // تنويم بغرفة خاصة، رعاية مركزة
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
        // «قسّم مرافق النادي الرياضي» — owner, 2026-08-16, and the same cut the
        // pharmacy got two lines up: what the place HAS stayed descriptive and
        // what somebody DOES for you became a line. A personal trainer, a
        // nutritionist, a bath attendant and a creche are four people's time,
        // and a gym sells all four beside the subscription.
        'خدمات النادي الرياضي',   // مدرب شخصي، حضانة أطفال
        // «شحن بري وبحري وجوى» was named for three modes and could say none of
        // them; its only line was a lorry list. The mode IS what a freight
        // company sells and it is priced per mode — a container by sea, a kilo
        // by air — so descriptive would have left the hole where it was.
        'وسيلة الشحن',            // شحن بحري، شحن جوي
        // Same cut again, for the guest: «نقل من المطار» is a driver, a car and
        // a fare, and it spent its life in «مرافق الإقامة» beside المسبح where
        // no price could reach it. One row, and a group of one is right when it
        // exists so something can be priced.
        'خدمات الفندق',           // نقل من المطار
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
        /*
         * The produce list «خضار وفاكهة» #114 answers with — mango, strawberry,
         * tomato, potato — created 2026-08-14 and left off this file entirely,
         * which is the mistake the «نوع قطع الغيار» note warns about happening
         * again: an unlisted group is reset to `descriptive` on the next run,
         * turning a trader's crop into a search filter he cannot price.
         *
         * `line`, not modifier, and the distinction is the whole file: «أصناف
         * المنتجات الغذائية» below qualifies a catalog product that already has
         * a price, while #114 has no catalog behind it at all. A tonne of
         * strawberries is what is sold, and «وحدة البيع» is what modifies it.
         */
        'أصناف الخضار والفاكهة',   // طن فراولة ≠ طن بصل
        // Same trade, same reading, 2026-08-16: the bird and the grain ARE what
        // is bought. «حالة الدواجن» and «وحدة البيع» are the modifiers on top —
        // «بط حي» and «بط مذبوح ومنظف» being two prices of one row is what
        // proves these are lines and not the other way round.
        'أنواع الدواجن والطيور',   // بط ≠ سمان
        'أنواع الحبوب والغلال',    // أردب قمح ≠ أردب عدس
        'أنواع الأعلاف',           // طن أعلاف دواجن ≠ طن تبن

        /*
        |----------------------------------------------------------------------
        | The goods reversal, 2026-08-16
        |----------------------------------------------------------------------
        | «معظم مجموعات الخيارات الوصفية هى المفروض ان تكون سطر مسعر كما فعلت فى
        | بعضهم».
        |
        | Everything below was a `modifier` on one argument, stated in this file
        | and in `shop_child_vocabularies.php`: these are goods trades, the
        | priced rows are catalog products, and the group only says what is on
        | the shelf. It was a reasonable argument and the data does not support
        | it.
        |
        | The catalog holds 1,164 products across 65 shelves, and for these
        | trades the shelf is a handful: seven products for every eyewear shop
        | on the platform, seven for gold, six for marble, six for locksmiths,
        | six for carpets. A merchant whose vocabulary is a modifier and whose
        | catalog is six rows deep can price nothing he actually sells — the
        | modifier has no line under it, which is the same emptiness «حالة
        | الدواجن» and «وحدة البيع» were sitting over on دواجن and حبوب وغلال
        | two days ago.
        |
        | So the rule the goods roots were finished under is narrowed rather
        | than dropped: a modifier is a SECOND answer on a line — new or used,
        | BMW or Fiat, by the tonne or by the kilo — and a group that answers
        | «what do you sell» is a line however deep the catalog behind it is.
        | The genuine qualifiers stay below, and the list of them is shorter and
        | sharper for it.
        |
        | This does not remove anything: a merchant's ticks are untouched, and
        | every one of these groups keeps its children. What changes is that
        | the ticks now reach the pricing screen.
        */

        // مصانع — what the works actually makes
        'أنواع الطوب',
        'مستلزمات النجارة',
        'أنواع السجاد',
        'مواد البناء الأساسية',
        'أنواع أجهزة الكمبيوتر',
        'أصناف مستحضرات التجميل',
        'أنواع الزجاج',
        // The other half of «سيراميك وأدوات صحية» #138, added 2026-08-16. Two
        // vocabularies on one child because the shop is one shop — see
        // factory_child_vocabularies.php for why it is not two children.
        'أنواع السيراميك والبورسلين', // بورسلانو، موزاييك
        'الأدوات الصحية',
        'مستلزمات المنزل',
        'الخراطيم والوصلات',
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
        'قطاعات ومنتجات الألومنيوم',
        'أنواع الأجهزة الكهربائية',
        'أنواع الأجهزة الرياضية',

        // شركات — the wholesale traders
        'لوازم الستائر',
        'الأنتيكات والتحف',
        'مستلزمات المقاهي',
        'أعمال التبريد والتكييف',
        'الأدوات المكتبية',

        // المحلات أو أونلاين — the shop floor
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
        'العدد والأدوات الكهربائية',
        'قطع غيار الأجهزة المنزلية',
        'أنواع الزيوت والسوائل',
        'الإطارات والجنوط',
        'أنواع الإكسسوارات',
        'نوع قطع الغيار',
        'أنواع النجف والإضاءة',
        'المفاتيح والتوزيع الكهربائي',

        /*
         * The pharmacy's shelves, moved out of `descriptive` in the same pass.
         * «أدوية بشرية» was called a shelf and not a product — but a pharmacy
         * with thirteen catalog rows platform-wide prices its counters, and
         * «مستحضرات تجميل» and «أجهزة قياس منزلية» are two different margins.
         */
        'أقسام الصيدلية',

        // The five written today for the shops that were answering somebody
        // else's question — see shop_child_vocabularies.php.
        'أجهزة الموبايل وملحقاتها',
        'خدمات المفاتيح والأقفال',
        'أنواع الستائر والديكور',
        'أصناف العصائر والمشروبات',
        'أصناف الحلويات والجاتوه',
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
         * «مصانع», 2026-08-11 — what survives of that block after the goods
         * reversal above. «نظام التصنيع» is a genuine second answer on a line
         * (the same order made to spec or taken from stock); the twenty-six
         * trade lists that sat beside it here are lines now.
         */
        'نظام التصنيع',           // حسب الطلب ≠ من المخزون
        // «قطع الغيار حسب الآلة» moved to `line` on 2026-08-16 — see the block
        // in that list. Declared in two tiers it was silently the later one,
        // which is worth knowing: this file is read top to bottom and a name in
        // two blocks takes the LAST, with no warning from anywhere.
        'درجة قطعة الغيار',       // أصلي وكيل ≠ تجاري — the trade's real price axis
        'حالة المنتج',            // جديد ≠ مستعمل
        // A PS4 hour and a PS5 hour are two prices for one line.
        'فئة جهاز الألعاب',       // بلايستيشن ٥ ≠ بلايستيشن ٤
        'ماركات الموبيلات',       // شاشة سامسونج ≠ شاشة شاومي
        /*
         * The modifiers that were genuinely missing, 2026-08-11. Each is a
         * SECOND answer on one line: the same lorry to Aswan or across town,
         * the same hall morning or evening, the same bean light or dark.
         */
        'نطاق الشحن',             // دولي ≠ داخل المدينة
        'سرعة الشحن',             // نفس اليوم ≠ عادي
        // The third axis on the same lorry: a refrigerated run and a dry one
        // are the same vehicle at two prices. «مقطورة» is NOT in it — that is
        // «مركبات النقل والركاب», and which vehicle is a different question
        // from how the load is carried.
        'تجهيز الشحن البري',      // مبرد ≠ جاف
        'فترة الحجز',             // يوم كامل ≠ فترة صباحية
        'وحدة البيع',             // بالطن ≠ بالكيلو
        'درجة التحميص والطحن',    // غامق ≠ فاتح
        'حالة الدواجن',           // مقطّع ≠ حي
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
        /*
         * «مساحات … تتناسب مع فروع مجموعة عقارات وممتلكات حتى يكون العرض سهل
         * وايضا البحث يكون محدد» — owner, 2026-08-16.
         *
         * A modifier and not a line: what is bought is the PROPERTY — «شقة»،
         * «مخازن» — and «100 – 150 م²» on its own is not a thing anybody buys.
         * It qualifies the same line «مستوى التشطيب» does, one row down.
         */
        'المساحة',                // 100 – 150 م² ≠ 2000 – 5000 م²
        // The farm's own unit, added the same day. A فدان is ≈4200 م², so the
        // two ladders would overlap if they were one — a search offering two
        // rows for one plot is the opposite of «محدد». Two units, two clean
        // ladders, and the merchant answers the one his trade quotes in.
        'المساحة بالفدان',        // فدان – 3 أفدنة
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
        // «أصناف المنتجات الغذائية» left this block on 2026-08-16 — see the
        // `line` list above. It was the one survivor of the modifier pattern
        // and it was the clearest case against it: a modifier with no line
        // under it prices nothing at all.
        // «مفروشات - اقمشة هم فقراء جدا فى خياراتهم» — owner, 2026-08-12. The
        // fabric list stays a modifier: a bolt of cotton is a catalog product
        // and the fibre qualifies its price. «أصناف المفروشات» went the other
        // way on his instruction — see the `line` block above.
        'أنواع الأقمشة',
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
        // «أقسام الصيدلية» moved to `line` on 2026-08-16 — see the goods
        // reversal there. It was here on the argument that «أدوية بشرية» is a
        // shelf and the medicine is the product; a pharmacy with thirteen
        // catalog rows platform-wide prices its counters instead.

        /*
         * Named rather than left to the default, 2026-08-16. All four were
         * relying on «anything unlisted is descriptive» and the seeder reported
         * them as such on every run — «بقيت وصفية (غير مذكورة)». That is the
         * right role for each, and a group whose role is an absence is one
         * rename away from being a surprise.
         */
        'الحد الأدنى للطلب',      // كرتونة / باليت / طن — how little you may buy
        'نوع العملاء',            // أفراد / شركات / جهات حكومية
        'تسهيلات ومرافق طبية',    // يقبل التأمين / أشعة داخلية
        'تجهيزات مساحة العمل',    // بروجيكتور / لوكرز / دخول ٢٤٧
        /*
         * «مرافق النادي الرياضي» was flagged as the one genuinely mixed group
         * left — the rooms and the bill under one name — and «قسّم مرافق النادي
         * الرياضي» (owner, 2026-08-16) settled it. What stayed here is the
         * PLACE: المسبح، الساونا، الجاكوزي، قسم سيدات، خزائن ودش، انتظار
         * سيارات، كيدز ايريا. What a club charges for went to «خدمات النادي
         * الرياضي» in `line`, and the rule that divides them is written out in
         * stray_child_vocabularies.php.
         *
         * Descriptive is right for what remains, and for the same reason
         * «مرافق الإقامة» is: a member does not buy the locker, he buys the
         * subscription and the locker comes with it.
         */
        'مرافق النادي الرياضي',

        /*
         * The tombstone. INACTIVE, so no screen offers it and the role never
         * shows — declared anyway because this file's own rule says a group
         * whose role is an absence is one rename away from being a surprise,
         * and because the seeder reports every undeclared group on every run.
         * A row that was dissolved into others is described by nothing and
         * prices nothing.
         */
        'صفوف متقاعدة',
    ],
];
