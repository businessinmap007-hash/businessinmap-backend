<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The stage→subject matrix (owner design 2026-08-02): each educational stage
 * becomes a CHILD that owns exactly its own subjects, so the stage's option
 * links ARE the definition of what that stage teaches. سنتر دروس keeps the
 * stages in its own options; when a center ticks «إعدادي», the UI resolves the
 * stage child of that name, reads its subject links, and pre-ticks them —
 * "a matrix inside a matrix" — leaving the center free to untick what it does
 * not offer.
 *
 * This needs NO schema change: the mapping lives in category_child_option rows
 * the app already knows how to read (same shape the child-options screens use).
 *
 *   php artisan db:seed --class=EducationalStagesSeeder
 *
 * Stage children are real business types too — a center specialising in
 * ابتدائي can register under it directly — so they get the universal commerce
 * options and a booking link like any other child. Idempotent; nothing deleted.
 */
class EducationalStagesSeeder extends Seeder
{
    private const ROOT_ID = 12;
    private const SUBJECT_GROUP = 'المواد الدراسية';
    private const STAGE_GROUP = 'المراحل التعليمية';
    private const TUTORING_CHILD = 'سنتر دروس';
    private const UNIVERSAL_OPTION_GROUP_ID = 12;

    /** Subjects that must exist for the Azhari / technical stages. */
    private const EXTRA_SUBJECTS = [
        'قرآن وتجويد' => 'Quran & Tajweed',
        'فقه' => 'Fiqh',
        'حديث' => 'Hadith',
        'توحيد' => 'Tawheed',
        'تفسير' => 'Tafseer',
        'نحو وصرف' => 'Arabic Grammar & Morphology',
        'بلاغة وأدب' => 'Rhetoric & Literature',
        'محاسبة تجارية' => 'Commercial Accounting',
        'إدارة ومبيعات' => 'Management & Sales',
        'كهرباء وإلكترونيات' => 'Electrical & Electronics',
        'ميكانيكا وسيارات' => 'Mechanics & Automotive',
        'فندقة وسياحة' => 'Hospitality & Tourism',
        'تمريض ورعاية صحية' => 'Nursing & Health Care',
        'زراعة' => 'Agriculture',
    ];

    /** stage child => its subjects (by name_ar, as they exist in the group). */
    private const STAGES = [
        'رياض أطفال' => [
            'تأسيس قراءة وكتابة', 'لغة عربية', 'لغة إنجليزية', 'رياضيات', 'تحفيظ قرآن',
        ],
        'ابتدائي' => [
            'لغة عربية', 'لغة إنجليزية', 'رياضيات', 'علوم', 'دراسات اجتماعية',
            'تربية دينية', 'حاسب آلي وتكنولوجيا معلومات', 'تحفيظ قرآن', 'تأسيس قراءة وكتابة',
        ],
        'إعدادي' => [
            'لغة عربية', 'لغة إنجليزية', 'لغة فرنسية', 'لغة ألمانية', 'رياضيات',
            'علوم', 'دراسات اجتماعية', 'تربية دينية', 'حاسب آلي وتكنولوجيا معلومات',
        ],
        'ثانوي عام' => [
            'لغة عربية', 'لغة إنجليزية', 'لغة فرنسية', 'لغة ألمانية', 'لغة إيطالية', 'لغة إسبانية',
            'رياضيات', 'رياضيات بحتة', 'رياضيات تطبيقية (استاتيكا وديناميكا)',
            'فيزياء', 'كيمياء', 'أحياء', 'جيولوجيا وعلوم بيئة',
            'تاريخ', 'جغرافيا', 'فلسفة ومنطق', 'علم نفس واجتماع', 'اقتصاد وإحصاء',
            'تربية دينية', 'حاسب آلي وتكنولوجيا معلومات',
        ],
        'ثانوي أزهري' => [
            'قرآن وتجويد', 'فقه', 'حديث', 'توحيد', 'تفسير', 'نحو وصرف', 'بلاغة وأدب',
            'لغة عربية', 'لغة إنجليزية', 'رياضيات', 'فيزياء', 'كيمياء', 'أحياء',
            'تاريخ', 'جغرافيا', 'تحفيظ قرآن',
        ],
        'دبلومات فنية' => [
            'محاسبة تجارية', 'إدارة ومبيعات', 'كهرباء وإلكترونيات', 'ميكانيكا وسيارات',
            'فندقة وسياحة', 'تمريض ورعاية صحية', 'زراعة',
            'لغة عربية', 'لغة إنجليزية', 'رياضيات', 'حاسب آلي وتكنولوجيا معلومات',
        ],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $subjectGroupId = (int) DB::table('option_groups')->where('name_ar', self::SUBJECT_GROUP)->value('id');
            $stageGroupId = (int) DB::table('option_groups')->where('name_ar', self::STAGE_GROUP)->value('id');

            if (! $subjectGroupId || ! $stageGroupId) {
                $this->command?->warn('Subject/stage groups missing — run TutoringCenterPoolsSeeder first.');

                return;
            }

            $addedSubjects = $this->ensureSubjects($subjectGroupId);
            $universalIds = DB::table('options')->where('group_id', self::UNIVERSAL_OPTION_GROUP_ID)->pluck('id');
            $rows = [];

            foreach (self::STAGES as $stage => $subjects) {
                $childId = $this->ensureChild($stage);

                // the stage's own subjects — this IS the stage definition the UI reads
                $order = 0;
                $linked = 0;

                foreach ($subjects as $subject) {
                    $optionId = DB::table('options')
                        ->where('group_id', $subjectGroupId)->where('name_ar', $subject)->value('id');

                    if (! $optionId) {
                        $this->command?->warn("  ! «{$subject}» غير موجودة في المواد — تخُطّي.");
                        continue;
                    }

                    DB::table('category_child_option')->updateOrInsert(
                        ['child_id' => $childId, 'option_id' => (int) $optionId],
                        ['reorder' => ++$order]
                    );
                    $linked++;
                }

                // a stage child is a business type too: give it the commerce facets
                foreach ($universalIds as $optionId) {
                    $exists = DB::table('category_child_option')
                        ->where('child_id', $childId)->where('option_id', $optionId)->exists();

                    if (! $exists) {
                        DB::table('category_child_option')->insert([
                            'child_id' => $childId, 'option_id' => $optionId, 'reorder' => 0,
                        ]);
                    }
                }

                $rows[$stage] = $linked;
            }

            $tutoring = $this->syncTutoringCentre($subjectGroupId, $stageGroupId);

            $this->command?->info('Educational stages matrix applied:');
            $this->command?->line('  - subjects added : ' . $addedSubjects);
            foreach ($rows as $stage => $n) {
                $this->command?->line("  - «{$stage}» ← {$n} مادة");
            }
            $this->command?->line('  - سنتر دروس : ' . $tutoring['stages'] . ' مرحلة + ' . $tutoring['subjects'] . ' مادة');
            $this->command?->line('  NEXT: php artisan db:seed --class=NewChildrenBranchesSeeder');
        });
    }

    /** Azhari + technical subjects the base pool did not carry. */
    private function ensureSubjects(int $groupId): int
    {
        $added = 0;

        foreach (self::EXTRA_SUBJECTS as $ar => $en) {
            if (DB::table('options')->where('group_id', $groupId)->where('name_ar', $ar)->exists()) {
                continue;
            }

            // options.name_en is globally unique — suffix on collision
            if (DB::table('options')->where('name_en', $en)->exists()) {
                $en .= ' (Subject)';
            }

            DB::table('options')->insert(['group_id' => $groupId, 'name_ar' => $ar, 'name_en' => $en]);
            $added++;
        }

        return $added;
    }

    private function ensureChild(string $name): int
    {
        $id = DB::table('category_children_master')->where('name_ar', $name)->value('id');

        if (! $id) {
            $id = DB::table('category_children_master')->insertGetId([
                'name_ar' => $name,
                'name_en' => $name,
                'reorder' => 1 + (int) DB::table('category_children_master')->max('reorder'),
            ]);
        }

        DB::table('category_parent_child')->updateOrInsert(
            ['parent_id' => self::ROOT_ID, 'child_id' => (int) $id],
            ['updated_at' => now()]
        );

        return (int) $id;
    }

    /**
     * سنتر دروس holds every stage AND every subject: the stages are what it
     * ticks, the subjects are what the tick expands into and what it may then
     * untick individually.
     *
     * @return array{stages:int,subjects:int}
     */
    private function syncTutoringCentre(int $subjectGroupId, int $stageGroupId): array
    {
        $childId = DB::table('category_children_master')->where('name_ar', self::TUTORING_CHILD)->value('id');

        if (! $childId) {
            return ['stages' => 0, 'subjects' => 0];
        }

        $counts = ['stages' => 0, 'subjects' => 0];

        foreach ([['stages', $stageGroupId], ['subjects', $subjectGroupId]] as [$label, $groupId]) {
            $order = 0;

            foreach (DB::table('options')->where('group_id', $groupId)->pluck('id') as $optionId) {
                DB::table('category_child_option')->updateOrInsert(
                    ['child_id' => (int) $childId, 'option_id' => (int) $optionId],
                    ['reorder' => ++$order]
                );
                $counts[$label]++;
            }
        }

        return $counts;
    }
}
