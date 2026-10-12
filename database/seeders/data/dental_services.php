<?php

/*
|--------------------------------------------------------------------------
| «خدمات عيادات الأسنان» — the platform's list a dental clinic picks its services from
|--------------------------------------------------------------------------
| «أنشئ قائمة خدمات عيادات الأسنان» — المالك، 2026-10-13. Teeth cleaning and whitening live HERE and nowhere else:
| a beauty centre never offers them (owner ruling 2026-10-12, see BeautyCenterChildSeeder).
|
| kind => [ [arabic, english], … ]. A clinic that does something not listed adds its own entry (visible to it alone).
| The clinic's visit fee (كشف / استشارة) is NOT here: it is a visit kind the clinic already prices.
*/

return [

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

];
