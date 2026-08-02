<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The tutoring-center pools (owner request 2026-08-02): سنتر دروس gets every
 * pre-university school subject in its own group, plus the educational stages
 * as a SECOND multi-select group — options rather than children, by the same
 * rule that put hospital specialties on this axis: one center serves ابتدائي
 * and إعدادي and ثانوي at once, and a stage-child would force it to pick one.
 * The priced thing stays the training session types (حصة خاصة، محاضرة جماعية…).
 *
 *   php artisan db:seed --class=TutoringCenterPoolsSeeder
 *
 * Language subjects carry a "… Language" English name because options.name_en
 * is globally unique and مجالات التدريب already owns English/German/French….
 * Idempotent; collision-suffixes instead of failing.
 */
class TutoringCenterPoolsSeeder extends Seeder
{
    private const CHILD_NAME = 'سنتر دروس';
    private const ROOT_ID = 12;

    private const POOLS = [
        ['المواد الدراسية', 'School Subjects', [
            'لغة عربية' => 'Arabic Language',
            'لغة إنجليزية' => 'English Language',
            'لغة فرنسية' => 'French Language',
            'لغة ألمانية' => 'German Language',
            'لغة إيطالية' => 'Italian Language',
            'لغة إسبانية' => 'Spanish Language',
            'رياضيات' => 'Mathematics',
            'فيزياء' => 'Physics',
            'كيمياء' => 'Chemistry',
            'أحياء' => 'Biology',
            'جيولوجيا' => 'Geology',
            'علوم' => 'Science',
            'علوم متكاملة' => 'Integrated Science',
            'دراسات اجتماعية' => 'Social Studies',
            'تاريخ' => 'History',
            'جغرافيا' => 'Geography',
            'فلسفة ومنطق' => 'Philosophy & Logic',
            'علم نفس واجتماع' => 'Psychology & Sociology',
            'اقتصاد وإحصاء' => 'Economics & Statistics',
            'تربية دينية' => 'Religious Education',
            'حاسب آلي' => 'Computer Studies',
        ]],
        ['المراحل التعليمية', 'Educational Stages', [
            'رياض أطفال' => 'Kindergarten',
            'ابتدائي' => 'Primary',
            'إعدادي' => 'Preparatory',
            'ثانوي عام' => 'General Secondary',
            'ثانوي أزهري' => 'Azhari Secondary',
            'دبلومات فنية' => 'Technical Diplomas',
        ]],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $childId = DB::table('category_parent_child as pc')
                ->join('category_children_master as ch', 'ch.id', '=', 'pc.child_id')
                ->where('pc.parent_id', self::ROOT_ID)->where('ch.name_ar', self::CHILD_NAME)
                ->value('ch.id');

            if (! $childId) {
                $this->command?->warn('سنتر دروس not found — nothing done.');

                return;
            }

            foreach (self::POOLS as [$groupAr, $groupEn, $options]) {
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

                $this->command?->line("  «{$groupAr}» (" . count($options) . ') ← سنتر دروس');
            }
        });
    }
}
