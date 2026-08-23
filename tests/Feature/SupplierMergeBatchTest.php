<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The 2026-08-23 batch: three folds, two retirements, two renames.
 *
 *   «ادمج أكياس بلاستيك تحت مواد التعبئة والتغليف فى كل الاقسام»
 *   «ادمج الرحلات تحت السياحة»
 *   «ادمج مستلزمات المطاعم والكافيهات فى مستلزمات مطاعم وكافيهات تحت كل الاقسام»
 *   «احذف شركات الطوب وشركات الأسمنت»
 *   «الاسم يكون فني صيانة أجهزة منزلية … ليمكن تمييزه عن ورش صيانة الأجهزة»
 *
 * Every one of them is the same shape and the same risk: a child stops
 * standing somewhere, and four kinds of file look children up BY NAME. What
 * this pins is not that the fold happened — the seeders report that — but that
 * nothing was left mute or unreachable by it.
 */
class SupplierMergeBatchTest extends TestCase
{
    use DatabaseTransactions;

    private const KEEPERS = [
        247 => 'مستلزمات مطاعم وكافيهات',
        204 => 'مواد تعبئة وتغليف',
        279 => 'سياحة',
    ];

    /** The folded children, and where each of them went. */
    private const FOLDED = [
        37 => 247,   // مستلزمات كافيهات
        66 => 247,   // مستلزمات قهاوى
        221 => 204,  // اكياس بلاستيك
        285 => 279,  // رحلات
    ];

    private function rootsOf(int $childId): array
    {
        return DB::table('category_parent_child as p')
            ->join('categories as c', 'c.id', '=', 'p.parent_id')
            ->where('p.child_id', $childId)
            ->orderBy('c.slug')->pluck('c.slug')->all();
    }

    /** @return array<int,string> */
    private function optionsOf(int $childId, string $group): array
    {
        return DB::table('category_child_option as l')
            ->join('options as o', 'o.id', '=', 'l.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('l.child_id', $childId)->where('g.name_ar', $group)
            ->distinct()->pluck('o.name_ar')->all();
    }

    /** A folded child stands nowhere, holds nobody, and keeps its master row. */
    public function test_the_folded_children_are_retired_and_not_deleted(): void
    {
        foreach (array_keys(self::FOLDED) as $childId) {
            $this->assertSame([], $this->rootsOf($childId), "#{$childId} still stands under a root");

            $this->assertSame(
                0,
                DB::table('users')->where('category_child_id', $childId)->count(),
                "#{$childId} was folded away with a merchant still on it"
            );

            // Nothing in this taxonomy is deleted: the master row and the
            // rootless state together ARE the undo record.
            $this->assertTrue(
                DB::table('category_children_master')->where('id', $childId)->exists(),
                "#{$childId} was deleted rather than retired"
            );
        }
    }

    /**
     * The keeper stands everywhere the children it swallowed stood.
     *
     * «تحت كل الاقسام الرئيسية» is the instruction, and it is the half a fold
     * cannot do on its own — ChildRootDetachSeeder refuses to reassign a
     * merchant to a destination that does not stand under his root.
     */
    public function test_each_keeper_took_the_roots_of_what_it_swallowed(): void
    {
        $this->assertSame(
            ['companies', 'exhibitions', 'factories', 'shops-online'],
            $this->rootsOf(247),
            'the supplier lost a storefront in the merge'
        );

        $this->assertSame(['companies', 'factories', 'shops-online'], $this->rootsOf(204));
        $this->assertSame(['companies'], $this->rootsOf(279));
    }

    /**
     * …and can say every word they said.
     *
     * The test of a merge: arriving mute is a demotion dressed as one. Neither
     * café list shared a single row with the restaurant supplier's, so #247
     * without «مستلزمات المقاهي» would be a narrower trade than the two it
     * replaced.
     */
    public function test_each_keeper_can_say_what_the_folded_child_said(): void
    {
        $cafe = $this->optionsOf(247, 'مستلزمات المقاهي');

        foreach (['ماكينات قهوة', 'مطاحن بن', 'شيشة ومستلزماتها'] as $row) {
            $this->assertContains($row, $cafe, "«{$row}» was lost in the café fold");
        }

        // …beside the restaurant list it already had.
        $this->assertContains('أفران', $this->optionsOf(247, 'مستلزمات المطاعم'));

        $bags = $this->optionsOf(204, 'الأكياس والمنتجات البلاستيكية');

        foreach (['أكياس تسوق', 'أكياس شرنك', 'رول بلاستيك'] as $row) {
            $this->assertContains($row, $bags, "«{$row}» was lost in the bag fold");
        }

        // «رحلات» needed nothing: «سياحة» held all six of its rows and five
        // more, which is why a difference of five rows is an option and not a
        // child.
        $trips = $this->optionsOf(279, 'خدمات السياحة والسفر');

        foreach (['رحلات داخلية', 'رحلات خارجية', 'رحلات بحرية', 'حجز طيران', 'تأشيرات'] as $row) {
            $this->assertContains($row, $trips, "«{$row}» is missing from the tourism list");
        }
    }

    /**
     * Nobody was left pointing at a child that stands nowhere.
     *
     * The one bag merchant and the one trip operator are the whole population
     * this batch moved, and the shape of the failure — a merchant vanishing
     * from every screen at once — is invisible until someone complains.
     */
    public function test_every_moved_merchant_stands_where_his_child_does(): void
    {
        foreach (self::FOLDED as $folded => $keeper) {
            $stranded = DB::table('users as u')
                ->where('u.category_child_id', $keeper)
                ->whereNotExists(fn ($q) => $q->from('category_parent_child as p')
                    ->whereColumn('p.child_id', 'u.category_child_id')
                    ->whereColumn('p.parent_id', 'u.category_id'))
                ->count();

            $this->assertSame(0, $stranded, "a merchant folded from #{$folded} points at a root #{$keeper} does not stand under");
        }
    }

    /**
     * «شركات طوب» and «شركات أسمنت» are gone as CHILDREN of شركات and alive
     * everywhere else. A brick is made in a factory or sold by a builders'
     * merchant; the company in between was never a third trade.
     */
    public function test_the_brick_and_cement_companies_left_only_that_root(): void
    {
        $companies = (int) DB::table('categories')->where('slug', 'companies')->value('id');

        foreach ([34 => 'طوب', 55 => 'اسمنت'] as $childId => $name) {
            $this->assertNotContains('companies', $this->rootsOf($childId), "«{$name}» still stands under شركات");
            $this->assertNotEmpty($this->rootsOf($childId), "«{$name}» was retired, not detached");

            $this->assertSame(
                0,
                DB::table('users')->where('category_child_id', $childId)->where('category_id', $companies)->count()
            );
        }

        $this->assertContains('factories', $this->rootsOf(34));
        $this->assertContains('shops-online', $this->rootsOf(55));
    }

    /**
     * A craftsman is a person; a workshop is a place.
     *
     * #22 under مهن وحرفيين and #546 «ورشة صيانة أجهزة» under ورش ومراكز صيانة
     * both did «صيانة أجهزة منزلية», and the customer choosing between «I call
     * someone out» and «I carry it in» was reading the same words twice.
     */
    public function test_the_appliance_technician_is_named_as_a_person(): void
    {
        $this->assertSame(
            'فني صيانة أجهزة منزلية',
            DB::table('category_children_master')->where('id', 22)->value('name_ar')
        );

        $this->assertSame(
            'مستلزمات مطاعم وكافيهات',
            DB::table('category_children_master')->where('id', 247)->value('name_ar')
        );

        // The rename keeps the id, which is why a merge keeps a child: the one
        // merchant, every option link and every service config travel with it.
        $this->assertSame(1, DB::table('users')->where('category_child_id', 22)->count());
        $this->assertNotEmpty($this->optionsOf(22, 'نظام التعاقد'));
    }

    /**
     * Every file that resolves a child BY NAME knows the new names.
     *
     * This is the landmine the taxonomy keeps re-laying: a rename the branch
     * maps have not heard of silently unwires the child, and a folded name left
     * in a vocabulary file re-grants its list to a rootless child on every run.
     */
    public function test_no_by_name_map_still_calls_a_child_by_a_dead_name(): void
    {
        $dead = ['مستلزمات مطاعم', 'مستلزمات كافيهات', 'مستلزمات قهاوى', 'اكياس بلاستيك', 'صيانة اجهزة منزلية'];

        $files = [
            'retail_child_branches.php', 'delivery_child_branches.php', 'booking_child_branches.php',
            'menu_child_branches.php', 'retail_child_types.php', 'delivery_child_types.php',
        ];

        foreach ($files as $file) {
            $path = database_path('seeders/data/' . $file);

            if (! is_file($path)) {
                continue;
            }

            foreach (require $path as $rootSlug => $children) {
                if (! is_array($children)) {
                    continue;
                }

                foreach (array_keys($children) as $key) {
                    // «مستلزمات مطاعم وكافيهات» contains «مستلزمات مطاعم», so
                    // the comparison has to be on the whole key.
                    $this->assertNotContains(
                        $key,
                        $dead,
                        "{$file} still names «{$key}», which stands nowhere since 2026-08-23"
                    );
                }
            }
        }
    }

    /** «رحلات» carried six of «سياحة»'s eleven rows — the merge argument itself. */
    public function test_the_trip_operator_was_a_subset_and_not_a_trade(): void
    {
        $file = require database_path('seeders/data/company_child_vocabularies.php');

        $this->assertArrayNotHasKey(285, $file['links'] ?? [], 'the vocabulary still grants a rootless child its list');

        $keeper = $this->optionsOf(279, 'خدمات السياحة والسفر');

        $this->assertGreaterThanOrEqual(11, count($keeper), 'the tourism list shrank in the merge');
    }
}
