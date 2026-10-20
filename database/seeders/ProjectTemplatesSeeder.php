<?php

namespace Database\Seeders;

use App\Models\ProjectTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Ready stage plans for the trades that run a job in phases (owner, 2026-10-20). Idempotent: a template is found by `code`;
 * its stages are rewritten only when the template has none yet, so a stage list an admin trimmed later is not put back.
 *
 * Stage shape: [Arabic title, English title, days, needs the customer's approval].
 * `children` are category-child ids (the trade); an empty list is a GENERAL plan offered to every business with the service.
 */
class ProjectTemplatesSeeder extends Seeder
{
    /** @return list<array<string,mixed>> */
    public static function plans(): array
    {
        return [
            [
                'code' => 'apartment_finishing', 'ar' => 'تشطيب شقة', 'en' => 'Apartment finishing',
                'children' => [147, 106, 80, 133, 220, 89, 227, 179, 174, 129, 72, 152, 518, 78],
                'stages' => [
                    ['الهدم والتجهيز', 'Demolition and preparation', 3, false],
                    ['السباكة', 'Plumbing', 5, true],
                    ['التمديدات الكهربائية', 'Electrical wiring', 5, true],
                    ['المحارة', 'Plastering', 7, true],
                    ['التبليط والسيراميك', 'Tiling', 7, true],
                    ['الجبس والأسقف', 'Gypsum and ceilings', 4, false],
                    ['الدهان', 'Painting', 6, true],
                    ['التنظيف والتسليم', 'Cleaning and handover', 2, true],
                ],
            ],
            [
                'code' => 'painting', 'ar' => 'دهانات', 'en' => 'Painting',
                'children' => [206],
                'stages' => [
                    ['المعاينة والقياس', 'Survey', 1, false],
                    ['تجهيز الأسطح', 'Surface preparation', 2, false],
                    ['المعجون والصنفرة', 'Putty and sanding', 3, false],
                    ['الوجه الأول', 'First coat', 2, false],
                    ['الوجه الثاني', 'Second coat', 2, true],
                    ['التسليم', 'Handover', 1, true],
                ],
            ],
            [
                'code' => 'furniture_workshop', 'ar' => 'أثاث ونجارة', 'en' => 'Furniture and carpentry',
                'children' => [49, 26, 287, 75, 544, 116, 115, 47],
                'stages' => [
                    ['التصميم والقياسات', 'Design and measurements', 3, true],
                    ['تجهيز الخامات', 'Materials', 4, false],
                    ['التصنيع', 'Manufacturing', 10, false],
                    ['التجميع', 'Assembly', 4, false],
                    ['الدهان والتشطيب', 'Finishing', 5, true],
                    ['التسليم والتركيب', 'Delivery and installation', 2, true],
                ],
            ],
            [
                'code' => 'metal_workshop', 'ar' => 'حدادة وألمونتال', 'en' => 'Metal, aluminium and doors',
                'children' => [18, 259, 299, 84, 545, 50, 17],
                'stages' => [
                    ['القياس والتصميم', 'Measuring and design', 2, true],
                    ['التقطيع والتجهيز', 'Cutting and preparation', 3, false],
                    ['التجميع واللحام', 'Assembly and welding', 5, false],
                    ['الدهان أو الأنودة', 'Coating', 3, false],
                    ['التركيب والتسليم', 'Installation and handover', 2, true],
                ],
            ],
            [
                'code' => 'car_repair', 'ar' => 'صيانة سيارة', 'en' => 'Car repair',
                'children' => [543, 39],
                'stages' => [
                    ['الفحص وتقدير التكلفة', 'Inspection and estimate', 1, true],
                    ['السمكرة', 'Bodywork', 3, false],
                    ['الميكانيكا', 'Mechanics', 3, false],
                    ['الكهرباء', 'Electrical', 2, false],
                    ['الدهان', 'Paint', 3, true],
                    ['الغسيل والتسليم', 'Wash and handover', 1, true],
                ],
            ],
            [
                'code' => 'appliance_ac', 'ar' => 'صيانة وتركيب أجهزة وتكييف', 'en' => 'Appliances and A/C',
                'children' => [546, 240, 15],
                'stages' => [
                    ['المعاينة', 'Survey', 1, false],
                    ['توريد القطع', 'Parts', 2, false],
                    ['التنفيذ', 'Work', 2, false],
                    ['الاختبار والتسليم', 'Test and handover', 1, true],
                ],
            ],
            [
                'code' => 'engineering_office', 'ar' => 'مكتب هندسي', 'en' => 'Engineering office',
                'children' => [123],
                'stages' => [
                    ['المعاينة ورفع المقاسات', 'Survey', 3, false],
                    ['التصميم المبدئي', 'Concept design', 7, true],
                    ['الرسومات التنفيذية', 'Working drawings', 10, true],
                    ['التراخيص والموافقات', 'Permits', 14, false],
                    ['التنفيذ والإشراف', 'Execution and supervision', 30, false],
                    ['الاستلام النهائي', 'Final handover', 3, true],
                ],
            ],
            [
                'code' => 'interior_design', 'ar' => 'ديكور وتصميم داخلي', 'en' => 'Interior design',
                'children' => [78],
                'stages' => [
                    ['المعاينة', 'Survey', 1, false],
                    ['الأفكار واللوحات', 'Concept boards', 5, true],
                    ['التصميم ثلاثي الأبعاد', '3D design', 7, true],
                    ['اعتماد التصميم', 'Design sign-off', 2, true],
                    ['التنفيذ', 'Execution', 21, false],
                    ['التسليم', 'Handover', 2, true],
                ],
            ],
            [
                'code' => 'event_planning', 'ar' => 'تنسيق حفلات', 'en' => 'Event planning',
                'children' => [70],
                'stages' => [
                    ['تحديد المتطلبات', 'Requirements', 2, true],
                    ['حجز القاعة والموردين', 'Venue and suppliers', 5, false],
                    ['التصميم والديكور', 'Design and decor', 5, true],
                    ['البروفة', 'Rehearsal', 1, false],
                    ['يوم الحفل', 'Event day', 1, false],
                    ['التفكيك والتقييم', 'Teardown and review', 1, false],
                ],
            ],
            [
                'code' => 'software', 'ar' => 'مشروع برمجي', 'en' => 'Software project',
                'children' => [233, 261],
                'stages' => [
                    ['تحليل المتطلبات', 'Requirements', 5, true],
                    ['التصميم', 'Design', 7, true],
                    ['التطوير', 'Development', 20, false],
                    ['الاختبار', 'Testing', 7, false],
                    ['التسليم والتدريب', 'Delivery and training', 3, true],
                ],
            ],
            [
                'code' => 'marketing_campaign', 'ar' => 'حملة تسويق', 'en' => 'Marketing campaign',
                'children' => [177, 11],
                'stages' => [
                    ['الاستراتيجية', 'Strategy', 3, true],
                    ['إعداد المحتوى', 'Content', 7, true],
                    ['التنفيذ والنشر', 'Launch', 14, false],
                    ['المتابعة والتقرير', 'Follow-up and report', 3, true],
                ],
            ],
            [
                'code' => 'installation', 'ar' => 'توريد وتركيب', 'en' => 'Supply and installation',
                'children' => [90, 250, 254, 67],
                'stages' => [
                    ['المعاينة', 'Survey', 1, false],
                    ['التوريد', 'Supply', 5, false],
                    ['التركيب', 'Installation', 4, false],
                    ['التجربة والتشغيل', 'Testing and commissioning', 1, true],
                    ['التسليم والتدريب', 'Handover and training', 1, true],
                ],
            ],
            [
                'code' => 'general', 'ar' => 'مشروع عام', 'en' => 'General project',
                'children' => [],
                'stages' => [
                    ['التخطيط', 'Planning', 2, false],
                    ['التنفيذ', 'Execution', 7, false],
                    ['المراجعة', 'Review', 2, true],
                    ['التسليم', 'Handover', 1, true],
                ],
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::plans() as $i => $plan) {
            $template = ProjectTemplate::query()->firstOrCreate(
                ['code' => $plan['code']],
                ['title_ar' => $plan['ar'], 'title_en' => $plan['en'], 'is_active' => true, 'sort_order' => $i + 1],
            );

            if ($template->stages()->doesntExist()) {
                foreach ($plan['stages'] as $n => [$ar, $en, $days, $approval]) {
                    $template->stages()->create([
                        'sort_order' => $n + 1, 'title_ar' => $ar, 'title_en' => $en,
                        'duration_days' => $days, 'requires_photo' => true, 'requires_approval' => $approval,
                    ]);
                }
            }

            foreach ($plan['children'] as $childId) {
                DB::table('project_template_children')->updateOrInsert(['template_id' => $template->id, 'child_id' => $childId], []);
            }
        }
    }
}
