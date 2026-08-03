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

        $this->assertContains('إيجار', $this->namesIn('نوع التعامل العقاري'));
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
     * The long groups that are deliberately NOT split, so a later cleanup does
     * not "tidy" a coherent list into headings for no reason.
     */
    public function test_the_single_question_groups_are_left_whole(): void
    {
        foreach (['ماركات السيارات' => 20, 'تخصصات طبية' => 30, 'الأنشطة الرياضية' => 30] as $group => $atLeast) {
            $this->assertGreaterThanOrEqual(
                $atLeast,
                count($this->namesIn($group)),
                "«{$group}» asks one question; splitting it would only add headings"
            );
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
