<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «عند تجربة تعديل خيارات بيت ضيافة وفندق عائم قمت بالغاء تحديد الخيارات بالخطا
 * اعد ترتيبها» — owner, 2026-08-09.
 *
 * Both children were stripped to three rows while trying the new card. What they
 * get back is derived, not invented: the base is the intersection of their four
 * intact siblings, and only the room list is a judgement call.
 */
class HospitalityOptionRestoreTest extends TestCase
{
    use DatabaseTransactions;

    private const HOTELS_ROOT = 24;

    private function childId(string $nameAr): int
    {
        return (int) DB::table('category_children_master')->where('name_ar', $nameAr)->value('id');
    }

    /** @return array<int,string> group name => count of that child's options */
    private function groupsOf(int $childId): array
    {
        return DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('cco.child_id', $childId)
            ->select('g.name_ar', DB::raw('COUNT(*) as total'))
            ->groupBy('g.name_ar')
            ->pluck('total', 'g.name_ar')
            ->all();
    }

    /** Whatever every intact sibling carries, the restored two carry too. */
    public function test_both_carry_the_base_every_sibling_has(): void
    {
        foreach (['بيت ضيافة', 'فندق عائم / بوت نيلي'] as $name) {
            $groups = $this->groupsOf($this->childId($name));

            $this->assertSame(10, $groups['مرافق الإقامة'] ?? 0, "«{$name}» lost its facilities");
            $this->assertSame(2, $groups['ملاءمة المكان'] ?? 0);
            $this->assertSame(2, $groups['إطلالة الوحدة'] ?? 0);
            $this->assertSame(3, $groups['نظام الوجبات'] ?? 0);
            $this->assertSame(1, $groups['الدفع والسداد'] ?? 0);
        }
    }

    /**
     * A property that lets rooms must be able to name them — but the room axis
     * belongs to HotelRoomKindOptionsSeeder, which states word by word which of
     * the six children may say each one. This restore does not touch it, and
     * this test only proves the rooms came back through the seeder that owns
     * them: only the boat says «كابينة», and a dormitory bed is a hostel's.
     */
    public function test_the_rooms_came_back_from_the_seeder_that_owns_them(): void
    {
        foreach (['بيت ضيافة', 'فندق عائم / بوت نيلي'] as $name) {
            $this->assertGreaterThan(0, $this->groupsOf($this->childId($name))['الغرف'] ?? 0, "«{$name}» cannot name a room");
        }

        $this->assertContains('كابينة', $this->optionNamesOf($this->childId('فندق عائم / بوت نيلي')));
        $this->assertNotContains('كابينة', $this->optionNamesOf($this->childId('بيت ضيافة')));

        foreach (['بيت ضيافة', 'فندق عائم / بوت نيلي'] as $name) {
            $this->assertNotContains('سرير في غرفة مشتركة', $this->optionNamesOf($this->childId($name)), "«{$name}»");
        }
    }

    /** @return array<int,string> */
    private function optionNamesOf(int $childId): array
    {
        return DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->where('cco.child_id', $childId)
            ->pluck('o.name_ar')->all();
    }

    /**
     * The accident left root-scoped duplicates of shared rows. A child hanging
     * from one root has nothing to scope against, so the rows are noise — and a
     * duplicate makes the card count an option twice.
     */
    public function test_no_scoped_duplicate_survives_on_a_single_root_child(): void
    {
        foreach (['بيت ضيافة', 'فندق عائم / بوت نيلي'] as $name) {
            $childId = $this->childId($name);

            $this->assertSame(
                1,
                DB::table('category_parent_child')->where('child_id', $childId)->count(),
                "«{$name}» now hangs from more than one root — the collapse rule no longer applies"
            );

            $this->assertSame(
                0,
                DB::table('category_child_option')->where('child_id', $childId)
                    ->where('category_id', '>', 0)->count(),
                "«{$name}» still carries a root-scoped row"
            );
        }
    }

    /** The four intact siblings were not touched. */
    public function test_the_intact_siblings_are_unchanged(): void
    {
        foreach (['فندق' => 43, 'شقق فندقية' => 27, 'منتجع' => 47, 'نُزل / هوستل' => 25] as $name => $expected) {
            $this->assertSame(
                $expected,
                DB::table('category_child_option')->where('child_id', $this->childId($name))->count(),
                "«{$name}» changed — the restore was supposed to leave it alone"
            );
        }
    }

    /** Everything still hangs from فنادق سياحية. */
    public function test_the_restored_children_stayed_in_their_root(): void
    {
        foreach (['بيت ضيافة', 'فندق عائم / بوت نيلي'] as $name) {
            $this->assertTrue(
                DB::table('category_parent_child')
                    ->where('parent_id', self::HOTELS_ROOT)
                    ->where('child_id', $this->childId($name))
                    ->exists()
            );
        }
    }

    /** Additive and repeatable: a second run writes nothing. */
    public function test_the_seeder_is_idempotent(): void
    {
        $before = DB::table('category_child_option')
            ->whereIn('child_id', [$this->childId('بيت ضيافة'), $this->childId('فندق عائم / بوت نيلي')])
            ->count();

        $this->artisan('db:seed', ['--class' => 'HospitalityOptionRestoreSeeder', '--no-interaction' => true])->run();

        $this->assertSame($before, DB::table('category_child_option')
            ->whereIn('child_id', [$this->childId('بيت ضيافة'), $this->childId('فندق عائم / بوت نيلي')])
            ->count());
    }
}
