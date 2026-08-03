<?php

namespace Tests\Feature;

use Database\Seeders\LinkCategoryChildrenToOptionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards LinkCategoryChildrenToOptionsSeeder: the bulk pass that took
 * category_child_option from 1 real child (68) to all 304. Two properties
 * matter more than the exact keyword list — additive-only (never fights a
 * concurrent admin edit) and idempotent (safe to re-run after the owner
 * moves more service items into an option group). Asserts the END STATE
 * (the seeder has already run for real), not a delta. Rolls back.
 */
class CategoryChildOptionLinkingTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * This used to assert the opposite: that EVERY child carried ALL 24 options
     * of the commerce grab-bag. That blanket is what ChildOptionGroupsSeeder
     * undid — the group was split into eight single-question groups, each given
     * only to the children whose trade can answer it. What still must hold is
     * that no LIVE child was left mute: a child under a root has at least one
     * question to answer about itself.
     */
    public function test_no_live_child_was_left_without_a_single_option(): void
    {
        $mute = DB::table('category_parent_child as pc')
            ->join('category_children_master as ch', 'ch.id', '=', 'pc.child_id')
            ->whereNotExists(function ($q) {
                $q->from('category_child_option as co')->whereColumn('co.child_id', 'pc.child_id');
            })
            ->distinct()
            ->pluck('ch.name_ar');

        $this->assertEmpty(
            $mute->all(),
            'a child linked to a root must offer something to describe itself: ' . $mute->implode('، ')
        );
    }

    public function test_vehicle_options_never_leaked_onto_an_unrelated_specialty(): void
    {
        $leaked = DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('category_children_master as c', 'c.id', '=', 'cco.child_id')
            ->where('o.group_id', 1)
            ->whereIn('c.name_ar', ['مطعم', 'كافيه', 'صيدلية', 'محاماه'])
            ->count();

        $this->assertSame(0, $leaked, 'a vehicle-brand option has no business being offered on a restaurant/pharmacy/law-firm specialty');
    }

    public function test_the_owners_manual_real_estate_linking_survived_untouched(): void
    {
        // The owner hand-linked all 12 real-estate children to all 18
        // real-estate options via the bulk editor concurrently with this
        // seeder's design — proof the seeder must never overwrite that.
        $shqa = DB::table('category_children_master')->where('name_ar', 'شقة')->first();

        if (! $shqa) {
            $this->markTestSkipped('The شقة real-estate child is gone.');
        }

        $group9Count = DB::table('options')->where('group_id', 9)->count();
        $linked = DB::table('category_child_option')->where('child_id', $shqa->id)->count();

        $this->assertGreaterThanOrEqual($group9Count, $linked, 'شقة must still carry every real-estate option the owner linked by hand');
    }

    public function test_seeder_is_idempotent(): void
    {
        $before = DB::table('category_child_option')->count();

        (new LinkCategoryChildrenToOptionsSeeder)->run();

        $this->assertSame($before, DB::table('category_child_option')->count(), 're-running the seeder must never insert a duplicate pair');
    }
}
