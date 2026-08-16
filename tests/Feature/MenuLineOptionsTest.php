<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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
    /**
     * These run seeders. Without this trait they ran them against the LIVE dev
     * database and kept the writes — which is how «عيادة» lost eight merchants'
     * specialties and «صيدلية» lost «حقن» during a full-suite run.
     */
    use DatabaseTransactions;

    private function menuServiceId(): int
    {
        return (int) DB::table('platform_services')->where('key', 'menu')->value('id');
    }

    private function groupId(): int
    {
        return (int) DB::table('option_groups')->where('name_ar', 'بنود المنيو')->value('id');
    }

    /**
     * The seeder's whole vocabulary, which has lived in four groups since
     * 2026-08-10 (MenuBandSplitSeeder). One declaration, one seeder, four
     * drawers — a supermarket's «خضار وفاكهة» is the same band it always was.
     *
     * @return array<int,int>
     */
    private function familyGroupIds(): array
    {
        // The transitive family: «بنود المنيو», the four it split into, and the
        // five that «أقسام السوبر ماركت» split into after that. Mirrors
        // MenuLineOptionsSeeder::ownFamily(), and must keep mirroring it — a
        // band that leaves the list here reads as belonging to nobody.
        $names = array_merge(
            ['بنود المنيو'],
            array_column((require database_path('seeders/data/menu_band_split.php'))['groups'], 'name_ar'),
            array_keys((require database_path('seeders/data/grocery_aisle_split.php'))['groups'])
        );

        return DB::table('option_groups')->whereIn('name_ar', $names)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * The option names of this seeder's vocabulary that a child carries.
     *
     * The aisle split created words of its OWN inside the family's groups —
     * «أسماك ومأكولات بحرية طازجة» is one — and those were never menu bands and
     * are not in menu_line_bands.php. Counting them here would report this
     * seeder as carrying something it has never heard of.
     *
     * @return array<int,string>
     */
    private function bandsOf(int $childId): array
    {
        $notBands = array_keys((require database_path('seeders/data/grocery_aisle_split.php'))['new_options'] ?? []);

        return DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->where('co.child_id', $childId)
            ->whereIn('o.group_id', $this->familyGroupIds())
            ->when($notBands !== [], fn ($q) => $q->whereNotIn('o.name_ar', $notBands))
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

            /*
             * …unless the owner took its last heading off by hand.
             *
             * ── #189 «معرض موتوسيكلات», 2026-08-16 17:48 ──
             *
             * The owner withdrew «مركبة معروضة» from it by hand, which was its only
             * line, and the withdrawal is right: «the thing on display» is a placeholder
             * word, not a heading anybody prices under. What it leaves is the shape this
             * whole sweep has been closing — «ماركات الموتوسيكلات» is a modifier and
             * there is now nothing under it.
             *
             * Its twin «معرض سيارات» prices on «نوع المركبة» — سيدان، SUV، بيك أب — and
             * a motorcycle answers none of those, so there is no list to borrow. Two
             * ways out and both are the owner's: a motorcycle TYPE list of its own
             * (سكوتر، رياضي، توك توك…), which is new words, or the brand list promoted
             * to `line`, which is one role change. Zero merchants stand on it, so
             * nothing is broken today.
             *
             * Recorded here rather than guessed at, and the guard is kept sharp: the
             * exemption is granted only while the WITHDRAWAL is on record. Put the row
             * back and the child leaves this list; let a seeder strip a line without a
             * decision behind it and the test still fails.
             */
            if (! $has && $this->lastLineWasWithdrawn((int) $id)) {
                continue;
            }

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
            ->whereIn('o.group_id', $this->familyGroupIds())
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

    /**
     * A child that grows a second vocabulary keeps its bands.
     *
     * «مني ماركت» #185 was given the poultry list by hand on 2026-08-16 03:09.
     * That made hasOtherLineGroup() true, which set `$wanted` to nothing, which
     * made `$drop` its entire holding — the next full seed would have taken all
     * twenty-four of its aisle words: خضار وفاكهة، ألبان وبيض، مخبوزات، منظفات,
     * the whole shop, because it had learned to say «بط».
     *
     * The guard against it was a hard-coded list of the groups «بنود المنيو»
     * was split into, which holds exactly until somebody adds a group that is
     * not on it. Somebody always does. Skipping the child is the fix that does
     * not need the list to be complete: not trading down is a reason to write
     * nothing, never a reason to delete.
     */
    public function test_a_child_with_its_own_vocabulary_is_skipped_not_emptied(): void
    {
        $childId = (int) DB::table('category_children_master')->where('name_ar', 'مني ماركت')->value('id');

        $bands = fn () => DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', $childId)
            ->whereIn('g.name_ar', ['بنود المنيو', 'أقسام الطازج واللحوم', 'أقسام البقالة الجافة',
                'أقسام المشروبات', 'أقسام المنزل والعناية', 'بنود المخبوزات والحلويات'])
            ->count();

        $before = $bands();

        $this->assertGreaterThan(10, $before, '«مني ماركت» has already lost its aisles');

        // The condition that arms the bug: a line group outside the band family.
        $this->assertTrue(
            DB::table('category_child_option as co')
                ->join('options as o', 'o.id', '=', 'co.option_id')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->where('co.child_id', $childId)
                ->where('g.name_ar', 'أنواع الدواجن والطيور')
                ->exists(),
            'the case this guards is gone — re-point it at whichever child now carries a second vocabulary'
        );

        DB::beginTransaction();

        try {
            (new \Database\Seeders\MenuLineOptionsSeeder)->run();

            $this->assertSame($before, $bands(), 'the seeder emptied a child instead of leaving it alone');
        } finally {
            DB::rollBack();
        }
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

    /** True when a `line` row was taken off this child by hand. */
    private function lastLineWasWithdrawn(int $childId): bool
    {
        return DB::table(\App\Services\Catalog\ChildOptionDecisions::TABLE . ' as d')
            ->join('options as o', 'o.id', '=', 'd.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('d.child_id', $childId)
            ->where('d.kind', \App\Services\Catalog\ChildOptionDecisions::WITHDRAWN)
            ->where('g.price_role', \App\Models\OptionGroup::ROLE_LINE)
            ->exists();
    }

}
