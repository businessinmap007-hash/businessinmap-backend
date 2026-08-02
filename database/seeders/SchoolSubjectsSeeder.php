<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * «المواد الدراسية» — the pre-university school subjects pool (owner request
 * 2026-08-02), attached to سنتر دروس: every subject a tutoring center can
 * teach across المراحل قبل الجامعية (ابتدائي/إعدادي/ثانوي). Same axis rule as
 * every pool before it: the subject is a multi-select option; the priced thing
 * stays the training session types (حصة خاصة، محاضرة جماعية، دورة…).
 *
 *   php artisan db:seed --class=SchoolSubjectsSeeder
 *
 * Idempotent; guards the GLOBAL options.name_en unique key by suffixing on
 * collision (e.g. «إنجليزي | English» already exists in مجالات التدريب).
 */
class SchoolSubjectsSeeder extends Seeder
{
    private const GROUP = ['المواد الدراسية', 'School Subjects (Pre-University)'];

    private const SUBJECTS = [
        'لغة عربية' => 'Arabic Language',
        'لغة إنجليزية' => 'English Language',
        'لغة فرنسية' => 'French Language',
        'لغة ألمانية' => 'German Language',
        'لغة إيطالية' => 'Italian Language',
        'لغة إسبانية' => 'Spanish Language',
        'رياضيات' => 'Mathematics',
        'رياضيات بحتة' => 'Pure Mathematics',
        'رياضيات تطبيقية (استاتيكا وديناميكا)' => 'Applied Mathematics',
        'علوم' => 'Science',
        'فيزياء' => 'Physics',
        'كيمياء' => 'Chemistry',
        'أحياء' => 'Biology',
        'جيولوجيا وعلوم بيئة' => 'Geology & Environmental Science',
        'دراسات اجتماعية' => 'Social Studies',
        'تاريخ' => 'History',
        'جغرافيا' => 'Geography',
        'فلسفة ومنطق' => 'Philosophy & Logic',
        'علم نفس واجتماع' => 'Psychology & Sociology',
        'اقتصاد وإحصاء' => 'Economics & Statistics',
        'تربية دينية' => 'Religious Education',
        'حاسب آلي وتكنولوجيا معلومات' => 'Computer & IT (School)',
        'تأسيس قراءة وكتابة' => 'Literacy Foundation',
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $childId = DB::table('category_children_master')->where('name_ar', 'سنتر دروس')->value('id');

            if (! $childId) {
                $this->command?->warn('«سنتر دروس» غير موجود — لا شيء نُفّذ.');

                return;
            }

            [$groupAr, $groupEn] = self::GROUP;
            $groupId = DB::table('option_groups')->where('name_ar', $groupAr)->value('id')
                ?: DB::table('option_groups')->insertGetId([
                    'name_ar' => $groupAr, 'name_en' => $groupEn,
                    'reorder' => 1 + (int) DB::table('option_groups')->max('reorder'),
                    'is_active' => 1,
                ]);

            $order = 0;
            $added = 0;

            foreach (self::SUBJECTS as $ar => $en) {
                $optionId = DB::table('options')->where('group_id', $groupId)->where('name_ar', $ar)->value('id');

                if (! $optionId) {
                    // options.name_en is GLOBALLY unique — suffix on collision.
                    if (DB::table('options')->where('name_en', $en)->exists()) {
                        $en .= ' (School)';
                    }

                    $optionId = DB::table('options')->insertGetId(['group_id' => $groupId, 'name_ar' => $ar, 'name_en' => $en]);
                    $added++;
                }

                DB::table('category_child_option')->updateOrInsert(
                    ['child_id' => (int) $childId, 'option_id' => (int) $optionId],
                    ['reorder' => ++$order]
                );
            }

            $this->command?->info("سنتر دروس ← «{$groupAr}»: " . count(self::SUBJECTS) . " مادة ({$added} جديدة).");
        });
    }
}
