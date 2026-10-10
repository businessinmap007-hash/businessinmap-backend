<?php

/*
|--------------------------------------------------------------------------
| «الإجراءات الطبية» — the platform's list a hospital picks its procedures from
|--------------------------------------------------------------------------
| «اضف للمستشفى إجراء طبي مثل العمليات الجراحية وما إلى ذلك» — المالك، 2026-10-10.
|
| kind => [ [arabic, english], … ]. A hospital that does something not listed adds its own entry (visible to it alone).
*/

return [

    'surgery' => [
        ['استئصال الزائدة الدودية', 'Appendectomy'],
        ['استئصال المرارة بالمنظار', 'Laparoscopic Cholecystectomy'],
        ['إصلاح الفتق', 'Hernia Repair'],
        ['ولادة قيصرية', 'Cesarean Section'],
        ['استئصال اللوزتين', 'Tonsillectomy'],
        ['استئصال الغدة الدرقية', 'Thyroidectomy'],
        ['استئصال الرحم', 'Hysterectomy'],
        ['عملية البواسير', 'Hemorrhoidectomy'],
        ['علاج الحصوات الجراحي', 'Surgical Stone Removal'],
        ['تغيير مفصل الركبة', 'Knee Replacement'],
        ['تغيير مفصل الورك', 'Hip Replacement'],
        ['تثبيت الكسور', 'Fracture Fixation'],
        ['جراحة الانزلاق الغضروفي', 'Disc Surgery'],
        ['عملية المياه البيضاء', 'Cataract Surgery'],
        ['تصحيح النظر بالليزر', 'Laser Vision Correction'],
        ['جراحة السمنة (تكميم المعدة)', 'Sleeve Gastrectomy'],
        ['جراحة القلب المفتوح', 'Open Heart Surgery'],
        ['جراحة الأوعية الدموية', 'Vascular Surgery'],
        ['جراحة المسالك البولية', 'Urological Surgery'],
        ['جراحة التجميل', 'Plastic Surgery'],
        ['جراحة الأطفال', 'Pediatric Surgery'],
    ],

    'endoscopy' => [
        ['منظار المعدة', 'Gastroscopy'],
        ['منظار القولون', 'Colonoscopy'],
        ['منظار المسالك البولية', 'Cystoscopy'],
        ['منظار المفاصل', 'Arthroscopy'],
        ['منظار الرحم', 'Hysteroscopy'],
        ['منظار الجيوب الأنفية', 'Sinus Endoscopy'],
        ['منظار القصبات الهوائية', 'Bronchoscopy'],
    ],

    'procedure' => [
        ['قسطرة قلبية تشخيصية', 'Diagnostic Cardiac Catheterization'],
        ['تركيب دعامة', 'Stent Placement'],
        ['غسيل كلى', 'Dialysis Session'],
        ['جلسة علاج كيماوي', 'Chemotherapy Session'],
        ['جلسة علاج إشعاعي', 'Radiotherapy Session'],
        ['تفتيت الحصوات بالموجات', 'Lithotripsy'],
        ['حقن مفصلي', 'Joint Injection'],
        ['تجبير وجبائر', 'Casting & Splinting'],
        ['تغيير على جرح', 'Wound Dressing'],
        ['سحب سائل أو عينة بالإبرة', 'Needle Aspiration / Biopsy'],
    ],
];
