<?php

namespace Tests\Feature;

use Database\Seeders\ChildOptionGroupsSeeder;
use Database\Seeders\LinkCategoryChildrenToOptionsSeeder;
use Database\Seeders\PrepaymentScopeSeeder;
use Database\Seeders\SalonAndPharmacyOptionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «الغِ اى ربط لخيار الدفع مقدما من كل التصنيفات الا الشحن والتوصيل» — owner,
 * 2026-08-08.
 *
 * «دفع مسبق» had reached 286 children: a bakery, a gym, a barber, a carpenter.
 * It sits in «الدفع والسداد», one of the widest groups on the platform, and the
 * whole group was granted per ROOT — so nobody ever chose to put it on a bakery,
 * it simply arrived.
 *
 * Paying before you receive is not a payment term the way «كاش» is. It is what a
 * CARRIER asks for, because the goods leave his hands and travel; across a shop
 * counter it says nothing, and a descriptive option that describes every
 * business describes none.
 */
class PrepaymentScopeTest extends TestCase
{
    use DatabaseTransactions;

    private int $optionId;

    /** @var array<int,int> */
    private array $carriers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->optionId = (int) DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', 'الدفع والسداد')
            ->where('o.name_ar', 'دفع مسبق')
            ->value('o.id');

        if ($this->optionId <= 0) {
            $this->markTestSkipped('The «دفع مسبق» option is not in the taxonomy.');
        }

        $this->carriers = DB::table('category_parent_child as pc')
            ->join('categories as r', 'r.id', '=', 'pc.parent_id')
            ->where('r.slug', 'shipping-delivery')
            ->pluck('pc.child_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return array<int,int> the children currently offered the option */
    private function linked(): array
    {
        return DB::table('category_child_option')
            ->where('option_id', $this->optionId)
            ->pluck('child_id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();
    }

    /** The rule, stated as the owner stated it. */
    public function test_only_shipping_and_delivery_carries_prepayment(): void
    {
        $this->assertNotEmpty($this->carriers, 'the shipping root has no children');

        sort($this->carriers);

        $this->assertSame(
            $this->carriers,
            $this->linked(),
            'prepayment is offered outside shipping and delivery'
        );
    }

    /** The exception is a ROOT: a carrier added tomorrow inherits it. */
    public function test_the_exception_follows_the_root_not_a_list_of_ids(): void
    {
        $child = $this->carriers[0];

        DB::table('category_child_option')
            ->where('option_id', $this->optionId)
            ->where('child_id', $child)
            ->delete();

        (new PrepaymentScopeSeeder)->run();

        $this->assertContains($child, $this->linked(), 'a carrier was left without it');
    }

    /**
     * The three files that used to hand it back. Two were changed with the
     * withdrawal; this is what proves they stay changed.
     */
    public function test_the_broad_seeders_no_longer_restore_it(): void
    {
        DB::beginTransaction();

        try {
            (new ChildOptionGroupsSeeder)->run();
            (new LinkCategoryChildrenToOptionsSeeder)->run();
            (new SalonAndPharmacyOptionsSeeder)->run();

            sort($this->carriers);

            $this->assertSame(
                $this->carriers,
                $this->linked(),
                'a seeder handed «دفع مسبق» back to children outside shipping'
            );
        } finally {
            DB::rollBack();
        }
    }

    /** A merchant's own tick is his answer about himself, not the taxonomy's. */
    public function test_the_seeder_never_touches_a_merchants_own_tick(): void
    {
        $before = DB::table('option_user')->where('option_id', $this->optionId)->count();

        (new PrepaymentScopeSeeder)->run();

        $this->assertSame(
            $before,
            DB::table('option_user')->where('option_id', $this->optionId)->count(),
            'a merchant lost a tick he made himself'
        );
    }

    /** Re-running writes nothing new. */
    public function test_the_seeder_is_idempotent(): void
    {
        $before = $this->linked();

        (new PrepaymentScopeSeeder)->run();

        $this->assertSame($before, $this->linked());
    }
}
