<?php

/*
|--------------------------------------------------------------------------
| «الإجراءات الطبية» — the platform's list a hospital picks its procedures from
|--------------------------------------------------------------------------
| «اضف للمستشفى إجراء طبي مثل العمليات الجراحية وما إلى ذلك» — المالك، 2026-10-10.
| «ضيف كل أنواع العمليات المعروفة والمستشفى يختار العمليات التي تتم عندها» — المالك، 2026-10-13.
|
| kind => [ [arabic, english], … ]. The kinds are the surgical specialties in the order a patient reads them, then
| the endoscopies and the treatment procedures. A hospital that does something not listed adds its own entry under any
| kind (visible to it alone).
|
| The first 38 rows were seeded on 2026-10-10 under three kinds only; the seeder RE-FILES a platform row by its Arabic
| name (the id stays, so every hospital's choice and every request keeps pointing at it) — never delete or rename one
| here, move it to the kind it belongs to.
*/

return [

    // الجراحة العامة
    'surgery' => [
        ['استئصال الزائدة الدودية', 'Appendectomy'],
        ['استئصال المرارة بالمنظار', 'Laparoscopic Cholecystectomy'],
        ['إصلاح الفتق', 'Hernia Repair'],
        ['عملية البواسير', 'Hemorrhoidectomy'],
        ['استئصال الغدة الدرقية', 'Thyroidectomy'],
        ['علاج الناسور الشرجي', 'Anal Fistula Surgery'],
        ['علاج الشق الشرجي', 'Anal Fissure Surgery'],
        ['جراحة الجيب الشعري', 'Pilonidal Sinus Surgery'],
        ['فتح وتصريف خراج', 'Abscess Incision and Drainage'],
        ['استئصال كتلة من الثدي', 'Breast Lump Excision'],
        ['استئصال الطحال', 'Splenectomy'],
        ['جراحة المعدة', 'Gastric Surgery'],
        ['جراحة القولون والمستقيم', 'Colorectal Surgery'],
        ['جراحة البنكرياس', 'Pancreatic Surgery'],
        ['استئصال جزء من الكبد', 'Hepatic Resection'],
    ],

    // العظام والمفاصل
    'orthopedic' => [
        ['تغيير مفصل الركبة', 'Knee Replacement'],
        ['تغيير مفصل الورك', 'Hip Replacement'],
        ['تثبيت الكسور', 'Fracture Fixation'],
        ['تغيير مفصل الكتف', 'Shoulder Replacement'],
        ['إصلاح الرباط الصليبي', 'ACL Reconstruction'],
        ['إصلاح الغضروف الهلالي بالمنظار', 'Arthroscopic Meniscus Repair'],
        ['إصلاح أوتار الكتف', 'Rotator Cuff Repair'],
        ['تثبيت الكسور بالمسمار النخاعي', 'Intramedullary Nailing'],
        ['تحرير النفق الرسغي', 'Carpal Tunnel Release'],
        ['جراحة اليد', 'Hand Surgery'],
        ['جراحة تشوهات القدم', 'Foot Deformity Surgery'],
        ['جراحة الأوتار والأربطة', 'Tendon and Ligament Surgery'],
    ],

    // النساء والتوليد
    'obstetrics' => [
        ['ولادة طبيعية', 'Normal Delivery'],
        ['ولادة قيصرية', 'Cesarean Section'],
        ['استئصال الرحم', 'Hysterectomy'],
        ['كحت الرحم', 'Dilation and Curettage'],
        ['استئصال الأورام الليفية', 'Myomectomy'],
        ['استئصال أكياس المبيض', 'Ovarian Cystectomy'],
        ['ربط قناتي فالوب', 'Tubal Ligation'],
        ['علاج الحمل خارج الرحم', 'Ectopic Pregnancy Treatment'],
        ['تثبيت عنق الرحم', 'Cervical Cerclage'],
        ['علاج هبوط الرحم', 'Uterine Prolapse Repair'],
        ['الحقن المجهري', 'ICSI'],
    ],

    // المسالك البولية والكلى
    'urology' => [
        ['جراحة المسالك البولية', 'Urological Surgery'],
        ['علاج الحصوات الجراحي', 'Surgical Stone Removal'],
        ['استئصال البروستاتا', 'Prostatectomy'],
        ['تفتيت الحصى بالليزر', 'Laser Lithotripsy'],
        ['استئصال الكلية', 'Nephrectomy'],
        ['زراعة الكلى', 'Kidney Transplant'],
        ['علاج دوالي الخصية', 'Varicocele Repair'],
        ['علاج الاستسقاء الخصوي', 'Hydrocele Repair'],
        ['ختان الذكور', 'Circumcision'],
        ['تركيب دعامة حالب', 'Ureteral Stent Placement'],
        ['علاج سلس البول', 'Urinary Incontinence Surgery'],
    ],

    // الأنف والأذن والحنجرة
    'ent' => [
        ['استئصال اللوزتين', 'Tonsillectomy'],
        ['استئصال اللحمية', 'Adenoidectomy'],
        ['جراحة الجيوب الأنفية', 'Sinus Surgery'],
        ['تصحيح الحاجز الأنفي', 'Septoplasty'],
        ['ترقيع طبلة الأذن', 'Tympanoplasty'],
        ['تركيب أنبوب تهوية بالأذن', 'Ear Tube Insertion'],
        ['زراعة القوقعة', 'Cochlear Implant'],
        ['جراحة الحنجرة', 'Laryngeal Surgery'],
    ],

    // العيون
    'ophthalmology' => [
        ['عملية المياه البيضاء', 'Cataract Surgery'],
        ['تصحيح النظر بالليزر', 'Laser Vision Correction'],
        ['علاج المياه الزرقاء', 'Glaucoma Surgery'],
        ['علاج انفصال الشبكية', 'Retinal Detachment Repair'],
        ['ليزر الشبكية', 'Retinal Laser'],
        ['زراعة القرنية', 'Corneal Transplant'],
        ['علاج القرنية المخروطية (الربط التصالبي)', 'Corneal Cross-linking'],
        ['عملية الحول', 'Strabismus Surgery'],
        ['إزالة الظفرة', 'Pterygium Removal'],
        ['جراحة الجفون', 'Eyelid Surgery'],
    ],

    // القلب والصدر والأوعية الدموية
    'cardiac' => [
        ['جراحة القلب المفتوح', 'Open Heart Surgery'],
        ['جراحة الأوعية الدموية', 'Vascular Surgery'],
        ['جراحة تحويل مسار الشرايين التاجية', 'Coronary Artery Bypass'],
        ['تغيير صمام القلب', 'Heart Valve Replacement'],
        ['إصلاح تمدد الأبهر', 'Aortic Aneurysm Repair'],
        ['علاج دوالي الساقين', 'Varicose Vein Surgery'],
        ['زراعة منظم ضربات القلب', 'Pacemaker Implantation'],
        ['وصلة غسيل الكلى (ناسور شرياني وريدي)', 'AV Fistula for Dialysis'],
        ['جراحة الصدر', 'Thoracic Surgery'],
        ['استئصال فص الرئة', 'Lobectomy'],
    ],

    // المخ والأعصاب والعمود الفقري
    'neuro' => [
        ['جراحة الانزلاق الغضروفي', 'Disc Surgery'],
        ['تثبيت الفقرات', 'Spinal Fusion'],
        ['علاج تضيق القناة الشوكية', 'Spinal Stenosis Decompression'],
        ['جراحة أورام المخ', 'Brain Tumor Surgery'],
        ['علاج النزيف الدماغي', 'Intracerebral Hemorrhage Surgery'],
        ['تصريف استسقاء الدماغ', 'Hydrocephalus Shunt'],
        ['جراحة الأعصاب الطرفية', 'Peripheral Nerve Surgery'],
    ],

    // التجميل والحروق
    'plastic' => [
        ['جراحة التجميل', 'Plastic Surgery'],
        ['تجميل الأنف', 'Rhinoplasty'],
        ['شفط الدهون', 'Liposuction'],
        ['شد البطن', 'Abdominoplasty'],
        ['تكبير أو تصغير الثدي', 'Breast Augmentation or Reduction'],
        ['شد الوجه', 'Facelift'],
        ['تجميل الجفون', 'Blepharoplasty'],
        ['علاج الحروق وترقيع الجلد', 'Burn Treatment and Skin Grafting'],
        ['إزالة الندبات', 'Scar Revision'],
        ['زراعة الشعر', 'Hair Transplant'],
    ],

    // جراحة الأطفال
    'pediatric' => [
        ['جراحة الأطفال', 'Pediatric Surgery'],
        ['علاج الفتق الإربي للأطفال', 'Pediatric Inguinal Hernia Repair'],
        ['علاج الخصية المعلقة', 'Orchiopexy'],
        ['ختان الأطفال', 'Pediatric Circumcision'],
        ['جراحة تضيق البواب', 'Pyloric Stenosis Surgery'],
        ['علاج الشفة الأرنبية وشق الحنك', 'Cleft Lip and Palate Repair'],
    ],

    // جراحة الأورام
    'oncology' => [
        ['استئصال الثدي', 'Mastectomy'],
        ['استئصال ورم القولون', 'Colon Cancer Surgery'],
        ['استئصال ورم المعدة', 'Gastrectomy for Cancer'],
        ['استئصال ورم المثانة بالمنظار', 'Transurethral Resection of Bladder Tumor'],
        ['استئصال أورام الجلد', 'Skin Tumor Excision'],
        ['استئصال الغدد الليمفاوية', 'Lymph Node Dissection'],
    ],

    // السمنة
    'bariatric' => [
        ['جراحة السمنة (تكميم المعدة)', 'Sleeve Gastrectomy'],
        ['تحويل مسار المعدة', 'Gastric Bypass'],
        ['ربط المعدة', 'Gastric Banding'],
        ['بالون المعدة', 'Gastric Balloon'],
    ],

    // الوجه والفكين والأسنان
    'maxillofacial' => [
        ['جراحة الفكين', 'Orthognathic Surgery'],
        ['علاج كسور الوجه', 'Facial Fracture Repair'],
        ['خلع ضرس العقل المنحشر', 'Impacted Wisdom Tooth Removal'],
        ['زراعة الأسنان', 'Dental Implant Surgery'],
        ['استئصال أورام الفم', 'Oral Tumor Surgery'],
        ['جراحة المفصل الفكي الصدغي', 'TMJ Surgery'],
    ],

    // مناظير
    'endoscopy' => [
        ['منظار المعدة', 'Gastroscopy'],
        ['منظار القولون', 'Colonoscopy'],
        ['منظار المسالك البولية', 'Cystoscopy'],
        ['منظار المفاصل', 'Arthroscopy'],
        ['منظار الرحم', 'Hysteroscopy'],
        ['منظار الجيوب الأنفية', 'Sinus Endoscopy'],
        ['منظار القصبات الهوائية', 'Bronchoscopy'],
        ['منظار البطن التشخيصي', 'Diagnostic Laparoscopy'],
        ['منظار الحالب', 'Ureteroscopy'],
        ['منظار الحنجرة', 'Laryngoscopy'],
        ['منظار القنوات الصفراوية (ERCP)', 'ERCP'],
    ],

    // إجراءات علاجية
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
        ['خزعة موجهة بالأشعة', 'Image-guided Biopsy'],
        ['نقل دم', 'Blood Transfusion'],
    ],

];
