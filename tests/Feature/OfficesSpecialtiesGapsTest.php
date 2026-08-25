<?php

namespace Tests\Feature;

use Database\Seeders\OfficesSpecialtiesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «راجع مجموعات الخدمات المكتبية الباقية زى المحاماة والاستشارات» — المالك،
 * 2026-08-25. Five practice areas the original office-specialty pools did not
 * name, plus the Fintech-law row confirmed by the owner after «Fine tec»
 * turned out to mean Fintech.
 *
 * Rolls back.
 */
class OfficesSpecialtiesGapsTest extends TestCase
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

    public function test_the_lawyer_can_name_the_new_practice_areas(): void
    {
        $lines = $this->linesOf('محاماه', 'تخصصات المحاماة');

        foreach ([
            'قانون الاستثمار والشركات الناشئة',
            'التوثيق والشهر العقاري',
            'الوساطة وتسوية المنازعات',
            'قانون البناء والتراخيص',
            'التكنولوجيا المالية (فينتك)',
        ] as $area) {
            $this->assertContains($area, $lines, "المحامى لا يستطيع تسمية «{$area}»");
        }
    }

    /**
     * «الاستشارات» has no trade of its own — `ConsultingConsolidationSeeder`
     * retired the per-field consultation types on 2026-08-02 and no
     * «استشارات» child or root replaced them. So the row lives on the office
     * that actually sells it.
     */
    public function test_the_accountant_can_name_financial_and_management_consulting(): void
    {
        $this->assertContains('استشارات مالية وإدارية', $this->linesOf('محاسبة', 'تخصصات المحاسبة'));

        $this->assertSame(
            0,
            DB::table('category_children_master')->where('name_ar', 'like', '%استشار%')->count(),
            'a «consulting» child exists again — the new row belongs on it instead, not on محاسبة'
        );
    }

    public function test_the_seeder_is_idempotent(): void
    {
        $before = DB::table('options')->count();
        $beforeLinks = DB::table('category_child_option')->count();

        (new OfficesSpecialtiesSeeder())->run();

        $this->assertSame($before, DB::table('options')->count());
        $this->assertSame($beforeLinks, DB::table('category_child_option')->count());
    }

    /** Nothing this seeder ever wrote is deleted — its own docblock promise. */
    public function test_no_specialty_from_the_original_pool_was_dropped(): void
    {
        $original = [
            'جنائي', 'مدني', 'أحوال شخصية وأسرة', 'تجاري وشركات', 'عمالي', 'عقاري',
            'ضرائب', 'إداري', 'جنح ومرور', 'استئناف ونقض', 'تحكيم', 'صياغة عقود',
        ];

        $lines = $this->linesOf('محاماه', 'تخصصات المحاماة');

        foreach ($original as $area) {
            $this->assertContains($area, $lines, "«{$area}» اختفت من قائمة المحاماة الأصلية");
        }
    }

    public function test_an_english_name_is_spent_once_platform_wide(): void
    {
        $dupes = DB::table('options')
            ->select('name_en')->whereNotNull('name_en')->where('name_en', '!=', '')
            ->groupBy('name_en')->havingRaw('COUNT(*) > 1')->pluck('name_en')->all();

        $this->assertSame([], $dupes, 'أسماء إنجليزية مكررة : ' . implode(' · ', $dupes));
    }
}
