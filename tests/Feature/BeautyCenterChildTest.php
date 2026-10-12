<?php

namespace Tests\Feature;

use App\Services\BookingShapes;
use Database\Seeders\BeautyCenterChildSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «مركز تجميل وسبا»: the trade the salon root had no door for, mirrored on the women's hairdresser, booked by
 * appointment — and the men's barber trimmed back to what a barber sells. Rolls back.
 */
class BeautyCenterChildTest extends TestCase
{
    use DatabaseTransactions;

    private function lineNames(int $childId): array
    {
        return DB::table('category_child_option as l')
            ->join('options as o', 'o.id', '=', 'l.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('l.child_id', $childId)
            ->where('g.name_ar', 'خدمات الكوافير والتجميل')
            ->pluck('o.name_ar')->unique()->sort()->values()->all();
    }

    private function runSeeder(): int
    {
        $this->seed(BeautyCenterChildSeeder::class);

        return (int) DB::table('category_children_master')->where('name_ar', 'مركز تجميل وسبا')->value('id');
    }

    public function test_the_centre_is_a_child_of_the_salon_root_with_the_fixed_id_the_pattern_map_names(): void
    {
        $id = $this->runSeeder();

        $this->assertSame(BeautyCenterChildSeeder::CHILD_ID, $id);
        $this->assertSame(443, (int) DB::table('category_parent_child')->where('child_id', $id)->value('parent_id'));

        // booking_patterns.php names children by id: an unlisted child has its pattern stripped on the next run
        $map = require base_path('database/seeders/data/booking_patterns.php');
        $this->assertSame(['appointment'], $map[$id] ?? null);
    }

    public function test_it_sells_the_salon_services_minus_mens_barbering_plus_massage_and_facials(): void
    {
        $lines = $this->lineNames($this->runSeeder());

        $this->assertContains('مكياج', $lines);
        $this->assertContains('مانيكير وباديكير', $lines);
        $this->assertContains('تركيب أظافر', $lines);
        $this->assertContains('حمام مغربي وسبا', $lines);
        $this->assertContains('مساج وتدليك', $lines);
        $this->assertContains('تنظيف بشرة وفيشل', $lines);

        $this->assertNotContains('حلاقة ذقن وتهذيب', $lines);
        $this->assertNotContains('عناية بالشعر للرجال', $lines);
    }

    public function test_the_new_services_are_rows_of_the_same_priced_group(): void
    {
        $this->runSeeder();

        $group = (int) DB::table('option_groups')->where('name_ar', 'خدمات الكوافير والتجميل')->value('id');

        foreach (['مساج وتدليك', 'تنظيف بشرة وفيشل'] as $name) {
            $this->assertSame($group, (int) DB::table('options')->where('name_ar', $name)->value('group_id'));
        }
    }

    public function test_it_is_booked_by_appointment_with_the_business_itself(): void
    {
        $id = $this->runSeeder();

        $config = json_decode((string) DB::table('category_service_configs as c')
            ->join('platform_services as s', 's.id', '=', 'c.platform_service_id')
            ->where('s.key', 'booking')->where('c.child_id', $id)->where('c.is_active', 1)->value('c.config'), true);

        $this->assertSame('appointment', $config['booking_pattern']);
        $this->assertSame(['booking_appointment'], $config['allowed_item_types']);
        $this->assertFalse($config['requires_bookable_item']);
        $this->assertSame('appointment', optional(app(BookingShapes::class)->forChild($id))->code ?? 'appointment');
    }

    public function test_the_mens_barber_loses_lashes_nails_and_henna_for_good(): void
    {
        $mens = (int) DB::table('category_children_master')->where('name_ar', 'كوافير رجالي')->value('id');

        // put them back by hand, as an older seeder run could have
        $ids = DB::table('options')->whereIn('name_ar', ['تركيب رموش', 'تركيب أظافر', 'حنة ونقش'])->pluck('id');
        foreach ($ids as $optionId) {
            DB::table('category_child_option')->insertOrIgnore(['child_id' => $mens, 'category_id' => 0, 'option_id' => $optionId, 'reorder' => 0]);
        }

        $this->runSeeder();

        $lines = $this->lineNames($mens);
        $this->assertNotContains('تركيب رموش', $lines);
        $this->assertNotContains('تركيب أظافر', $lines);
        $this->assertNotContains('حنة ونقش', $lines);

        // what a barber sells is still there
        $this->assertContains('قص شعر', $lines);
        $this->assertContains('حلاقة ذقن وتهذيب', $lines);

        // the ledger remembers, so no later seeder hands them back
        $withdrawn = DB::table('category_child_option_decisions')
            ->where('child_id', $mens)->where('kind', 'withdrawn')->whereIn('option_id', $ids)->count();
        $this->assertSame(3, $withdrawn);

        // and the women's hairdresser keeps them
        $ladies = $this->lineNames((int) DB::table('category_children_master')->where('name_ar', 'كوافير حريمى')->value('id'));
        $this->assertContains('تركيب رموش', $ladies);
    }

    public function test_it_is_idempotent(): void
    {
        $first = $this->runSeeder();
        $links = DB::table('category_child_option')->where('child_id', $first)->count();

        $this->seed(BeautyCenterChildSeeder::class);

        $this->assertSame($links, DB::table('category_child_option')->where('child_id', $first)->count());
        $this->assertSame(1, DB::table('category_children_master')->where('name_ar', 'مركز تجميل وسبا')->count());
        $this->assertSame(2, DB::table('options')->whereIn('name_ar', ['مساج وتدليك', 'تنظيف بشرة وفيشل'])->count());
    }
}
