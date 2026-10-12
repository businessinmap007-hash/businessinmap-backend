<?php

/*
|--------------------------------------------------------------------------
| «خدمات التخصص» — the platform's lists a specialty clinic picks its services from
|--------------------------------------------------------------------------
| «أنشئ قائمة خدمات عيادات الأسنان» — المالك، 2026-10-13. «ابدأ بقائمة خدمات الجلدية والتجميل» — المالك، 2026-10-14.
|
| specialty => kind => [ [arabic, english], … ]. A clinic that does something not listed adds its own entry (visible to
| it alone). The clinic's visit fee (كشف / استشارة) is NOT here: it is a visit kind the clinic already prices.
|
| Teeth cleaning and whitening live under «dental» and nowhere else; fillers, botox and the medical laser live under «skin»
| and are never a beauty centre's (owner rulings 2026-10-12). Surgical work — a surgical facelift, a rhinoplasty — is a
| hospital's: see data/medical_procedures.php, kind «plastic».
*/

return [

    // ───────── عيادة الأسنان ─────────
    'dental' => [

        // الأشعة والتشخيص
        'checkup' => [
            ['أشعة بانوراما للفكين', 'Panoramic X-ray'],
            ['أشعة سنية صغيرة (بيري أبيكال)', 'Periapical X-ray'],
            ['أشعة سيفالومترية', 'Cephalometric X-ray'],
            ['أشعة مقطعية ثلاثية الأبعاد (CBCT)', '3D CBCT Scan'],
            ['أخذ مقاسات وعمل موديل دراسة', 'Study Models'],
        ],

        // الوقاية والتنظيف
        'preventive' => [
            ['تنظيف الأسنان وإزالة الجير', 'Scaling and Cleaning'],
            ['تلميع الأسنان', 'Teeth Polishing'],
            ['علاج بالفلورايد', 'Fluoride Treatment'],
            ['سد الشقوق السنية (سيلانت)', 'Fissure Sealant'],
            ['علاج حساسية الأسنان', 'Dentin Hypersensitivity Treatment'],
            ['واقي ليلي للأسنان', 'Night Guard'],
        ],

        // الحشو والترميم
        'restorative' => [
            ['حشو تجميلي (كومبوزيت)', 'Composite Filling'],
            ['حشو أسمنت زجاجي', 'Glass Ionomer Filling'],
            ['حشو مؤقت', 'Temporary Filling'],
            ['ترميم بدعامة (بناء قلب السن)', 'Core Build-up'],
            ['إصلاح كسر في السن', 'Chipped Tooth Repair'],
        ],

        // علاج الجذور
        'endodontic' => [
            ['علاج عصب سن أمامي', 'Root Canal — Front Tooth'],
            ['علاج عصب ضرس', 'Root Canal — Molar'],
            ['إعادة علاج عصب', 'Root Canal Retreatment'],
            ['سحب العصب وتطهير القنوات', 'Pulpectomy'],
            ['علاج خراج الأسنان', 'Dental Abscess Treatment'],
        ],

        // اللثة
        'periodontal' => [
            ['علاج التهاب اللثة', 'Gum Disease Treatment'],
            ['تنظيف عميق تحت اللثة', 'Deep Scaling and Root Planing'],
            ['جراحة اللثة', 'Gum Surgery'],
            ['تجميل اللثة', 'Gum Contouring'],
            ['تثبيت الأسنان المتحركة', 'Splinting of Loose Teeth'],
        ],

        // الجراحة والزراعة
        'surgical' => [
            ['خلع سن عادي', 'Simple Extraction'],
            ['خلع جراحي', 'Surgical Extraction'],
            ['خلع ضرس عقل', 'Wisdom Tooth Extraction'],
            ['خلع ضرس عقل منحشر', 'Impacted Wisdom Tooth Removal'],
            ['زراعة سن', 'Dental Implant'],
            ['تطعيم عظمي', 'Bone Graft'],
            ['رفع جيوب الفك العلوي', 'Sinus Lift'],
            ['استئصال كيس أو ورم صغير', 'Cyst Removal'],
        ],

        // التركيبات
        'prosthetic' => [
            ['تاج معدني', 'Metal Crown'],
            ['تاج خزف على معدن', 'Porcelain-Fused-to-Metal Crown'],
            ['تاج زيركون', 'Zirconia Crown'],
            ['تاج إيماكس', 'E-max Crown'],
            ['كوبري أسنان', 'Dental Bridge'],
            ['طقم أسنان كامل', 'Complete Denture'],
            ['طقم أسنان جزئي', 'Partial Denture'],
            ['تركيب فوق زراعة', 'Implant Crown'],
        ],

        // التقويم
        'orthodontic' => [
            ['تقويم معدني', 'Metal Braces'],
            ['تقويم خزفي', 'Ceramic Braces'],
            ['تقويم شفاف (إنفزلاين)', 'Clear Aligners'],
            ['تقويم متحرك', 'Removable Appliance'],
            ['مثبّت بعد التقويم', 'Retainer'],
        ],

        // تجميل الأسنان
        'cosmetic' => [
            ['تبييض الأسنان بالعيادة', 'In-office Teeth Whitening'],
            ['تبييض الأسنان بالمنزل', 'Take-home Teeth Whitening'],
            ['فينير', 'Veneers'],
            ['عدسات لاصقة للأسنان (لومينير)', 'Lumineers'],
            ['ابتسامة هوليوود', 'Hollywood Smile'],
            ['إغلاق فراغات الأسنان', 'Diastema Closure'],
        ],

        // أسنان الأطفال
        'pediatric' => [
            ['حشو سن لبني', 'Primary Tooth Filling'],
            ['خلع سن لبني', 'Primary Tooth Extraction'],
            ['علاج عصب سن لبني', 'Pulpotomy'],
            ['تلبيسة معدنية للأطفال', 'Stainless Steel Crown'],
            ['حافظ مسافة للأطفال', 'Space Maintainer'],
        ],
    ],

    // ───────── عيادة الجلدية والتجميل ─────────
    'skin' => [

        // الحقن التجميلية
        'injectables' => [
            ['فيلر الخدود', 'Cheek Filler'],
            ['فيلر الشفاه', 'Lip Filler'],
            ['فيلر الذقن والفك', 'Chin and Jawline Filler'],
            ['فيلر تحت العين', 'Under-eye Filler'],
            ['فيلر الأنف بدون جراحة', 'Non-surgical Rhinoplasty Filler'],
            ['بوتوكس الجبهة وخطوط العين', 'Forehead and Crow\'s Feet Botox'],
            ['بوتوكس الفك', 'Masseter Botox'],
            ['بوتوكس التعرق', 'Hyperhidrosis Botox'],
            ['ميزوثيرابي للوجه', 'Facial Mesotherapy'],
            ['بلازما PRP للوجه', 'Facial PRP'],
            ['حقن تحسين جودة الجلد (سكين بوستر)', 'Skin Boosters'],
        ],

        // شد وتجديد الوجه بلا جراحة
        'face_lifting' => [
            ['خيوط شد الوجه', 'Thread Lift'],
            ['الهايفو لشد الوجه (HIFU)', 'HIFU Face Lift'],
            ['شد الوجه بالترددات الراديوية', 'Radiofrequency Skin Tightening'],
            ['شد الرقبة بدون جراحة', 'Non-surgical Neck Tightening'],
            ['الميكرونيدلنج', 'Microneedling'],
            ['الميكرونيدلنج بالترددات الراديوية', 'Radiofrequency Microneedling'],
            ['تجديد البشرة بالبلازما', 'Plasma Skin Resurfacing'],
        ],

        // الليزر
        'laser' => [
            ['ليزر إزالة الشعر', 'Laser Hair Removal'],
            ['إزالة الوشم بالليزر', 'Laser Tattoo Removal'],
            ['علاج التصبغات والكلف بالليزر', 'Pigmentation Laser'],
            ['علاج الأوعية الدموية الظاهرة بالليزر', 'Vascular Laser'],
            ['ليزر الفراكشنال', 'Fractional Laser'],
            ['ليزر ثاني أكسيد الكربون', 'CO2 Laser'],
            ['علاج حب الشباب وآثاره بالليزر', 'Acne and Acne Scar Laser'],
            ['إزالة الثآليل والزوائد الجلدية بالليزر', 'Warts and Skin Tag Laser Removal'],
            ['إزالة الشامات', 'Mole Removal'],
        ],

        // الندبات والجروح
        'scars' => [
            ['علاج الندبات', 'Scar Treatment'],
            ['علاج ندبات حب الشباب', 'Acne Scar Treatment'],
            ['علاج علامات التمدد', 'Stretch Mark Treatment'],
            ['علاج الجدرات (الكيلويد)', 'Keloid Treatment'],
            ['حقن الكورتيزون للندبات', 'Steroid Injection for Scars'],
            ['تجميل الجروح وخياطتها', 'Cosmetic Wound Closure'],
            ['تنظيف الجروح وتضميدها', 'Wound Cleaning and Dressing'],
            ['علاج الجروح المزمنة', 'Chronic Wound Care'],
        ],

        // التقشير وعلاج البشرة
        'peels' => [
            ['تقشير كيميائي', 'Chemical Peel'],
            ['تقشير بالأحماض', 'Acid Peel'],
            ['علاج الكلف والتصبغات', 'Melasma and Pigmentation Treatment'],
            ['علاج حب الشباب', 'Acne Treatment'],
            ['تنظيف البشرة العميق', 'Deep Facial Cleansing'],
            ['علاج المسام الواسعة', 'Enlarged Pores Treatment'],
            ['علاج الهالات السوداء', 'Dark Circles Treatment'],
        ],

        // الأمراض الجلدية
        'skin_disease' => [
            ['علاج الأكزيما', 'Eczema Treatment'],
            ['علاج الصدفية', 'Psoriasis Treatment'],
            ['علاج البهاق', 'Vitiligo Treatment'],
            ['علاج الثآليل بالتجميد', 'Wart Cryotherapy'],
            ['علاج الفطريات الجلدية', 'Fungal Skin Infection Treatment'],
            ['علاج الحساسية الجلدية', 'Skin Allergy Treatment'],
        ],

        // الشعر وفروة الرأس
        'hair' => [
            ['بلازما PRP لفروة الرأس', 'Scalp PRP'],
            ['ميزوثيرابي الشعر', 'Hair Mesotherapy'],
            ['علاج الصلع الوراثي', 'Androgenetic Alopecia Treatment'],
            ['علاج الثعلبة', 'Alopecia Areata Treatment'],
            ['علاج القشرة وفروة الرأس', 'Dandruff and Scalp Treatment'],
            ['ليزر تنشيط الشعر', 'Low-level Laser Hair Therapy'],
        ],

        // الجسم
        'body' => [
            ['حقن تفتيت الدهون', 'Fat Dissolving Injections'],
            ['شد الجسم بالترددات الراديوية', 'Body Radiofrequency Tightening'],
            ['علاج السيلوليت', 'Cellulite Treatment'],
        ],
    ],

];
