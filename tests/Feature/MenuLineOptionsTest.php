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
     * The option ids that mirror the item types a child may list.
     *
     * Matched the way the seeder matches — by the type's names, not by the
     * option's label. `options.name_en` is uniquely indexed platform-wide, and
     * two menu types are both called "Seafood" in English (`seafood` and
     * `seafood_grocery`), so ONE option necessarily serves both. Comparing
     * Arabic labels would call that a violation; it is the schema's answer to
     * a name the taxonomy reused.
     *
     * @return array<int,int>
     */
    private function allowedOptionIds(int $childId): array
    {
        $keys = DB::table('category_service_configs')
            ->where('child_id', $childId)
            ->where('platform_service_id', $this->menuServiceId())
            ->where('is_active', 1)
            ->pluck('config')
            ->flatMap(fn ($c) => (json_decode((string) $c, true) ?: [])['allowed_item_types'] ?? [])
            ->unique();

        $types = DB::table('platform_service_item_types')
            ->where('platform_service_id', $this->menuServiceId())
            ->whereIn('key', $keys)
            ->get(['name_ar', 'name_en']);

        return DB::table('options')
            ->whereIn('name_en', $types->pluck('name_en'))
            ->orWhereIn('name_ar', $types->pluck('name_ar'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
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
    public function test_a_child_carries_only_the_bands_its_config_allows(): void
    {
        $children = DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->where('o.group_id', $this->groupId())
            ->distinct()
            ->pluck('co.child_id');

        $this->assertNotEmpty($children);

        foreach ($children as $childId) {
            $name = DB::table('category_children_master')->where('id', $childId)->value('name_ar');
            $allowed = $this->allowedOptionIds((int) $childId);

            $held = DB::table('category_child_option')
                ->where('child_id', $childId)
                ->whereIn('option_id', DB::table('options')->where('group_id', $this->groupId())->select('id'))
                ->pluck('option_id');

            foreach ($held as $optionId) {
                $band = DB::table('options')->where('id', $optionId)->value('name_ar');

                $this->assertContains(
                    (int) $optionId,
                    $allowed,
                    "«{$name}» carries «{$band}», which its activity may not list"
                );
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
