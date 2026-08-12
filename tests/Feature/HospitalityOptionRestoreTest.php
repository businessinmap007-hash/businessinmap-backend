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

    /**
     * Whatever every intact sibling carries, the restored two carry too.
     *
     * «carries the base», not «carries only the base». A child gaining an extra
     * payment word from some other seeder is not a regression, and asserting an
     * exact group size would turn every unrelated grant into a red test here.
     */
    public function test_both_carry_the_base_every_sibling_has(): void
    {
        foreach (['بيت ضيافة', 'فندق عائم / بوت نيلي'] as $name) {
            $groups = $this->groupsOf($this->childId($name));

            $this->assertSame(10, $groups['مرافق الإقامة'] ?? 0, "«{$name}» lost its facilities");
            $this->assertSame(2, $groups['ملاءمة المكان'] ?? 0);
            $this->assertSame(2, $groups['إطلالة الوحدة'] ?? 0);
            $this->assertSame(3, $groups['نظام الوجبات'] ?? 0);

            // Was «تقسيط بدون فوائد» until 2026-08-10, when the owner made that
            // one hand-set only. The base a hospitality child must carry is now
            // the pair every trade can answer.
            $names = $this->optionNamesOf($this->childId($name));

            $this->assertContains('كاش', $names);
            $this->assertContains('تقسيط', $names);
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
     * The accident left a root-scoped row BESIDE the shared row for the same
     * option, so the child appeared to carry it twice and the card counted it
     * twice. That duplication is the defect — a scoped row on its own is not:
     * other seeders write them legitimately, and asserting they can never exist
     * would make this test a tripwire on work that is none of its business.
     */
    public function test_no_scoped_row_duplicates_a_shared_one(): void
    {
        foreach (['بيت ضيافة', 'فندق عائم / بوت نيلي'] as $name) {
            $childId = $this->childId($name);

            $shared = DB::table('category_child_option')->where('child_id', $childId)
                ->where('category_id', 0)->pluck('option_id')->map(fn ($id) => (int) $id);

            $scoped = DB::table('category_child_option')->where('child_id', $childId)
                ->where('category_id', '>', 0)->pluck('option_id')->map(fn ($id) => (int) $id);

            $this->assertSame(
                [],
                $scoped->intersect($shared)->values()->all(),
                "«{$name}» carries the same option both shared and scoped"
            );
        }
    }

    /** The four intact siblings were not touched. */
    public function test_the_intact_siblings_are_unchanged(): void
    {
        // Each gained one on 2026-08-10: the payment group's single wholesale
        // option «تقسيط بدون فوائد» was replaced by the pair كاش + تقسيط. The
        // restore seeder still did not touch them — the totals moved underneath
        // it, which is what a hard-coded total costs and why it is worth it.
        //
        // And again on 2026-08-12: «تصنيف الإقامة» was lent to the four kinds of
        // stay that never had it (ChildVocabularyBorrowSeeder), +7 each — so
        // شقق فندقية 28→35 and نُزل 26→33, while فندق and منتجع already held it
        // and did not move. The restore seeder is still not the one that wrote
        // them, which is the whole point of the assertion.
        foreach (['فندق' => 44, 'شقق فندقية' => 35, 'منتجع' => 48, 'نُزل / هوستل' => 33] as $name => $expected) {
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
