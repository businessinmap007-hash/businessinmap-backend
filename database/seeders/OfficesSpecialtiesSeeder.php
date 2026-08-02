<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Specialty option pools for the office professions (root 19 «مكاتب») —
 * requested 2026-08-02. Same axis logic as the medical specialties: what a law
 * or engineering office SPECIALIZES in is descriptive and multi-select, one
 * group per profession under its own name; the priced/booked thing stays the
 * business_consulting item types (موعد استشارة، جلسة أونلاين، معاينة بالموقع…).
 *
 *   php artisan db:seed --class=OfficesSpecialtiesSeeder
 *
 * Idempotent: groups keyed by name_ar, options by (group, name_ar), links by
 * (child, option). Nothing is ever deleted.
 */
class OfficesSpecialtiesSeeder extends Seeder
{
    /** child name_ar => [group name_ar, group name_en, [ar => en options]] */
    private const POOLS = [
        'محاماه' => ['تخصصات المحاماة', 'Law Specialties', [
            'جنائي' => 'Criminal',
            'مدني' => 'Civil',
            'أحوال شخصية وأسرة' => 'Family & Personal Status',
            'تجاري وشركات' => 'Commercial & Corporate',
            'عمالي' => 'Labor',
            'عقاري' => 'Real Estate',
            'ضرائب' => 'Tax',
            'إداري' => 'Administrative',
            'جنح ومرور' => 'Misdemeanors & Traffic',
            'استئناف ونقض' => 'Appeals & Cassation',
            'تحكيم' => 'Arbitration',
            'صياغة عقود' => 'Contract Drafting',
        ]],
        'هندسية' => ['تخصصات الهندسة', 'Engineering Specialties', [
            'معماري' => 'Architectural',
            'إنشائي / مدني' => 'Structural / Civil',
            'كهرباء' => 'Electrical',
            'ميكانيكا' => 'Mechanical',
            'اتصالات' => 'Telecommunications',
            'مساحة' => 'Surveying',
            'جيوتقنية وتربة' => 'Geotechnical',
            'تخطيط عمراني' => 'Urban Planning',
            'تصميم داخلي' => 'Interior Design',
            'إشراف على التنفيذ' => 'Construction Supervision',
            'استشارات إنشائية' => 'Structural Consulting',
        ]],
        'محاسبة' => ['تخصصات المحاسبة', 'Accounting Specialties', [
            'ضرائب' => 'Taxation',
            'مراجعة وتدقيق' => 'Audit',
            'قوائم مالية' => 'Financial Statements',
            'تأسيس شركات' => 'Company Formation',
            'تأمينات اجتماعية' => 'Social Insurance',
            'خبرة قضائية' => 'Forensic Accounting',
            'إدارة رواتب' => 'Payroll',
        ]],
        'دعاية وإعلان' => ['تخصصات الدعاية والإعلان', 'Advertising Specialties', [
            'تسويق رقمي وسوشيال ميديا' => 'Digital & Social Media',
            'إعلانات ممولة' => 'Paid Ads',
            'تصميم جرافيك' => 'Graphic Design',
            'هوية بصرية' => 'Branding',
            'تصوير وإنتاج' => 'Photography & Production',
            'لافتات وإعلانات طرق' => 'Signage & Outdoor',
            'مطبوعات دعائية' => 'Promotional Print',
        ]],
        'ديكور' => ['تخصصات الديكور', 'Decor Specialties', [
            'تصميم داخلي' => 'Interior Design',
            'تشطيبات متكاملة' => 'Full Finishing',
            'جبس وأسقف' => 'Gypsum & Ceilings',
            'دهانات وورق حائط' => 'Paint & Wallpaper',
            'لاندسكيب' => 'Landscape',
            'ديكورات محلات ومعارض' => 'Commercial Fit-out',
        ]],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            foreach (self::POOLS as $childName => [$groupAr, $groupEn, $options]) {
                $childId = DB::table('category_parent_child as pc')
                    ->join('category_children_master as ch', 'ch.id', '=', 'pc.child_id')
                    ->where('pc.parent_id', 19)->where('ch.name_ar', $childName)
                    ->value('ch.id');

                if (! $childId) {
                    $this->command?->warn("  ! «{$childName}» غير موجود تحت مكاتب — تخُطّي.");
                    continue;
                }

                $groupId = DB::table('option_groups')->where('name_ar', $groupAr)->value('id')
                    ?: DB::table('option_groups')->insertGetId([
                        'name_ar' => $groupAr, 'name_en' => $groupEn,
                        'reorder' => 1 + (int) DB::table('option_groups')->max('reorder'),
                        'is_active' => 1,
                    ]);

                $order = 0;

                foreach ($options as $ar => $en) {
                    $optionId = DB::table('options')->where('group_id', $groupId)->where('name_ar', $ar)->value('id');

                    if (! $optionId) {
                        // options.name_en is GLOBALLY unique — an «Electrical»
                        // in another group blocks ours, so suffix on collision.
                        if (DB::table('options')->where('name_en', $en)->exists()) {
                            $en .= ' (' . $groupEn . ')';
                        }

                        $optionId = DB::table('options')->insertGetId(['group_id' => $groupId, 'name_ar' => $ar, 'name_en' => $en]);
                    }

                    DB::table('category_child_option')->updateOrInsert(
                        ['child_id' => (int) $childId, 'option_id' => (int) $optionId],
                        ['reorder' => ++$order]
                    );
                }

                $this->command?->line("  {$childName} ← «{$groupAr}» (" . count($options) . ')');
            }
        });
    }
}
