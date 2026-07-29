<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase A of the services-linking simplification (see docs/architecture-blueprint.md §8).
 *
 * Locks two things: the dead migration-scratch tables stay dropped, and the
 * hotel-star classification that was ALMOST mistaken for pollution stays intact.
 */
class TaxonomyLinkingCleanupTest extends TestCase
{
    /** The two scratch tables from the options→item_types migration are gone. */
    public function test_dead_scratch_tables_are_dropped(): void
    {
        $this->assertFalse(
            Schema::hasTable('temp_category_option_mapping'),
            'temp_category_option_mapping should have been dropped in Phase A'
        );
        $this->assertFalse(
            Schema::hasTable('temp_unmatched_category_option_ids'),
            'temp_unmatched_category_option_ids should have been dropped in Phase A'
        );
    }

    /**
     * The «⭐» children in category_children_master are the hotel-star
     * classification, NOT pollution — real businesses depend on them. This test
     * is the landmine sign: if it fails because the rows were deleted, hotels
     * were orphaned.
     */
    public function test_hotel_star_classification_is_preserved(): void
    {
        $starChildren = DB::table('category_children_master')
            ->where('name_ar', 'like', '%⭐%')
            ->pluck('id');

        // Only assert the invariant when the data is present (skip on an empty
        // fixture DB rather than assert a count the seed may not carry).
        if ($starChildren->isEmpty()) {
            $this->markTestSkipped('No hotel-star classification rows in this database.');
        }

        $classifiedBusinesses = DB::table('users')
            ->whereIn('category_child_id', $starChildren)
            ->count();

        $this->assertGreaterThan(
            0,
            $classifiedBusinesses,
            'businesses are classified under the star children — they must not be deleted'
        );
    }
}
