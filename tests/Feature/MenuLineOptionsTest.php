<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Every menu heading now lives in the options vocabulary, and a child carries
 * exactly the bands ITS OWN config allows.
 *
 * The bug this exists to stop: linking per BRANCH instead of per TYPE. A
 * supermarket's config allows three restaurant-menu types (ساندوتشات and the
 * two drink bands) out of fourteen, and branch-level linking handed it
 * «مشويات» and «وجبات أطفال» as headings its merchants can never sell.
 *
 * @see \Database\Seeders\MenuLineOptionsSeeder
 */
class MenuLineOptionsTest extends TestCase
{
    private function menuServiceId(): int
    {
        return (int) DB::table('platform_services')->where('key', 'menu')->value('id');
    }

    private function groupId(): int
    {
        return (int) DB::table('option_groups')->where('name_ar', 'بنود المنيو')->value('id');
    }

    /** @return array<int,string> the option names of this group a child carries */
    private function bandsOf(int $childId): array
    {
        return DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->where('co.child_id', $childId)
            ->where('o.group_id', $this->groupId())
            ->pluck('o.name_ar')
            ->all();
    }

    /**
     * The bands a child is meant to carry, from the frozen map.
     *
     * They used to be derived from the live item types. Those have since
     * collapsed to five coarse kinds that say which SURFACE a child sells on,
     * so data/menu_line_bands.php is the source of truth now — deriving them
     * from the types again would wipe all 44 and leave five.
     *
     * @return array<int,string>
     */
    private function expectedBands(string $childName): array
    {
        $map = require database_path('seeders/data/menu_line_bands.php');

        return $map['children'][$childName] ?? [];
    }

    /** The group must be a line group, or none of it is a heading at all. */
    public function test_the_menu_bands_are_line_options(): void
    {
        $group = DB::table('option_groups')->where('name_ar', 'بنود المنيو')->first();

        $this->assertNotNull($group, 'the «بنود المنيو» group is missing');
        $this->assertSame(OptionGroup::ROLE_LINE, (string) $group->price_role);
        $this->assertSame(1, (int) $group->is_active);
    }

    /** Every child on the menu can now name what it sells. */
    public function test_no_menu_child_is_left_without_a_line_option(): void
    {
        $children = DB::table('category_platform_services as l')
            ->join('category_children_master as m', 'm.id', '=', 'l.child_id')
            ->where('l.platform_service_id', $this->menuServiceId())
            ->where('l.is_active', 1)
            ->distinct()
            ->pluck('m.name_ar', 'm.id');

        $this->assertNotEmpty($children);

        foreach ($children as $id => $name) {
            $has = DB::table('category_child_option as co')
                ->join('options as o', 'o.id', '=', 'co.option_id')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->where('co.child_id', $id)
                ->where('g.price_role', OptionGroup::ROLE_LINE)
                ->exists();

            $this->assertTrue($has, "«{$name}» has nothing it can call a heading");
        }
    }

    /**
     * The bug. A supermarket allows three of the fourteen restaurant bands, and
     * must carry three — not fourteen.
     */
    public function test_a_child_carries_only_the_bands_the_map_allows(): void
    {
        // Only the children the seeder actually manages: it reconciles those
        // OFFERED the menu service, so a child whose link the owner switched
        // off is out of its reach and may hold whatever he put there by hand.
        $children = DB::table('category_platform_services as l')
            ->join('category_child_option as co', 'co.child_id', '=', 'l.child_id')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->where('l.platform_service_id', $this->menuServiceId())
            ->where('l.is_active', 1)
            ->where('o.group_id', $this->groupId())
            ->distinct()
            ->pluck('co.child_id');

        $this->assertNotEmpty($children);

        foreach ($children as $childId) {
            $name = DB::table('category_children_master')->where('id', $childId)->value('name_ar');
            $allowed = $this->expectedBands((string) $name);

            foreach ($this->bandsOf((int) $childId) as $band) {
                $this->assertContains($band, $allowed, "«{$name}» carries «{$band}», which its activity does not sell");
            }
        }
    }

    /** Named directly, because it is the case that was wrong. */
    public function test_a_supermarket_is_not_offered_grills(): void
    {
        $id = (int) DB::table('category_children_master')->where('name_ar', 'سوبر ماركت')->value('id');

        if (! $id) {
            $this->markTestSkipped('No «سوبر ماركت» child.');
        }

        $bands = $this->bandsOf($id);

        $this->assertNotEmpty($bands);
        $this->assertNotContains('مشويات', $bands);
        $this->assertNotContains('وجبات أطفال', $bands);
        $this->assertContains('خضار وفاكهة', $bands);
    }

    /**
     * A child that already sells by a richer vocabulary keeps it — «غرفة نوم»
     * is a better heading than the item type «قطعة أثاث», and the seeder must
     * never trade down.
     */
    public function test_a_child_with_its_own_vocabulary_is_left_alone(): void
    {
        $id = (int) DB::table('category_children_master')->where('name_ar', 'آثاث')->value('id');

        if (! $id) {
            $this->markTestSkipped('No «آثاث» child.');
        }

        $this->assertEmpty($this->bandsOf($id), '«آثاث» was given item-type bands over its own furniture lines');

        $own = DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', $id)
            ->where('g.name_ar', 'أثاث وتشطيب منزلي')
            ->pluck('o.name_ar');

        $this->assertContains('غرفة نوم', $own->all());
    }

    /** Re-running must repair, not accumulate. */
    public function test_the_seeder_is_idempotent(): void
    {
        $count = fn () => [
            DB::table('options')->count(),
            DB::table('option_groups')->count(),
            DB::table('category_child_option')->count(),
        ];

        $before = $count();

        DB::beginTransaction();

        try {
            (new \Database\Seeders\MenuLineOptionsSeeder)->run();

            $this->assertSame($before, $count());
        } finally {
            DB::rollBack();
        }
    }

    /**
     * The group must stay named in option_price_roles.php, or the next run of
     * OptionPriceRolesSeeder pushes it back to descriptive and every heading
     * silently stops being one.
     */
    public function test_the_group_survives_the_price_roles_seeder(): void
    {
        DB::beginTransaction();

        try {
            (new \Database\Seeders\OptionPriceRolesSeeder)->run();

            $this->assertSame(
                OptionGroup::ROLE_LINE,
                (string) DB::table('option_groups')->where('name_ar', 'بنود المنيو')->value('price_role'),
                'the price-roles seeder demoted the menu bands'
            );
        } finally {
            DB::rollBack();
        }
    }
}
