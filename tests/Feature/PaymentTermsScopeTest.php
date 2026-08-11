<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «الدفع والسداد استخدم منها كاش وتقسيط اما الاخرين فسأحددهم يدويا» — owner,
 * 2026-08-10.
 *
 * The group's per-root grant had exactly the wrong member: «تقسيط بدون فوائد»
 * was the ONE option handed out, which is why it reached 297 children while كاش
 * reached 95 and تقسيط 84. Interest-free instalments are a commercial claim only
 * the merchant can make; كاش and تقسيط are what every trade can answer.
 */
class PaymentTermsScopeTest extends TestCase
{
    use DatabaseTransactions;

    private const GROUP = 'الدفع والسداد';

    private function optionId(string $nameAr): int
    {
        return (int) DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', self::GROUP)->where('o.name_ar', $nameAr)
            ->value('o.id');
    }

    /**
     * DISTINCT children, not rows. A child may hold a shared row and a
     * root-scoped one for the same option, so counting rows made كاش and تقسيط
     * look unequal (229 vs 231) while every child carried both.
     */
    /** @return array<int,int> */
    private function childrenWith(string $nameAr): array
    {
        return DB::table('category_child_option')->where('option_id', $this->optionId($nameAr))
            ->distinct()->pluck('child_id')->map(fn ($id) => (int) $id)->all();
    }

    private function childCount(string $nameAr): int
    {
        return DB::table('category_child_option')->where('option_id', $this->optionId($nameAr))
            ->distinct()->count('child_id');
    }

    /** The two answers every trade can give, on the same children. */
    public function test_cash_and_instalment_are_the_two_granted_terms(): void
    {
        $this->assertGreaterThan(100, $this->childCount('كاش'));

        /*
         * Granted TOGETHER, and taken off one at a time.
         *
         * The map hands both or neither, and this used to assert equal counts.
         * The owner then unticked «كاش» from «تحويل أموال» by hand on
         * 2026-08-11 20:06 — a money-transfer office quoting a cash price for
         * moving cash is a fair thing to remove — and equality is his answer to
         * overrule, not the map's promise.
         *
         * What the map promises is that no child is offered one without the
         * other UNLESS the difference is written in the withdrawal record.
         */
        $cash = $this->childrenWith('كاش');
        $instalment = $this->childrenWith('تقسيط');

        $lopsided = array_merge(
            array_diff($cash, $instalment),
            array_diff($instalment, $cash)
        );

        $unaccounted = array_values(array_filter(
            $lopsided,
            fn ($childId) => ! DB::table(\App\Services\Catalog\ChildOptionDecisions::TABLE)
                ->where('child_id', $childId)
                ->whereIn('option_id', [$this->optionId('كاش'), $this->optionId('تقسيط')])
                ->where('kind', \App\Services\Catalog\ChildOptionDecisions::WITHDRAWN)
                ->exists()
        ));

        $this->assertSame(
            [],
            $unaccounted,
            'these carry one payment term without the other and nobody decided that: #'
                . implode(', #', $unaccounted)
        );
    }

    /** Hand-set only: no child carries it because a map granted it. */
    public function test_interest_free_instalments_are_no_longer_granted_wholesale(): void
    {
        $option = $this->optionId('تقسيط بدون فوائد');

        $this->assertGreaterThan(0, $option, 'the option was retired — it must stay selectable');

        $ticked = DB::table('option_user')->where('option_id', $option)->count();

        $this->assertSame(
            $ticked > 0 ? $this->childCount('تقسيط بدون فوائد') : 0,
            $this->childCount('تقسيط بدون فوائد'),
            'it is still being handed out in bulk'
        );

        if ($ticked === 0) {
            $this->assertSame(0, $this->childCount('تقسيط بدون فوائد'));
        }
    }

    /** It stays a live option in its own group — withdrawn, not retired. */
    public function test_the_option_itself_is_untouched(): void
    {
        $row = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('o.name_ar', 'تقسيط بدون فوائد')
            ->first(['o.id', 'g.name_ar']);

        $this->assertNotNull($row, 'the option lost its group');
        $this->assertSame(self::GROUP, $row->name_ar);
    }

    /**
     * «دفع مسبق» is hand-set too, but by a seeder the owner asked for — it
     * belongs to carriers. Sweeping it up with the others would have undone a
     * scope, not removed noise.
     */
    public function test_prepayment_keeps_its_carrier_scope(): void
    {
        $this->assertGreaterThan(0, $this->childCount('دفع مسبق'));

        $shipping = (int) DB::table('categories')->where('slug', 'shipping-delivery')->value('id');

        $outside = DB::table('category_child_option as cco')
            ->whereNotExists(fn ($q) => $q->from('category_parent_child as p')
                ->whereColumn('p.child_id', 'cco.child_id')->where('p.parent_id', $shipping))
            ->where('cco.option_id', $this->optionId('دفع مسبق'))
            ->count();

        $this->assertSame(0, $outside, '«دفع مسبق» leaked outside شحن وتوصيل');
    }

    /** The broad seeder does not hand it back on its next run. */
    public function test_the_seeder_does_not_restore_it(): void
    {
        $before = $this->childCount('تقسيط بدون فوائد');

        $this->artisan('db:seed', ['--class' => 'ChildOptionGroupsSeeder', '--no-interaction' => true])->run();
        $this->artisan('db:seed', ['--class' => 'HospitalityOptionRestoreSeeder', '--no-interaction' => true])->run();

        $this->assertSame($before, $this->childCount('تقسيط بدون فوائد'));
    }
}
