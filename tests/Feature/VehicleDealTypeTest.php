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

    private const SHOWROOMS = [188, 53, 189]; // معرض سيارات، سيارات، معرض موتوسيكلات

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

            $this->assertSame(
                ['إيجار', 'بيع وشراء', 'تبديل'],
                $this->answersOf($childId),
                "«{$name}» cannot say what kind of deal it does"
            );
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

            $this->assertSame(['إيجار', 'بيع وشراء'], $this->answersOf($childId), "«{$name}» drifted");
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
        $this->assertSame(['إيجار', 'بيع وشراء', 'تبديل'], $this->answersOf(188));
    }
}
