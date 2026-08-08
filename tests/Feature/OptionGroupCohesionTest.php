<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
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
