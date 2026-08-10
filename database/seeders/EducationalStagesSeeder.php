<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The stage→subject matrix.
 *
 *   php artisan db:seed --class=EducationalStagesSeeder
 *
 * ORIGINALLY (owner design 2026-08-02) each stage was also a CHILD, so that its
 * subject links doubled as the stage definition: a centre ticks «إعدادي», the UI
 * resolves the stage child of that name, reads its subject links and pre-ticks
 * them — "a matrix inside a matrix".
 *
 * FOLDED 2026-08-10, owner: «اطوها كالورش». The six stage children stood beside
 * «سنتر دروس» holding zero accounts each, and «سنتر دروس» already carried the
 * same six as options in «المراحل التعليمية» — six rows that were six words
 * standing next to themselves, exactly the shape the workshop benches had. They
 * are detached by ChildRootDetachSeeder; nothing here re-creates them.
 *
 * **The matrix below is NOT deleted, and that is the point of this note.** It is
 * the only place that records which subjects belong to which stage, the UI that
 * would read it was never built, and folding the rows must not take the design
 * with them. The seeder writes it onto any stage that is STILL a child — today
 * that is «حضانات» alone, which is a real pre-school with live accounts — and
 * keeps the rest as a declaration waiting for the feature.
 *
 * Idempotent; nothing deleted.
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
        // حضانات is the pre-school stage in practice (3 live accounts), so it
        // carries the same foundation set as رياض أطفال.
        'حضانات' => [
            'تأسيس قراءة وكتابة', 'لغة عربية', 'لغة إنجليزية', 'رياضيات', 'تحفيظ قرآن',
        ],
    ];

    /**
     * Children that teach FIELDS, not school subjects. Any المواد الدراسية link
     * on them is a leak — «تحفيظ قرآن» had drifted onto مركز تدريب, which would
     * have offered a training centre a school subject it never teaches.
     */
    private const SUBJECT_FREE_CHILDREN = ['مركز تدريب'];

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

            $folded = [];

            foreach (self::STAGES as $stage => $subjects) {
                $childId = $this->standingChild($stage);

                // A stage that is no longer a child is a WORD now, and its row
                // must not be conjured back — an add-only seeder naming a folded
                // child is how a fold gets undone on the next run.
                if ($childId <= 0) {
                    $folded[] = $stage;

                    continue;
                }

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
            $unlinked = $this->clearLeakedSubjects($subjectGroupId);

            $this->command?->info('Educational stages matrix applied:');
            $this->command?->line('  - subjects added : ' . $addedSubjects);
            foreach ($rows as $stage => $n) {
                $this->command?->line("  - «{$stage}» ← {$n} مادة");
            }
            $this->command?->line('  - مراحل مطويّة (كلمات لا صفوف) : ' . (implode('، ', $folded) ?: 'لا شيء'));
            $this->command?->line('  - سنتر دروس : ' . $tutoring['stages'] . ' مرحلة + ' . $tutoring['subjects'] . ' مادة');
            $this->command?->line('  - leaked subject links cleared : ' . $unlinked);
            $this->command?->line('  NEXT: php artisan db:seed --class=NewChildrenBranchesSeeder');
        });
    }

    /** Strip school subjects off children that teach fields instead. */
    private function clearLeakedSubjects(int $subjectGroupId): int
    {
        $subjectIds = DB::table('options')->where('group_id', $subjectGroupId)->pluck('id');
        $cleared = 0;

        foreach (self::SUBJECT_FREE_CHILDREN as $name) {
            $childId = DB::table('category_children_master')->where('name_ar', $name)->value('id');

            if (! $childId) {
                continue;
            }

            $cleared += DB::table('category_child_option')
                ->where('child_id', (int) $childId)
                ->whereIn('option_id', $subjectIds)
                ->delete();
        }

        return $cleared;
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

    /**
     * The stage's child row IF it still stands under the root — never created.
     *
     * This used to be `ensureChild()`, which inserted the row and attached it.
     * After the 2026-08-10 fold that would have been the undo button: the six
     * detached stages would come straight back under دورات وتدريب on the next
     * run of a seeder nobody thought to look at.
     */
    private function standingChild(string $name): int
    {
        return (int) DB::table('category_parent_child as p')
            ->join('category_children_master as c', 'c.id', '=', 'p.child_id')
            ->where('p.parent_id', self::ROOT_ID)
            ->where('c.name_ar', $name)
            ->value('c.id');
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
