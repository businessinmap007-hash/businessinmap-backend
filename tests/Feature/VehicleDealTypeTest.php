<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use App\Services\MerchantOfferingVocabulary;
use Database\Seeders\VehicleDealTypeSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «بيع أم إيجار» is the same question about a flat and about a car.
 *
 * Real estate had the axis and vehicles did not, so a showroom that also rents
 * could price the sale and nothing else — and «تأجير سيارات» exists nowhere in
 * the taxonomy either, so renting had no home at all. The group stopped being
 * about property (renamed «نوع التعامل») and the three vehicle showrooms were
 * given it, rather than a second group repeating بيع/إيجار in other words.
 *
 * A modifier makes the rental priceable and listable; it does not make a NAMED
 * car reservable on given dates — that still needs registered units, the way a
 * hotel names room 101.
 */
class VehicleDealTypeTest extends TestCase
{
    use DatabaseTransactions;

    /*
     * «سيارات» #53 was folded into «معرض سيارات» #188 on 2026-08-17 and is
     * retired — owner: «خليه معرض سيارات ونفذ الطى والنقل». Two showrooms left,
     * and #188 now stands under «سيارات» rather than «معارض».
     */
    private const SHOWROOMS = [188, 189]; // معرض سيارات، معرض موتوسيكلات

    /*
     * «بيع وشراء» was split into two rows for VEHICLES ONLY on the same day —
     * «التقسيم على السيارات وحدها». A showroom that BUYS your car is making a
     * different offer from one that sells you one, and a buyer filters on one
     * of the two. Real estate, gold, furniture and clothing keep the merged row
     * and the test below holds them to it.
     */
    private const VEHICLE_DEALS = ['إيجار', 'بيع', 'تبديل', 'شراء'];

    /*
     * #238 «تسويق عقاري» folded into #517 «مكتب عقاري» on 2026-08-12 (owner):
     * one trade under two names, and #517 is the platform's declared
     * `business_migration_target`. It reaches no root, so a link table cannot
     * keep anything away from it.
     */
    private const REAL_ESTATE = [517, 518, 522];

    private function group(): ?object
    {
        return DB::table('option_groups')->where('name_ar', 'نوع التعامل')->first();
    }

    /** @return array<int,string> option names this child may answer in the group */
    private function answersOf(int $childId): array
    {
        return DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->where('co.child_id', $childId)
            ->where('o.group_id', (int) $this->group()->id)
            ->distinct()
            ->pluck('o.name_ar')
            ->sort()
            ->values()
            ->all();
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->group()) {
            $this->markTestSkipped('The deal-type group is not in this database.');
        }
    }

    /**
     * The rename is the identity: option_groups has no key column, so every
     * seeder resolves this group by name_ar. A stale «نوع التعامل العقاري» in
     * any data file makes the next run create a SECOND group and move بيع
     * وشراء / إيجار into it, splitting one axis in two.
     */
    public function test_no_seeder_still_calls_it_the_property_deal_type(): void
    {
        foreach (glob(database_path('seeders/data/*.php')) as $file) {
            $source = file_get_contents($file);

            // The vehicle seeder names the old string on purpose, to find it.
            $this->assertStringNotContainsString(
                "'نوع التعامل العقاري'",
                $source,
                basename($file) . ' would recreate the group under its old name'
            );
        }

        $this->assertSame(
            1,
            DB::table('option_groups')->whereIn('name_ar', ['نوع التعامل', 'نوع التعامل العقاري'])->count(),
            'the deal-type axis exists twice'
        );
    }

    /** It changes a price, it is never the thing bought. */
    public function test_the_deal_type_is_a_modifier(): void
    {
        $this->assertSame(OptionGroup::ROLE_MODIFIER, $this->group()->price_role);
    }

    /** Every vehicle showroom can now say sell / rent / trade-in. */
    public function test_each_vehicle_showroom_carries_all_three_deals(): void
    {
        foreach (self::SHOWROOMS as $childId) {
            $name = DB::table('category_children_master')->where('id', $childId)->value('name_ar');

            $this->assertEqualsCanonicalizing(
                self::VEHICLE_DEALS,
                $this->answersOf($childId),
                "«{$name}» cannot say what kind of deal it does"
            );

            // The merged row is the thing the split replaced — holding both
            // would ask the same question twice.
            $this->assertNotContains('بيع وشراء', $this->answersOf($childId));
        }
    }

    /**
     * A group is shared; a CHILD's view of it is not. The trade-in is a car
     * showroom's third deal and has no meaning for a property office, so the
     * link table — not a second group — is what keeps it away from them.
     */
    public function test_real_estate_keeps_its_pair_and_never_sees_the_trade_in(): void
    {
        foreach (self::REAL_ESTATE as $childId) {
            $name = DB::table('category_children_master')->where('id', $childId)->value('name_ar');

            /*
             * «مكتب عقاري» #517 gained «تبديل» by hand on 2026-08-16 18:46, and
             * a بدل — شقة بشقة through a broker — is a real Egyptian deal. The
             * pair is what the FILE grants; a third answer the owner pinned is
             * his, so it is allowed only when the ledger says he put it there.
             *
             * What stays absolute is the direction: nothing may DRIFT in. A row
             * with no decision behind it still fails.
             */
            $pinned = DB::table(\App\Services\Catalog\ChildOptionDecisions::TABLE . ' as d')
                ->join('options as o', 'o.id', '=', 'd.option_id')
                ->where('d.child_id', $childId)
                ->where('d.kind', \App\Services\Catalog\ChildOptionDecisions::PINNED)
                ->pluck('o.name_ar')->all();

            /*
             * The pair is THREE rows since 2026-08-17 — «والتقسيم على الكل».
             * «بيع وشراء» was one word making two claims, and a property office
             * that also buys is a different offer from one that only lists. It
             * is the private seller the split is really for: «مالك عقار» ticks
             * «بيع» and leaves «شراء» alone, which the merged row made
             * impossible to say.
             */
            $unexplained = array_values(array_diff($this->answersOf($childId), ['إيجار', 'بيع', 'شراء'], $pinned));

            $this->assertSame([], $unexplained, "«{$name}» drifted: " . implode('، ', $unexplained));

            /*
             * The ledger has the last word in BOTH directions.
             *
             * The paragraph above says what the split is for — «مالك عقار»
             * ticks «بيع» and leaves «شراء» alone — and then this loop used to
             * demand both halves of every child, which forbade the one thing
             * the split was built to allow. An owner did exactly that on
             * 2026-08-18 and the suite called it a loss.
             *
             * A half may be absent only when a withdrawal says who took it. The
             * direction that stays absolute is unchanged: silence is still a
             * loss, because a row that vanishes with nothing behind it is drift.
             */
            $withdrawn = DB::table(\App\Services\Catalog\ChildOptionDecisions::TABLE . ' as d')
                ->join('options as o', 'o.id', '=', 'd.option_id')
                ->where('d.child_id', $childId)
                ->where('d.kind', \App\Services\Catalog\ChildOptionDecisions::WITHDRAWN)
                ->pluck('o.name_ar')->all();

            foreach (['بيع', 'شراء'] as $half) {
                if (in_array($half, $withdrawn, true)) {
                    continue;
                }

                $this->assertContains($half, $this->answersOf($childId), "«{$name}» lost «{$half}»");
            }

            // And the word they came from reaches nobody.
            $this->assertNotContains('بيع وشراء', $this->answersOf($childId));
        }
    }

    /** What the merchant actually opens: the axis beside the brand. */
    public function test_a_showroom_is_offered_the_deal_type_when_pricing(): void
    {
        $business = DB::table('users')
            ->where('type', 'business')
            ->where('category_child_id', 188)
            ->first(['id']);

        if (! $business) {
            $this->markTestSkipped('No live car showroom to price with.');
        }

        $vocabulary = app(MerchantOfferingVocabulary::class)->for((int) $business->id, 188, 21);

        $this->assertArrayHasKey('نوع المركبة', $vocabulary['lines'], 'the priced line is gone');
        $this->assertArrayHasKey('نوع التعامل', $vocabulary['modifiers']);
        $this->assertArrayHasKey('ماركات السيارات', $vocabulary['modifiers']);

        $deals = collect($vocabulary['modifiers']['نوع التعامل'])->pluck('name_ar')->all();

        $this->assertContains('إيجار', $deals, 'renting still cannot be priced');
    }

    /** Re-running must not add a fourth deal or a duplicate link. */
    public function test_the_seeder_is_idempotent(): void
    {
        $before = DB::table('category_child_option')->count();

        (new VehicleDealTypeSeeder)->run();

        $this->assertSame($before, DB::table('category_child_option')->count());
        $this->assertEqualsCanonicalizing(self::VEHICLE_DEALS, $this->answersOf(188));
    }
}
