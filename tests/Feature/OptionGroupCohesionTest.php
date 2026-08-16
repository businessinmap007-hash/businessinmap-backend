<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * One group, one question.
 *
 * Long is fine — «ماركات السيارات» has 43 rows and asks one thing. Mixed is not:
 * a hotel's facilities list also held its view and its meal plan, so «نصف
 * إقامة» sat between «مسبح» and «سبا» as though it answered the same question.
 *
 * @see \Database\Seeders\OptionGroupSplitSeeder
 */
class OptionGroupCohesionTest extends TestCase
{
    /**
     * These run seeders. Without this trait they ran them against the LIVE dev
     * database and kept the writes — which is how «عيادة» lost eight merchants'
     * specialties and «صيدلية» lost «حقن» during a full-suite run.
     */
    use DatabaseTransactions;

    /** @return array<int,string> option names inside a group */
    private function namesIn(string $group): array
    {
        $id = DB::table('option_groups')->where('name_ar', $group)->value('id');

        if (! $id) {
            $this->markTestSkipped("The «{$group}» group is absent.");
        }

        return DB::table('options')->where('group_id', $id)->pluck('name_ar')->all();
    }

    public function test_hotel_facilities_no_longer_hold_a_view_or_a_meal_plan(): void
    {
        $facilities = $this->namesIn('مرافق الإقامة');

        $this->assertNotContains('نصف إقامة', $facilities, 'a meal plan is not a facility');
        $this->assertNotContains('إطلالة بحرية', $facilities, 'a view is not a facility');

        $this->assertContains('نصف إقامة', $this->namesIn('نظام الوجبات'));
        $this->assertContains('إطلالة بحرية', $this->namesIn('إطلالة الوحدة'));
    }

    /**
     * …and a fourth question the amenity list was answering: what the hotel
     * SELLS. A transfer is a driver, a car and a fare; it sat beside «مسبح»
     * where no price could reach it.
     *
     * «خدمة الغرف» is the control. Staff bring it, but the bill is the food and
     * the hotel prices that on a menu, so the row means «there is room service»
     * — a fact about the place. If it ever moves, the cut was made on «is
     * somebody involved» rather than «is this the thing being bought».
     */
    public function test_hotel_facilities_no_longer_hold_a_thing_the_hotel_sells(): void
    {
        $facilities = $this->namesIn('مرافق الإقامة');

        $this->assertNotContains('نقل من المطار', $facilities, 'a paid transfer is not an amenity');
        $this->assertContains('نقل من المطار', $this->namesIn('خدمات الفندق'));
        $this->assertContains('خدمة الغرف', $facilities, 'the bill for room service is the food');

        $this->assertSame(
            'line',
            DB::table('option_groups')->where('name_ar', 'خدمات الفندق')->value('price_role'),
            'the group exists so a hotel can price the transfer; a descriptive one would not'
        );
    }

    /**
     * The clinic case, and the one where the file that ruled on it had the
     * counter-example in its own hands.
     *
     * health_child_vocabularies.php declined to invent a modifier for medicine
     * because «a modifier exists where the SAME line prices two ways, and «كشف»
     * does not». True of every row it wrote — and «زيارة منزلية», sitting in its
     * descriptive list, is precisely a كشف priced two ways.
     *
     * It moved rather than being created, into the axis that already asks how a
     * service is supplied.
     */
    public function test_medical_facilities_no_longer_hold_the_one_row_that_is_a_price(): void
    {
        $this->assertNotContains('زيارة منزلية', $this->namesIn('تسهيلات ومرافق طبية'));
        $this->assertContains('زيارة منزلية', $this->namesIn('نمط تقديم الخدمة'));

        // And the five who answer it still do. A regroup that re-granted by list
        // instead of moving by id would have handed it to all seven.
        $carriers = DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('category_children_master as c', 'c.id', '=', 'cco.child_id')
            ->where('o.name_ar', 'زيارة منزلية')->distinct()->orderBy('c.name_ar')
            ->pluck('c.name_ar')->all();

        $this->assertNotContains('مراكز أشعة', $carriers, 'an X-ray suite does not travel');
        $this->assertContains('عيادة', $carriers);
        $this->assertContains('معمل تحاليل', $carriers);
    }

    /**
     * The hotel seeder builds an option when it cannot find one, and it used to
     * look only inside «مرافق الإقامة». Six of its fifteen rows have since been
     * split out of that group, so a single run would have created six duplicates
     * there — «Sea View (Stay)» beside «Sea View» — linked them to every hotel
     * child, and undone both splits at once. It is in no seeder list, which is
     * the only reason it had not fired.
     */
    public function test_the_hotel_seeder_cannot_rebuild_what_the_split_took_out(): void
    {
        $before = DB::table('options')->count();

        (new \Database\Seeders\CoworkingAndHotelUnitsSeeder)->run();

        $this->assertSame($before, DB::table('options')->count(), 'the amenity seeder created a duplicate of a split row');

        foreach (['إطلالة بحرية', 'نصف إقامة', 'نقل من المطار'] as $moved) {
            $this->assertCount(
                1,
                DB::table('options')->where('name_ar', $moved)->get(),
                "«{$moved}» exists twice; the split has been undone"
            );
        }
    }

    /**
     * «ادمج التسليم والاستلام» — owner, 2026-08-16, and the same disease one
     * level down: not a group asking two questions but a ROW restating two
     * answers standing beside it.
     *
     * «شحن وتوصيل» was «شحن» and «توصيل طلبات» joined by a واو, with all three
     * in one list. Dissolved, not deleted — the row survives in an inactive
     * group and every child it reached kept a way to say what it does.
     */
    public function test_a_compound_row_was_dissolved_into_the_rows_it_was_made_of(): void
    {
        $this->assertNotContains('شحن وتوصيل', $this->namesIn('التسليم والاستلام'));
        $this->assertContains('شحن', $this->namesIn('التسليم والاستلام'));
        $this->assertContains('توصيل طلبات', $this->namesIn('التسليم والاستلام'));

        $compound = (int) DB::table('options')->where('name_ar', 'شحن وتوصيل')->value('id');

        $this->assertSame(
            0,
            DB::table('category_child_option')->where('option_id', $compound)->count(),
            'a dissolved row still reaches children'
        );

        // Retired, not orphaned. `group_id = NULL` is what the vehicle seeder
        // does and what TaxonomyRedistributionTest forbids.
        $group = DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('o.id', $compound)->first(['g.name_ar', 'g.is_active']);

        $this->assertNotNull($group, 'the retired row was left groupless');
        $this->assertSame(0, (int) $group->is_active, 'a retired row must sit in a group no screen offers');
    }

    /**
     * …and the half that makes the merge stick.
     *
     * `child_option_groups.php` grants the whole fulfilment list per child, so
     * a dissolved id left in that array is handed straight back to every goods
     * child on the next run — which is how two seeders end up undoing each
     * other over one row.
     */
    public function test_the_per_child_map_no_longer_offers_the_dissolved_row(): void
    {
        $compound = (int) DB::table('options')->where('name_ar', 'شحن وتوصيل')->value('id');
        $map = require database_path('seeders/data/child_option_groups.php');

        $this->assertNotContains($compound, $map['groups']['fulfilment']['options']);

        foreach (['شحن', 'توصيل طلبات'] as $part) {
            $this->assertContains(
                (int) DB::table('options')->where('name_ar', $part)->value('id'),
                $map['groups']['fulfilment']['options'],
                "«{$part}» must stay in the map — it is what the compound dissolved into"
            );
        }
    }

    /**
     * Nobody went mute, and nobody got back what he had taken off.
     *
     * Eighteen of the compound's children had withdrawn «شحن» by hand while
     * still carrying a row that CONTAINS shipping — the contradiction the merge
     * resolves. The ledger blocks the grant, so they come out saying delivery
     * and not shipping, which is what the withdrawal meant. What must never
     * happen is a child left with no way to answer the group at all.
     *
     * Asserted against the MAP and not against a count. The first version of
     * this counted children and demanded at least as many as the compound had
     * reached, which read as an invariant and was a snapshot: reverting the
     * «شركات» pins took the fulfilment group off eleven service companies —
     * correctly, a money-transfer office has no delivery terms — and the count
     * fell to 106. The rule is «every child the map OFFERS this group can
     * answer it», and that survives a narrowing.
     */
    public function test_the_merge_left_no_child_unable_to_answer(): void
    {
        $map = require database_path('seeders/data/child_option_groups.php');
        $groupId = (int) DB::table('option_groups')->where('name_ar', 'التسليم والاستلام')->value('id');

        $mute = [];

        foreach (DB::table('category_parent_child as pc')->join('categories as c', 'c.id', '=', 'pc.parent_id')
            ->get(['c.slug', 'pc.child_id']) as $row) {
            if (! isset($map['root_defaults'][$row->slug])) {
                continue;
            }

            $bundle = $map['child_overrides']["{$row->slug}:{$row->child_id}"] ?? $map['root_defaults'][$row->slug];

            if (! in_array('fulfilment', $bundle, true)) {
                continue;
            }

            $answers = DB::table('category_child_option as co')
                ->join('options as o', 'o.id', '=', 'co.option_id')
                ->where('co.child_id', $row->child_id)->where('o.group_id', $groupId)->exists();

            if (! $answers) {
                $mute[] = "{$row->slug}:{$row->child_id}";
            }
        }

        $this->assertSame([], $mute, 'offered the delivery group and unable to answer it: ' . implode('، ', $mute));
    }

    public function test_property_types_no_longer_hold_a_deal_or_a_payment_term(): void
    {
        $property = $this->namesIn('عقارات وممتلكات');

        $this->assertNotContains('إيجار', $property, 'renting is a deal, not a property type');
        $this->assertNotContains('تقسيط', $property, 'instalments are a payment term');

        $this->assertContains('إيجار', $this->namesIn('نوع التعامل'));
        $this->assertContains('تقسيط', $this->namesIn('الدفع والسداد'));
    }

    public function test_furniture_pieces_no_longer_hold_a_style(): void
    {
        $this->assertNotContains('مودرن', $this->namesIn('أثاث وتشطيب منزلي'));
        $this->assertContains('مودرن', $this->namesIn('طراز الأثاث'));
    }

    public function test_training_fields_no_longer_hold_a_named_language(): void
    {
        $fields = $this->namesIn('مجالات التدريب');

        $this->assertNotContains('ياباني', $fields);
        $this->assertContains('ياباني', $this->namesIn('اللغات'));

        // the umbrella stays: teaching languages at all is a field of its own
        $this->assertContains('لغات', $fields);
    }

    /**
     * A marque list has no families to cut it into, so it stays whole. Guarded
     * so a later cleanup does not "tidy" a coherent list into headings.
     */
    public function test_the_marque_list_is_left_whole(): void
    {
        $this->assertGreaterThanOrEqual(
            20,
            count($this->namesIn('ماركات السيارات')),
            'a list of car marques asks one question and has no natural families'
        );
    }

    /**
     * Sports, specialties and lab tests are ONE list each.
     *
     * They were briefly cut into families. The screen showed why not: the parent
     * kept whatever belonged to no family, so it sat BESIDE its own children,
     * and a gym ended up with a fold called «الأنشطة الرياضية» holding only
     * جمباز. Folded back on the owner's call.
     */
    public function test_the_three_domain_lists_are_each_one_group(): void
    {
        foreach (['الأنشطة الرياضية' => 40, 'تخصصات طبية' => 40, 'التحاليل الطبية' => 25] as $group => $atLeast) {
            $this->assertGreaterThanOrEqual(
                $atLeast,
                count($this->namesIn($group)),
                "«{$group}» is read as one list; its members must not be scattered across families"
            );
        }

        // the families are gone, not merely emptied
        foreach (['رياضات قتالية', 'تخصصات جراحية', 'تحاليل الدم والكيمياء'] as $family) {
            $this->assertNull(
                DB::table('option_groups')->where('name_ar', $family)->value('id'),
                "«{$family}» was folded back; an empty leftover group clutters every picker"
            );
        }
    }

    /**
     * The reason the split was unsafe before.
     *
     * Both remodel seeders looked an option up by (group_id, name_ar). Once a
     * row was re-filed into a family the lookup missed it and INSERTED it again,
     * so a single re-run would have duplicated 45 sports and 41 specialties.
     * They now key on the globally unique `name_en`, falling back to `name_ar`,
     * and leave a found row in whatever group it is in.
     */
    public function test_re_running_the_remodel_seeders_creates_no_duplicates(): void
    {
        $before = DB::table('options')->count();

        DB::beginTransaction();

        try {
            (new \Database\Seeders\SportsRemodelSeeder)->run();
            (new \Database\Seeders\HealthRemodelSeeder)->run();

            $this->assertSame(
                $before,
                DB::table('options')->count(),
                'a re-filed option must be found where it now lives, not created again'
            );

            // and a re-run must not scatter them back out of their one group
            $this->assertContains('كرة قدم', $this->namesIn('الأنشطة الرياضية'));
            $this->assertContains('جراحة عامة', $this->namesIn('تخصصات طبية'));
        } finally {
            DB::rollBack();
        }
    }

    /** A gym hosts no water polo, and a pool hosts no parkour. */
    public function test_a_venue_is_only_offered_the_sports_it_can_host(): void
    {
        $gym = DB::table('category_children_master')->where('name_ar', 'جيم')->value('id');
        $pool = DB::table('category_children_master')->where('name_ar', 'حمام سباحة')->value('id');

        if (! $gym || ! $pool) {
            $this->markTestSkipped('The sports taxonomy is absent.');
        }

        // Scoping is per CHILD, not per group — folding the families back did
        // not widen anyone's pool, and this is what proves it.
        $offered = fn ($childId) => DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', $childId)
            ->where('g.name_ar', 'الأنشطة الرياضية')
            ->pluck('o.name_ar')
            ->all();

        $gymSports = $offered($gym);
        $poolSports = $offered($pool);

        $this->assertNotEmpty($gymSports, 'a gym must still be offered its own sports');
        $this->assertNotContains('كرة ماء', $gymSports);
        $this->assertNotContains('كرة قدم', $gymSports);
        $this->assertContains('كارديو', $gymSports);

        $this->assertNotContains('باركور', $poolSports);
        $this->assertContains('سباحة', $poolSports);
    }
}
