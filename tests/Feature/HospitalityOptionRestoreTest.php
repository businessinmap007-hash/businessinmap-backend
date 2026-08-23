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

    /** The four kinds of stay that were never emptied. */
    private const INTACT = ['فندق', 'شقق فندقية', 'منتجع', 'نُزل / هوستل'];

    /**
     * Whatever every intact sibling carries, the restored two carry too.
     *
     * «carries the base», not «carries only the base». A child gaining an extra
     * payment word from some other seeder is not a regression, and asserting an
     * exact group size would turn every unrelated grant into a red test here.
     *
     * ── And the base is MEASURED, not written down ────────────────────────────
     *
     * It used to be five numbers in a literal, and every one of them was a
     * snapshot of a taxonomy that has not stopped moving: «نقل من المطار» left
     * «مرافق الإقامة» for «خدمات الفندق» in August so a hotel could price it,
     * «ملاءمة المكان» grew from two answers to five, and on 2026-08-20 the owner
     * took that whole group off four of the six kinds of stay and «إطلالة
     * الوحدة» off the Nile boat. Each of those is a correct change and each one
     * turned this test red for measuring the wrong thing.
     *
     * The sentence in the title is already the rule, and it names its own
     * yardstick: the base IS what the intact siblings carry. Read from them, it
     * moves when the trade moves and still fails the day one of the restored two
     * falls behind its siblings — which is the only thing that was ever being
     * asked.
     */
    public function test_both_carry_the_base_every_sibling_has(): void
    {
        $siblings = array_map(fn ($n) => $this->groupsOf($this->childId($n)), self::INTACT);

        // The base of a group is the least any intact sibling holds of it. A
        // group no sibling holds has no base to fall behind.
        $base = [];

        foreach (array_merge(...array_map('array_keys', $siblings)) as $group) {
            $base[$group] = min(array_map(fn ($held) => $held[$group] ?? 0, $siblings));
        }

        $base = array_filter($base);

        $this->assertNotEmpty($base, 'the four intact siblings share no group at all');

        foreach (['بيت ضيافة', 'فندق عائم / بوت نيلي'] as $name) {
            $groups = $this->groupsOf($this->childId($name));

            $gaveUp = $this->withdrawnPerGroup($this->childId($name));

            foreach ($base as $group => $least) {
                /*
                 * A row he took off by hand is not a row that went missing. The
                 * boat gave «إطلالة الوحدة» back on 2026-08-20 — a cruiser's
                 * window is not a fixed attribute of a cabin — and its four
                 * siblings kept the group, so the floor alone reads that ruling
                 * as damage.
                 *
                 * Counting the ledger beside the links keeps the floor honest
                 * both ways: a row that disappears with no decision behind it
                 * still fails, which is the accident this whole file exists for.
                 */
                $this->assertGreaterThanOrEqual(
                    $least,
                    ($groups[$group] ?? 0) + ($gaveUp[$group] ?? 0),
                    "«{$name}» lost part of «{$group}» — it must carry at least the base every sibling has"
                );
            }

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

    /**
     * How many rows of each group this child took off by hand.
     *
     * @return array<string,int> group name => withdrawals
     */
    private function withdrawnPerGroup(int $childId): array
    {
        return DB::table('category_child_option_decisions as d')
            ->join('options as o', 'o.id', '=', 'd.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('d.child_id', $childId)
            ->where('d.kind', 'withdrawn')
            ->select('g.name_ar', DB::raw('COUNT(DISTINCT o.id) as total'))
            ->groupBy('g.name_ar')
            ->pluck('total', 'g.name_ar')
            ->map(fn ($n) => (int) $n)
            ->all();
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

    /**
     * The four intact siblings were not touched — by THIS seeder.
     *
     * The claim was once four totals: فندق 51، شقق فندقية 38، منتجع 55، نُزل 40.
     * Every one of them has been correct and then wrong, and the comment above
     * them grew a dated paragraph each time — the payment pair replacing «تقسيط
     * بدون فوائد», the star rating lent to four kinds of stay, «خدمات الفندق»
     * going from one row to eight. None of those was this seeder's doing, which
     * is what the test says it is checking, and none of them could be told apart
     * from a real regression by a number.
     *
     * On 2026-08-20 the owner took the stars back off «نُزل» and «ملاءمة
     * المكان» off «فندق» — his rulings, and the fourth and fifth time these
     * literals went red for someone else's correct work.
     *
     * So it measures the sentence instead: run the restore, and the four are
     * where they were. That fails on exactly one thing — this seeder reaching a
     * child it has no business touching — and on nothing else.
     */
    public function test_the_intact_siblings_are_unchanged(): void
    {
        $count = fn (string $name) => DB::table('category_child_option')
            ->where('child_id', $this->childId($name))->count();

        $before = array_map($count, array_combine(self::INTACT, self::INTACT));

        $this->assertNotContains(0, $before, 'an intact sibling is missing or empty');

        $this->artisan('db:seed', ['--class' => 'HospitalityOptionRestoreSeeder', '--no-interaction' => true])->run();

        foreach ($before as $name => $was) {
            $this->assertSame($was, $count($name), "«{$name}» changed — the restore was supposed to leave it alone");
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
