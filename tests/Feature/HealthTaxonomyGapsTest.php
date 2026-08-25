<?php

namespace Tests\Feature;

use Database\Seeders\HealthRemodelSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «راجع باقى مجموعات الخدمات الطبية والصحية» — المالك، 2026-08-25.
 *
 * Four medical specialties, one imaging modality and two lab tests, added to
 * data/health_taxonomy.php per its own docblock: «Adding a specialty later =
 * append to SPECIALTIES and re-run; the seeder is idempotent and never
 * deletes.»
 *
 * Rolls back.
 */
class HealthTaxonomyGapsTest extends TestCase
{
    use DatabaseTransactions;

    private function childId(string $nameAr): int
    {
        return (int) DB::table('category_children_master')->where('name_ar', $nameAr)->value('id');
    }

    /** @return array<int,string> */
    private function linesOf(string $childNameAr, string $groupNameAr): array
    {
        return DB::table('category_child_option as l')
            ->join('options as o', 'o.id', '=', 'l.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('l.child_id', $this->childId($childNameAr))
            ->where('g.name_ar', $groupNameAr)
            ->pluck('o.name_ar')
            ->all();
    }

    public function test_a_hospital_can_name_the_four_new_specialties(): void
    {
        $lines = $this->linesOf('مستشفى', 'تخصصات طبية');

        foreach (['طب نفسي', 'طب الطوارئ', 'الأمراض المعدية', 'علاج الإدمان'] as $s) {
            $this->assertContains($s, $lines, "المستشفى لا يستطيع تسمية «{$s}»");
        }
    }

    public function test_a_radiology_centre_can_name_pet_ct(): void
    {
        $this->assertContains('بيت سكان (PET-CT)', $this->linesOf('مراكز أشعة', 'أنواع الأشعة'));
    }

    public function test_a_lab_can_name_covid_and_allergy_tests(): void
    {
        $lines = $this->linesOf('معمل تحاليل', 'التحاليل الطبية');

        $this->assertContains('مسحة كوفيد (PCR / سريع)', $lines);
        $this->assertContains('تحليل الحساسية', $lines);
    }

    public function test_no_original_specialty_was_dropped(): void
    {
        $original = [
            'أسنان', 'باطنه', 'جراحة عامة', 'عظام', 'عيون', 'نساء و ولادة',
            'اطفال وحديثي الولادة', 'قلب وأوعية دموية', 'مخ وأعصاب', 'كلى', 'كبد',
        ];

        $lines = $this->linesOf('مستشفى', 'تخصصات طبية');

        foreach ($original as $s) {
            $this->assertContains($s, $lines, "«{$s}» اختفت من قائمة التخصصات الأصلية");
        }
    }

    public function test_the_seeder_is_idempotent(): void
    {
        $options = DB::table('options')->count();
        $links = DB::table('category_child_option')->count();

        (new HealthRemodelSeeder())->run();

        $this->assertSame($options, DB::table('options')->count());
        $this->assertSame($links, DB::table('category_child_option')->count());
    }

    public function test_an_english_name_is_spent_once_platform_wide(): void
    {
        $dupes = DB::table('options')
            ->select('name_en')->whereNotNull('name_en')->where('name_en', '!=', '')
            ->groupBy('name_en')->havingRaw('COUNT(*) > 1')->pluck('name_en')->all();

        $this->assertSame([], $dupes, 'أسماء إنجليزية مكررة : ' . implode(' · ', $dupes));
    }
}
