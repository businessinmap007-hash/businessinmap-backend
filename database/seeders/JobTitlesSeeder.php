<?php

namespace Database\Seeders;

use App\Models\CategoryChild;
use App\Models\JobTitle;
use Illuminate\Database\Seeder;

/**
 * The starter list of job titles. Add-only and idempotent (keyed on scope +
 * name_ar), so an owner's edits and additions in the admin screen survive a
 * re-run. A child that no longer exists by that name is skipped, not guessed.
 *
 * Scopes: 'general' fits any business; 'children' are looked up by name and
 * apply to that trade alone.
 */
class JobTitlesSeeder extends Seeder
{
    private const GENERAL = [
        ['محاسب', 'Accountant'],
        ['سائق', 'Driver'],
        ['عامل نظافة', 'Cleaner'],
        ['حارس أمن', 'Security guard'],
        ['مندوب مبيعات', 'Sales representative'],
        ['مندوب توصيل', 'Delivery courier'],
        ['خدمة عملاء', 'Customer service'],
        ['سكرتير / سكرتيرة', 'Secretary'],
        ['مدير فرع', 'Branch manager'],
        ['مصمم جرافيك', 'Graphic designer'],
        ['مسؤول سوشيال ميديا', 'Social media specialist'],
        ['أمين مخزن', 'Storekeeper'],
        ['عامل', 'Laborer'],
    ];

    /** child name_ar => [[name_ar, name_en], ...] */
    private const BY_CHILD = [
        'مطعم' => [['شيف', 'Chef'], ['طباخ', 'Cook'], ['مساعد طباخ', 'Kitchen assistant'], ['ويتر', 'Waiter'], ['كاشير', 'Cashier'], ['عامل مطبخ', 'Kitchen hand'], ['عامل تحضير', 'Prep cook'], ['مدير مطعم', 'Restaurant manager'], ['عامل توصيل', 'Delivery rider']],
        'مطعم وكافيه' => [['شيف', 'Chef'], ['طباخ', 'Cook'], ['ويتر', 'Waiter'], ['باريستا', 'Barista'], ['كاشير', 'Cashier'], ['عامل مطبخ', 'Kitchen hand']],
        'كافيه' => [['باريستا', 'Barista'], ['ويتر', 'Waiter'], ['كاشير', 'Cashier'], ['عامل مشروبات', 'Beverage maker'], ['عامل حلويات', 'Pastry hand'], ['مدير كافيه', 'Cafe manager']],
        'مجمع مطاعم' => [['ويتر', 'Waiter'], ['كاشير', 'Cashier'], ['مشرف صالة', 'Floor supervisor'], ['شيف', 'Chef']],
        'أكل بيتى' => [['طباخة', 'Home cook'], ['مساعد طباخ', 'Kitchen assistant'], ['عامل تغليف', 'Packer']],
        'عربية قهوة ومأكولات' => [['عامل عربية', 'Cart attendant'], ['كاشير', 'Cashier']],

        'عيادة' => [['طبيب', 'Doctor'], ['ممرض / ممرضة', 'Nurse'], ['موظف استقبال', 'Receptionist'], ['فني أشعة', 'Radiology technician'], ['مساعد طبيب', 'Doctor assistant']],
        'مستشفى' => [['طبيب', 'Doctor'], ['ممرض / ممرضة', 'Nurse'], ['فني أشعة', 'Radiology technician'], ['فني معمل', 'Lab technician'], ['صيدلي', 'Pharmacist'], ['موظف استقبال', 'Receptionist']],
        'مركز طبي' => [['طبيب', 'Doctor'], ['ممرض / ممرضة', 'Nurse'], ['موظف استقبال', 'Receptionist'], ['فني أشعة', 'Radiology technician']],
        'معمل تحاليل' => [['فني معمل', 'Lab technician'], ['موظف سحب عينات', 'Phlebotomist'], ['موظف استقبال', 'Receptionist']],
        'مراكز أشعة' => [['فني أشعة', 'Radiology technician'], ['موظف استقبال', 'Receptionist']],
        'صيدلية' => [['صيدلي', 'Pharmacist'], ['مساعد صيدلي', 'Pharmacy assistant'], ['كاشير', 'Cashier'], ['عامل توصيل', 'Delivery rider']],

        'فندق' => [['موظف استقبال', 'Receptionist'], ['عامل غرف', 'Housekeeper'], ['بيل بوي', 'Bellboy'], ['ويتر', 'Waiter'], ['شيف', 'Chef'], ['مدير فندق', 'Hotel manager']],
        'شقق فندقية' => [['موظف استقبال', 'Receptionist'], ['عامل غرف', 'Housekeeper']],
        'منتجع' => [['موظف استقبال', 'Receptionist'], ['منقذ سباحة', 'Lifeguard'], ['ويتر', 'Waiter'], ['منسق أنشطة', 'Activities coordinator']],

        'جيم' => [['مدرب', 'Trainer'], ['موظف استقبال', 'Receptionist'], ['عامل نظافة', 'Cleaner']],
        'نادي رياضي' => [['مدرب', 'Trainer'], ['منقذ سباحة', 'Lifeguard'], ['موظف استقبال', 'Receptionist']],
        'أكاديمية رياضية' => [['مدرب', 'Trainer'], ['مدرب حراس مرمى', 'Goalkeeper coach'], ['منسق', 'Coordinator']],
        'حمام سباحة' => [['منقذ سباحة', 'Lifeguard'], ['مدرب سباحة', 'Swimming coach']],

        'ورشة سيارات' => [['ميكانيكي', 'Mechanic'], ['كهربائي سيارات', 'Auto electrician'], ['سمكري', 'Body repairer'], ['دهان سيارات', 'Auto painter'], ['مساعد فني', 'Technician assistant']],
        'مغسلة سيارات' => [['عامل غسيل', 'Car washer'], ['عامل تلميع', 'Polisher']],
        'معرض سيارات' => [['مندوب مبيعات', 'Sales representative'], ['مسؤول معرض', 'Showroom manager']],

        'سنتر دروس' => [['مدرس', 'Teacher'], ['موظف استقبال', 'Receptionist'], ['مسؤول شؤون طلاب', 'Student affairs officer']],
        'حضانات' => [['مربية', 'Nursery teacher'], ['مشرفة', 'Supervisor'], ['عاملة نظافة', 'Cleaner']],
        'مركز تدريب' => [['مدرب', 'Trainer'], ['منسق تدريب', 'Training coordinator']],

        'برمجة' => [['مبرمج', 'Developer'], ['مصمم UI/UX', 'UI/UX designer'], ['مختبر برمجيات', 'QA tester'], ['مدير مشروع', 'Project manager']],
        'إتصالات' => [['فني شبكات', 'Network technician'], ['مهندس اتصالات', 'Telecom engineer']],

        'مكتب عقاري' => [['سمسار', 'Broker'], ['مسؤول مبيعات عقارات', 'Property sales officer']],
        'قاعة مناسبات' => [['منسق مناسبات', 'Event coordinator'], ['ويتر', 'Waiter'], ['عامل قاعة', 'Hall attendant']],
    ];

    /**
     * Reusable title sets for trades that share one shape. A child maps to a
     * profile key (string) below, or carries its own list in BY_CHILD.
     */
    private const PROFILES = [
        'goods' => [['بائع', 'Salesperson'], ['مندوب مبيعات', 'Sales representative'], ['أمين مخزن', 'Storekeeper'], ['مسؤول مشتريات', 'Purchasing officer'], ['عامل إنتاج', 'Production worker'], ['عامل تعبئة وتغليف', 'Packer'], ['سائق توصيل', 'Delivery driver'], ['مشرف فرع', 'Branch supervisor']],
        'shop' => [['بائع', 'Salesperson'], ['كاشير', 'Cashier'], ['أمين مخزن', 'Storekeeper'], ['مندوب مبيعات', 'Sales representative'], ['عامل توصيل', 'Delivery rider'], ['مشرف فرع', 'Branch supervisor']],
        'grocery' => [['بائع', 'Salesperson'], ['كاشير', 'Cashier'], ['عامل رفوف', 'Shelf stocker'], ['أمين مخزن', 'Storekeeper'], ['عامل توصيل', 'Delivery rider'], ['مشرف فرع', 'Branch supervisor']],
        'food' => [['عامل إنتاج', 'Production worker'], ['مشرف إنتاج', 'Production supervisor'], ['مراقب جودة', 'Quality inspector'], ['بائع', 'Salesperson'], ['أمين مخزن', 'Storekeeper'], ['مندوب مبيعات', 'Sales representative'], ['سائق توصيل', 'Delivery driver']],
        'factory' => [['عامل إنتاج', 'Production worker'], ['فني ماكينات', 'Machine operator'], ['مشرف إنتاج', 'Production supervisor'], ['مراقب جودة', 'Quality inspector'], ['فني صيانة', 'Maintenance technician'], ['عامل تعبئة وتغليف', 'Packer'], ['أمين مخزن', 'Storekeeper'], ['سائق نقل', 'Truck driver']],
        'farm' => [['عامل زراعة', 'Farm worker'], ['مشرف مزرعة', 'Farm supervisor'], ['مهندس زراعي', 'Agronomist'], ['سائق جرار', 'Tractor driver'], ['عامل تعبئة', 'Packer']],
        'livestock' => [['عامل رعاية حيوانات', 'Animal caretaker'], ['طبيب بيطري', 'Veterinarian'], ['مشرف مزرعة', 'Farm supervisor'], ['عامل تعليف', 'Feeder']],
        'contracting' => [['مهندس مدني', 'Civil engineer'], ['مهندس موقع', 'Site engineer'], ['مشرف بناء', 'Construction supervisor'], ['مساح', 'Surveyor'], ['فورمان', 'Foreman'], ['عامل بناء', 'Builder'], ['سائق معدات ثقيلة', 'Heavy equipment operator'], ['مسؤول سلامة', 'Safety officer']],
        'entertainment' => [['موظف استقبال', 'Receptionist'], ['مشرف صالة', 'Floor supervisor'], ['عامل', 'Attendant'], ['كاشير', 'Cashier'], ['فني صيانة', 'Maintenance technician']],
        'office' => [['موظف إداري', 'Administrative clerk'], ['سكرتير / سكرتيرة', 'Secretary'], ['موظف استقبال', 'Receptionist'], ['مندوب', 'Field representative']],
    ];

    /** child name_ar => profile key | explicit [[ar, en], ...] */
    private const COVERAGE = [
        // Goods sold or made (the same child is shared by shops, companies and factories).
        'اكسسوار' => 'goods', 'لوازم ستائر' => 'goods', 'ألمونتال' => 'goods', 'أنتيكات وتحف' => 'shop', 'أجهزة رياضية' => 'goods',
        'كتب' => 'shop', 'طوب' => 'factory', 'زيت سيارات' => 'shop', 'قطع غيار سيارات' => 'goods', 'كرڤان' => 'factory',
        'باب وشباك' => 'factory', 'مستلزمات نجارة' => 'goods', 'سجاد' => 'goods', 'اسمنت' => 'factory', 'نجف' => 'goods', 'نجف و تحف' => 'shop',
        'ملابس' => 'shop', 'ملابس جاهزة' => 'goods', 'بن' => 'shop', 'أجهزه كمبيوتر' => 'goods', 'أدوات تجميل' => 'goods', 'ستائر و ديكور' => 'shop',
        'منظفات' => 'goods', 'أدوات كهربائية' => 'shop', 'أجهزة كهربائية' => 'goods', 'أقمشة' => 'goods', 'مواد غذائية' => 'food', 'مواد غذائية ومنظفات' => 'food',
        'مجمدات' => 'grocery', 'خضار وفاكهة' => 'grocery', 'مفروشات' => 'goods', 'آثاث' => 'goods', 'نظارات' => 'shop', 'زجاج' => 'goods',
        'سيراميك وأدوات صحية' => 'goods', 'كبس خراطيم' => 'factory', 'أدوات صيد' => 'shop', 'عصائر' => 'food', 'مفاتيح' => 'shop', 'جلود وشنط وأحذية' => 'goods',
        'رخام' => 'goods', 'رخام وجرانيت' => 'factory', 'مراتب' => 'goods', 'مستلزمات طبية' => 'goods', 'موبيلات و اكسسوار' => 'shop', 'مواد تعبئة وتغليف' => 'goods',
        'حدايد وبويات' => 'goods', 'عطور' => 'shop', 'مواد دوائية' => 'factory', 'بلاستيك' => 'shop', 'صينى وخزف' => 'goods', 'طباعة مواد تعبئة وتغليف' => 'factory',
        'مستلزمات مطاعم وكافيهات' => 'goods', 'سيفتى ومقاومة حرائق' => 'goods', 'فضة' => 'shop', 'مشتقات التدخين' => 'shop', 'حديد تسليح' => 'goods',
        'قطع غيار' => 'goods', 'قطع غيار أجهزة كهربائية' => 'shop', 'إسفنج' => 'goods', 'أدوات مكتبية' => 'shop', 'مكملات غذائية' => 'shop', 'لعب أطفال' => 'goods',
        'أخشاب' => 'goods', 'مصنوعات خشبية ومستلزمات ديكور' => 'shop', 'أصواف' => 'goods', 'كابلات وقواطع كهرباء' => 'factory', 'معدات سوبرماركت' => 'goods',
        'أجهزة بلايستيشن' => 'shop', 'جنوط وكاوتش سيارات' => 'shop', 'نباتات طبيعية وزينة' => 'shop', 'استيراد وتصدير' => 'goods',
        'ذهب' => [['بائع', 'Salesperson'], ['صائغ', 'Goldsmith'], ['مصمم مجوهرات', 'Jewelry designer'], ['كاشير', 'Cashier']],
        'حلويات' => [['حلواني', 'Pastry chef'], ['عامل حلويات', 'Pastry hand'], ['بائع', 'Salesperson'], ['كاشير', 'Cashier'], ['عامل تغليف', 'Packer']],
        'مخابز' => [['خباز', 'Baker'], ['عامل عجين', 'Dough hand'], ['بائع', 'Salesperson'], ['كاشير', 'Cashier'], ['عامل فرن', 'Oven hand']],
        'أسماك' => [['بائع أسماك', 'Fishmonger'], ['عامل تنظيف أسماك', 'Fish cleaner'], ['كاشير', 'Cashier'], ['سائق توصيل', 'Delivery driver']],
        'جزارة' => [['جزار', 'Butcher'], ['مساعد جزار', 'Butcher assistant'], ['كاشير', 'Cashier'], ['عامل تقطيع وتغليف', 'Cutter and packer']],
        'دواجن' => [['عامل دواجن', 'Poultry worker'], ['بائع دواجن', 'Poultry seller'], ['عامل ذبح وتنظيف', 'Slaughter and cleaning hand'], ['كاشير', 'Cashier']],
        'هايبر ماركت' => 'grocery', 'سوبر ماركت' => 'grocery', 'مني ماركت' => 'grocery',

        // Farming.
        'معدات زراعية' => 'goods', 'تقاوي وأسمدة ومبيدات' => 'goods', 'مزارع سمكية' => [['عامل مزرعة سمكية', 'Fish farm worker'], ['فني تشغيل', 'Operations technician'], ['مشرف مزرعة', 'Farm supervisor']],
        'أعلاف' => 'factory', 'حبوب وغلال' => 'farm', 'مواشي وأرانب' => 'livestock', 'معدات وتجهيزات المزارع' => 'goods',

        // Trades and craftsmen (مهن وحرفيين).
        'فني تبريد وتكييف' => [['فني تبريد وتكييف', 'HVAC technician'], ['مساعد فني', 'Technician assistant']],
        'فني الوميتال' => [['فني ألوميتال', 'Aluminium fabricator'], ['مساعد فني', 'Technician assistant']],
        'فني صيانة أجهزة منزلية' => [['فني صيانة أجهزة', 'Appliance technician'], ['مساعد فني', 'Technician assistant']],
        'نجار تنده' => [['نجار', 'Carpenter'], ['مساعد نجار', 'Carpenter assistant']],
        'نجار موبيليا' => [['نجار موبيليا', 'Furniture carpenter'], ['مساعد نجار', 'Carpenter assistant'], ['دهان أثاث', 'Furniture painter']],
        'خدمات نظافة' => [['عامل نظافة', 'Cleaner'], ['مشرف نظافة', 'Cleaning supervisor'], ['عاملة نظافة', 'Female cleaner']],
        'فني ستائر و تنجيد' => [['فني ستائر', 'Curtain installer'], ['منجد', 'Upholsterer']],
        'تكسير ونحت' => [['عامل تكسير', 'Demolition worker'], ['نحات', 'Sculptor']],
        'كهربائي' => [['كهربائي', 'Electrician'], ['مساعد كهربائي', 'Electrician assistant'], ['كهربائي تمديدات', 'Wiring electrician']],
        'مبلط' => [['مبلط', 'Tiler'], ['مساعد مبلط', 'Tiler assistant']],
        'جي أر سي' => [['فني جي أر سي', 'GRC fabricator'], ['مساعد فني', 'Technician assistant']],
        'جبس وجبسيوم بورد' => [['فني جبس', 'Gypsum installer'], ['مساعد فني', 'Technician assistant']],
        'متخصص كوافير' => [['كوافير', 'Hairdresser'], ['مساعد كوافير', 'Hairdresser assistant']],
        'عامل بناء' => [['عامل بناء', 'Builder'], ['بناء', 'Mason'], ['مساعد بناء', 'Builder assistant']],
        'بناء وواجهات حجرية' => [['بناء واجهات حجرية', 'Stone facade mason'], ['مساعد بناء', 'Builder assistant']],
        'نقاش' => [['نقاش', 'Painter'], ['مساعد نقاش', 'Painter assistant']],
        'باركيه' => [['فني باركيه', 'Parquet installer'], ['مساعد فني', 'Technician assistant']],
        'مبيض محارة' => [['مبيض محارة', 'Plasterer'], ['مساعد مبيض', 'Plasterer assistant']],
        'سباك' => [['سباك', 'Plumber'], ['مساعد سباك', 'Plumber assistant']],
        'دش وأقمار صناعية' => [['فني دش', 'Satellite technician'], ['مساعد فني', 'Technician assistant']],
        'حداد' => [['حداد', 'Blacksmith'], ['مساعد حداد', 'Blacksmith assistant']],
        'منجد' => [['منجد', 'Upholsterer'], ['مساعد منجد', 'Upholsterer assistant']],
        'أويمجى' => [['أويمجى', 'Ornamental engraver'], ['مساعد فني', 'Technician assistant']],
        'استرجي' => [['استرجي', 'Veneer craftsman'], ['مساعد فني', 'Technician assistant']],

        // Workshops and service centres.
        'مركز خدمة متكامل' => [['ميكانيكي', 'Mechanic'], ['فني كهرباء سيارات', 'Auto electrician'], ['موظف استقبال', 'Receptionist'], ['مساعد فني', 'Technician assistant']],
        'ورشة باب وشباك' => [['فني ألوميتال', 'Aluminium fabricator'], ['نجار', 'Carpenter'], ['مساعد فني', 'Technician assistant']],
        'تبريد وتكييف' => [['فني تبريد وتكييف', 'HVAC technician'], ['مساعد فني', 'Technician assistant']],
        'ورشة أثاث ونجارة' => [['نجار', 'Carpenter'], ['دهان أثاث', 'Furniture painter'], ['مساعد نجار', 'Carpenter assistant']],
        'ورشة حدادة وخراطة' => [['حداد', 'Blacksmith'], ['خراط', 'Lathe operator'], ['لحام', 'Welder'], ['مساعد فني', 'Technician assistant']],
        'ورشة صيانة أجهزة' => [['فني صيانة أجهزة', 'Appliance technician'], ['فني إلكترونيات', 'Electronics technician'], ['مساعد فني', 'Technician assistant']],

        // Offices and professional practices.
        'محاسبة' => [['محاسب', 'Accountant'], ['مراجع حسابات', 'Auditor'], ['مساعد محاسب', 'Junior accountant'], ['سكرتير / سكرتيرة', 'Secretary']],
        'دعاية وإعلان وإدارة صفحات' => [['مصمم جرافيك', 'Graphic designer'], ['مسؤول سوشيال ميديا', 'Social media specialist'], ['كاتب محتوى', 'Content writer'], ['مصور', 'Photographer'], ['مونتير', 'Video editor'], ['مسؤول إعلانات ممولة', 'Paid ads specialist']],
        'منطقة عمل مشتركة' => [['مدير مساحة عمل', 'Space manager'], ['موظف استقبال', 'Receptionist'], ['عامل نظافة', 'Cleaner']],
        'شركة' => 'office', 'مكتب' => 'office',
        'تنسيق حفلات' => [['منسق حفلات', 'Event planner'], ['منسق ورود وديكور', 'Decor coordinator'], ['ويتر', 'Waiter'], ['فني صوت وإضاءة', 'Sound and lighting technician']],
        'مقاولات' => 'contracting', 'مقاولات بنية تحتية' => 'contracting',
        'تخليص جمركي' => [['مخلص جمركي', 'Customs clearing agent'], ['مندوب تخليص', 'Clearance representative'], ['موظف إداري', 'Administrative clerk']],
        'ديكور' => [['مصمم ديكور', 'Interior designer'], ['مهندس ديكور', 'Decor engineer'], ['رسام ثلاثي الأبعاد', '3D visualizer'], ['فني تنفيذ', 'Installation technician']],
        'هندسية' => [['مهندس مدني', 'Civil engineer'], ['مهندس معماري', 'Architect'], ['رسام أوتوكاد', 'CAD draftsman'], ['مساح', 'Surveyor']],
        'خدمات منزلية' => [['عاملة منزلية', 'Domestic worker'], ['جليسة أطفال', 'Babysitter'], ['مقدم رعاية مسنين', 'Elderly caregiver'], ['طباخة', 'Cook']],
        'محاماه' => [['محامي', 'Lawyer'], ['مساعد قانوني', 'Legal assistant'], ['سكرتير / سكرتيرة', 'Secretary'], ['مندوب محاكم', 'Court runner']],
        'مأذون شرعى' => [['مأذون', 'Marriage officiant'], ['كاتب', 'Clerk']],
        'أمن' => [['حارس أمن', 'Security guard'], ['مشرف أمن', 'Security supervisor'], ['مشغل كاميرات مراقبة', 'CCTV operator']],
        'أمن وسلامة' => [['فني أنظمة إنذار وحريق', 'Alarm and fire technician'], ['فني كاميرات مراقبة', 'CCTV technician'], ['مسؤول سلامة', 'Safety officer']],
        'برمجيات' => [['مبرمج', 'Developer'], ['مصمم UI/UX', 'UI/UX designer'], ['مختبر برمجيات', 'QA tester'], ['مدير مشروع', 'Project manager'], ['دعم فني', 'Technical support']],
        'تسويق' => [['مسؤول تسويق', 'Marketing specialist'], ['مسؤول سوشيال ميديا', 'Social media specialist'], ['مصمم جرافيك', 'Graphic designer'], ['كاتب محتوى', 'Content writer'], ['مندوب مبيعات', 'Sales representative']],
        'شركات تأمين' => [['مندوب تأمين', 'Insurance agent'], ['موظف مطالبات', 'Claims officer'], ['خدمة عملاء', 'Customer service']],
        'صرافة وتحويل أموال' => [['صراف', 'Teller'], ['كاشير', 'Cashier'], ['موظف امتثال', 'Compliance officer']],
        'طباعة' => [['عامل طباعة', 'Print operator'], ['مصمم جرافيك', 'Graphic designer'], ['عامل تجليد وقص', 'Binding and cutting hand'], ['مندوب مبيعات', 'Sales representative']],
        'سياحة' => [['مرشد سياحي', 'Tour guide'], ['موظف حجوزات', 'Reservations agent'], ['منسق رحلات', 'Trip coordinator'], ['مندوب مبيعات', 'Sales representative']],
        'مصاعد وسلم كهرياء' => [['فني مصاعد', 'Elevator technician'], ['مساعد فني', 'Technician assistant'], ['مهندس تركيب', 'Installation engineer']],
        'معدات ثقيلة' => [['سائق معدات ثقيلة', 'Heavy equipment operator'], ['ميكانيكي معدات', 'Equipment mechanic'], ['مشرف معدات', 'Equipment supervisor']],

        // Transport and logistics.
        'سائق' => [['سائق', 'Driver'], ['سائق خاص', 'Private driver'], ['سائق نقل', 'Truck driver']],
        'جراج' => [['عامل جراج', 'Garage attendant'], ['مشرف جراج', 'Garage supervisor'], ['كاشير', 'Cashier']],
        'خدمة ليموزين' => [['سائق ليموزين', 'Limousine driver'], ['منسق رحلات', 'Trip dispatcher']],
        'ونش إنقاذ' => [['سائق ونش', 'Tow truck driver'], ['فني إنقاذ', 'Recovery technician']],
        'نقل ركاب' => [['سائق ميكروباص', 'Minibus driver'], ['سائق أتوبيس', 'Bus driver'], ['محصل', 'Fare collector']],
        'سيارات نقل' => [['سائق نقل', 'Truck driver'], ['مساعد سائق', 'Driver assistant'], ['عامل تحميل', 'Loader']],
        'سيارة من المالك' => [['سائق', 'Driver']],
        'مندوب' => [['مندوب توصيل', 'Delivery courier'], ['مندوب استلام', 'Pickup representative'], ['موزع', 'Distributor']],
        'معرض موتوسيكلات' => [['مندوب مبيعات', 'Sales representative'], ['ميكانيكي دراجات', 'Motorbike mechanic'], ['كاشير', 'Cashier']],

        // Beauty.
        'كوافير حريمى' => [['كوافيرة', 'Hairdresser'], ['مساعدة كوافير', 'Hairdresser assistant'], ['خبيرة مكياج', 'Makeup artist'], ['مانيكير وباديكير', 'Nail technician'], ['موظفة استقبال', 'Receptionist']],
        'كوافير رجالي' => [['حلاق', 'Barber'], ['مساعد حلاق', 'Barber assistant'], ['موظف استقبال', 'Receptionist']],

        // Entertainment and sport.
        'بلياردو وبينج بونج' => 'entertainment', 'بولينج' => 'entertainment', 'مركز ترفيهي' => 'entertainment', 'بلاي ستيشن' => 'entertainment',
        'اكوا بارك' => [['منقذ سباحة', 'Lifeguard'], ['موظف استقبال', 'Receptionist'], ['فني صيانة', 'Maintenance technician'], ['مشرف ألعاب', 'Rides supervisor']],
        'صالة ألعاب' => 'entertainment', 'منطقة أطفال' => [['مشرفة أطفال', 'Kids supervisor'], ['موظف استقبال', 'Receptionist'], ['عامل نظافة', 'Cleaner']],
        'رحلات ومراكب' => [['قبطان', 'Captain'], ['بحار', 'Deckhand'], ['مرشد سياحي', 'Tour guide'], ['موظف حجوزات', 'Reservations agent']],
        'فوتوجرافر' => [['مصور', 'Photographer'], ['مساعد مصور', 'Photographer assistant'], ['مونتير', 'Video editor']],
        'استوديوهات' => [['مصور', 'Photographer'], ['مونتير', 'Video editor'], ['فني صوت', 'Sound technician'], ['موظف استقبال', 'Receptionist']],
        'ملاعب كرة' => [['مشرف ملعب', 'Pitch supervisor'], ['موظف استقبال', 'Receptionist'], ['عامل ملعب', 'Groundskeeper'], ['حكم', 'Referee']],
        'مدرب' => [['مدرب', 'Coach'], ['مدرب لياقة', 'Fitness coach'], ['مدرب مساعد', 'Assistant coach']],
        'مستكشف لاعبين' => [['كشاف لاعبين', 'Talent scout'], ['محلل أداء', 'Performance analyst']],
        'ناشئ موهوب' => [['مدرب', 'Coach']],

        // Real estate, halls, hospitality, health, training.
        'مطور عقاري' => [['مهندس موقع', 'Site engineer'], ['مسؤول مبيعات عقارات', 'Property sales officer'], ['مدير مشروع', 'Project manager'], ['محاسب', 'Accountant']],
        'مالك عقار' => [['حارس عقار', 'Building doorman'], ['مسؤول تحصيل', 'Rent collector'], ['فني صيانة', 'Maintenance technician']],
        'مركز مؤتمرات واجتماعات' => [['منسق فعاليات', 'Event coordinator'], ['موظف استقبال', 'Receptionist'], ['فني صوت وإضاءة', 'Sound and lighting technician'], ['ويتر', 'Waiter']],
        'قاعات تدريب' => [['منسق قاعات', 'Hall coordinator'], ['موظف استقبال', 'Receptionist'], ['عامل نظافة', 'Cleaner']],
        'نُزل / هوستل' => [['موظف استقبال', 'Receptionist'], ['عامل غرف', 'Housekeeper'], ['مدير هوستل', 'Hostel manager']],
        'بيت ضيافة' => [['موظف استقبال', 'Receptionist'], ['عامل غرف', 'Housekeeper'], ['طباخ', 'Cook']],
        'فندق عائم / بوت نيلي' => [['موظف استقبال', 'Receptionist'], ['عامل غرف', 'Housekeeper'], ['ويتر', 'Waiter'], ['بحار', 'Deckhand'], ['شيف', 'Chef']],
        'مالك وحدة مصيفية' => [['عامل نظافة', 'Cleaner'], ['حارس', 'Guard'], ['فني صيانة', 'Maintenance technician']],
        'مركز حجامة' => [['حجّام', 'Cupping therapist'], ['ممرض / ممرضة', 'Nurse'], ['موظف استقبال', 'Receptionist']],
    ];

    public function run(): void
    {
        foreach (self::GENERAL as $i => [$ar, $en]) {
            JobTitle::firstOrCreate(
                ['category_id' => null, 'category_child_id' => null, 'name_ar' => $ar],
                ['name_en' => $en, 'sort_order' => 1000 + $i],
            );
        }

        $byChild = self::BY_CHILD;
        foreach (self::COVERAGE as $childName => $spec) {
            $byChild[$childName] ??= is_string($spec) ? self::PROFILES[$spec] : $spec;
        }

        foreach ($byChild as $childName => $titles) {
            $children = CategoryChild::query()->where('name_ar', $childName)->get();

            foreach ($children as $child) {
                foreach ($titles as $i => [$ar, $en]) {
                    JobTitle::firstOrCreate(
                        ['category_child_id' => $child->id, 'name_ar' => $ar],
                        ['name_en' => $en, 'sort_order' => $i],
                    );
                }
            }
        }
    }
}
