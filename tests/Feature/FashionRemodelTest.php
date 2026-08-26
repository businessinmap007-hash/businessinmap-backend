<?php

namespace Tests\Feature;

use Database\Seeders\ChildOptionScopeSeeder;
use Database\Seeders\FashionRemodelSeeder;
use Database\Seeders\LinkCategoryChildrenToOptionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «هناك محلات احذية فقط وكوتشى فقط واكسسوار فقط لكن هناك محلات بها كلهم — ملابس
 * زفاف وملابس رسمية ايضا فى محل واحد» — owner, 2026-08-08.
 *
 * Root #14 answered "what does this shop sell?" three times over: the child
 * name, the retail item type, and the line option. A business has exactly one
 * `category_child_id`, so the shop with all of it had no home — and «كوتشي»
 * carried ZERO line options, meaning a sneaker shop could name nothing it sold.
 *
 * The health remodel again: the child says WHERE, the options say WHAT, and the
 * audience says for whom.
 */
class FashionRemodelTest extends TestCase
{
    use DatabaseTransactions;

    private const ROOT = 14;

    private array $data;

    private int $groupId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->data = require database_path('seeders/data/fashion_taxonomy.php');

        $this->groupId = (int) DB::table('option_groups')
            ->where('name_ar', $this->data['group_name_ar'])->value('id');

        if ($this->groupId <= 0) {
            $this->markTestSkipped('The fashion group is not in the taxonomy.');
        }
    }

    /** @return array<int,int> */
    private function childrenOfRoot(): array
    {
        return DB::table('category_parent_child')->where('parent_id', self::ROOT)
            ->pluck('child_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
    }

    /** @return array<int,string> */
    private function offered(int $childId): array
    {
        return DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->where('cco.child_id', $childId)
            ->where('o.group_id', $this->groupId)
            ->pluck('o.name_ar')->unique()->values()->all();
    }

    /** Nine became three. */
    public function test_the_root_holds_only_the_three(): void
    {
        $keep = $this->data['keep'];
        sort($keep);

        $this->assertSame($keep, $this->childrenOfRoot());
    }

    /**
     * The owner's shop: clothes AND shoes AND accessories, all sayable.
     *
     * The claim is that the five words live on ONE axis a child may tick many
     * of — which is the whole remodel — not that all three survivors must carry
     * all five forever. On 2026-08-14 the owner narrowed «اكسسوار» to what an
     * accessories shop actually sells, withdrawing thirty-five words from it one
     * save at a time; requiring every child to keep every word turned his
     * curation into a failure and would have had the seeder hand back wedding
     * dresses to a bag shop.
     *
     * So: every word must still be claimable, at least one child must be able
     * to claim several at once, and nothing may be left with no word at all.
     */
    public function test_one_shop_can_claim_clothes_shoes_and_accessories(): void
    {
        $words = ['ملابس', 'أحذية', 'كوتشي', 'اكسسوارات', 'شنط وحقائب'];

        $byChild = collect($this->data['keep'])
            ->mapWithKeys(fn ($childId) => [$childId => $this->offered($childId)]);

        foreach ($words as $must) {
            $this->assertTrue(
                $byChild->contains(fn ($offered) => in_array($must, $offered, true)),
                "no child of the root can say «{$must}» — the word has no home again"
            );
        }

        $this->assertTrue(
            $byChild->contains(fn ($offered) => count(array_intersect($words, $offered)) >= 3),
            'no single child can claim clothes and shoes and accessories together — '
                . 'the remodel put these on one axis precisely so one shop could'
        );

        /*
         * «nothing may be left with no word at all» — re-pointed on 2026-08-17
         * rather than dropped.
         *
         * The invariant is about the CHILD, not about this group. «اكسسوار» #8
         * ended that day holding one row of the fashion list, #21 «اكسسوارات»,
         * which is the child's own name said back at it; it was declared empty
         * in `child_option_scopes.php` for the reason #95 «أقمشة» was, and it
         * cost nothing because the same week gave #8 «أنواع الإكسسوارات» and
         * fourteen rows of real stock.
         *
         * So the question is «can this child name something it sells», asked of
         * every LINE group it carries — which is what the guard was written to
         * catch and what a group-shaped assertion had stopped measuring.
         */
        foreach ($byChild->keys() as $childId) {
            $this->assertNotEmpty(
                DB::table('category_child_option as cco')
                    ->join('options as o', 'o.id', '=', 'cco.option_id')
                    ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                    ->where('cco.child_id', $childId)
                    ->where('g.price_role', 'line')
                    ->where('g.is_active', 1)
                    ->pluck('o.name_ar')->all(),
                "child #{$childId} can name nothing it sells"
            );
        }
    }

    /** «ملابس زفاف وملابس رسمية ايضا فى محل واحد» — two ticks, one shop. */
    public function test_wedding_and_formal_wear_live_on_the_same_child(): void
    {
        $offered = $this->offered(59);

        $this->assertContains('فساتين زفاف', $offered);
        $this->assertContains('بدلة زفاف', $offered);
        $this->assertContains('ملابس رسمية', $offered);
    }

    /**
     * The move had to be lossless: a sneaker shop that was «كوتشي» still says
     * so, as its own tick rather than as its category.
     */
    public function test_a_moved_business_kept_what_it_sold(): void
    {
        $sneaker = (int) DB::table('options')
            ->where('group_id', $this->groupId)->where('name_ar', 'كوتشي')->value('id');

        $this->assertGreaterThan(0, $sneaker, 'the sneaker option was never created');

        $ticked = DB::table('option_user')->where('option_id', $sneaker)->pluck('user_id');

        $this->assertNotEmpty($ticked->all(), 'no business carries the sneaker claim');

        foreach ($ticked as $userId) {
            $this->assertSame(168, (int) DB::table('users')->where('id', $userId)->value('category_child_id'));
        }
    }

    /**
     * The retired rows stood as the undo record until 2026-08-26, when the
     * owner reviewed the platform's whole rootless list and hard-deleted
     * every one that stood under no root — this batch included. One
     * deliberate exception to «لا شىء يُحذف», not a bug this file's own
     * seeder should reverse.
     */
    public function test_the_retired_children_are_gone_for_good(): void
    {
        foreach (array_keys($this->data['retire']) as $name) {
            $this->assertSame(
                0,
                DB::table('category_children_master')->where('name_ar', $name)->count(),
                "«{$name}» is back — the owner's 2026-08-26 cleanup should not be reversed by a seeder"
            );
        }
    }

    /** No business may be left pointing at a child the root no longer holds. */
    public function test_no_business_was_left_behind(): void
    {
        foreach (array_keys($this->data['retire']) as $name) {
            $childId = (int) DB::table('category_children_master')->where('name_ar', $name)->value('id');

            $this->assertSame(
                0,
                DB::table('users')->where('category_child_id', $childId)->count(),
                "«{$name}» still holds a business but is no longer under the root"
            );
        }
    }

    /**
     * The scope seeder must not narrow the three back down.
     *
     * «Silenced» means losing a word it currently holds — the failure the
     * remodel was written against, where كوتشي could name nothing at all. It
     * does NOT mean «must still carry كوتشي»: the owner withdrew that word from
     * «اكسسوار» himself, and a test that reads a withdrawal as a silencing is a
     * test that argues with him every run.
     *
     * Each child is checked against its own set, so the seeders may add but
     * never subtract.
     */
    public function test_the_scope_seeder_does_not_re_silence_them(): void
    {
        $before = collect($this->data['keep'])
            ->mapWithKeys(fn ($childId) => [$childId => $this->offered($childId)]);

        DB::beginTransaction();

        try {
            (new LinkCategoryChildrenToOptionsSeeder)->run();
            (new ChildOptionScopeSeeder)->run();

            foreach ($before as $childId => $had) {
                $lost = array_values(array_diff($had, $this->offered($childId)));

                $this->assertSame([], $lost,
                    "child #{$childId} was silenced again — it lost: " . implode('، ', $lost));
            }
        } finally {
            DB::rollBack();
        }
    }

    /** Re-running changes nothing. */
    public function test_the_seeder_is_idempotent(): void
    {
        $before = [
            DB::table('options')->where('group_id', $this->groupId)->count(),
            DB::table('category_child_option')->count(),
            $this->childrenOfRoot(),
        ];

        (new FashionRemodelSeeder)->run();

        $this->assertSame($before, [
            DB::table('options')->where('group_id', $this->groupId)->count(),
            DB::table('category_child_option')->count(),
            $this->childrenOfRoot(),
        ]);
    }
}
