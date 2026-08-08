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

    /** The owner's shop: clothes AND shoes AND accessories, all sayable. */
    public function test_one_shop_can_claim_clothes_shoes_and_accessories(): void
    {
        foreach ($this->data['keep'] as $childId) {
            $offered = $this->offered($childId);

            foreach (['ملابس', 'أحذية', 'كوتشي', 'اكسسوارات', 'شنط وحقائب'] as $must) {
                $this->assertContains($must, $offered, "child #{$childId} cannot say «{$must}»");
            }
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

    /** Non-destructive: the retired rows are the undo record. */
    public function test_the_retired_children_still_exist(): void
    {
        foreach (array_keys($this->data['retire']) as $name) {
            $this->assertTrue(
                DB::table('category_children_master')->where('name_ar', $name)->exists(),
                "«{$name}» was deleted rather than detached"
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

    /** The scope seeder must not narrow the three back down. */
    public function test_the_scope_seeder_does_not_re_silence_them(): void
    {
        DB::beginTransaction();

        try {
            (new LinkCategoryChildrenToOptionsSeeder)->run();
            (new ChildOptionScopeSeeder)->run();

            foreach ($this->data['keep'] as $childId) {
                $this->assertContains('كوتشي', $this->offered($childId), "child #{$childId} was silenced again");
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
