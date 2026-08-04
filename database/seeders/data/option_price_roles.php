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
        'نوع المركبة',            // سيدان — BMW; the brand needs something to be the brand OF
        'تخصصات طبية',            // كشف عظام
        'التحاليل الطبية',        // صورة دم كاملة
        'أنواع الأشعة',           // رنين مغناطيسي
        'خدمات الكوافير والتجميل', // قص شعر
        'خدمات الصيدلية',         // قياس ضغط
        'الأنشطة الرياضية',       // حصة سباحة
        'المواد الدراسية',        // حصة رياضيات
        'مجالات التدريب',         // كورس برمجة
        'اللغات',                  // كورس إنجليزي
        'تخصصات المحاماة',        // استشارة جنائي
        'تخصصات الهندسة',         // تصميم معماري
        'تخصصات المحاسبة',        // إقرار ضريبي
        'تخصصات الدعاية والإعلان', // هوية بصرية
        'تخصصات الديكور',         // تصميم داخلي
        'أثاث وتشطيب منزلي',      // غرفة نوم
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
        'نوع التعامل العقاري',    // شقة بيع ≠ شقة إيجار — the most violent one
        'المراحل التعليمية',      // رياضيات ثانوي ≠ ابتدائي
        'نمط تقديم الخدمة',       // بسائق ≠ بدون · أونلاين ≠ حضوري
        'ماركات السيارات',        // ليموزين مرسيدس ≠ هيونداي
        'ماركات الموتوسيكلات',
        'نظام الوجبات',           // إقامة كاملة ≠ شامل الإفطار
        'إطلالة الوحدة',          // إطلالة بحرية أغلى
        'حالة المنتج',            // جديد ≠ مستعمل
        'الجمهور المستهدف',       // حريمي / رجالي / أطفال — split out of موضة

        /*
         * These two were created as modifiers by PropertyModifierOptionsSeeder
         * and then silently reset to descriptive on the next run of THIS
         * seeder, because anything unlisted here is pushed back to descriptive.
         * A group that is not named in this file does not keep its role —
         * add it here whenever a seeder creates one.
         */
        'عدد الغرف',              // شقة غرفتين ≠ ثلاث غرف
        'مستوى التشطيب',          // سوبر لوكس ≠ على المحارة
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
