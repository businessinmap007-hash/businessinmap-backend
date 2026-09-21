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

    public function run(): void
    {
        foreach (self::GENERAL as $i => [$ar, $en]) {
            JobTitle::firstOrCreate(
                ['category_id' => null, 'category_child_id' => null, 'name_ar' => $ar],
                ['name_en' => $en, 'sort_order' => 1000 + $i],
            );
        }

        foreach (self::BY_CHILD as $childName => $titles) {
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
