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
     * A hotel must never be orphaned. That invariant is unchanged; what changed
     * (2026-08-02) is where it lives. The «⭐» children WERE the classification,
     * so this test used to assert businesses sat under them. They are now
     * detached — a star rating describes a hotel rather than saying what it is —
     * and hospitality is classified by accommodation TYPE, with the grade on the
     * option axis. So the guard now watches the replacement: real hotels sit
     * under a type, and the grade they carried survived the move.
     */
    public function test_hotels_are_classified_by_accommodation_type(): void
    {
        $typeChildren = DB::table('category_parent_child as pc')
            ->join('categories as r', 'r.id', '=', 'pc.parent_id')
            ->join('category_children_master as ch', 'ch.id', '=', 'pc.child_id')
            ->where('r.slug', 'tourist-hotels')
            ->pluck('ch.name_ar', 'ch.id');

        if ($typeChildren->isEmpty()) {
            $this->markTestSkipped('No hospitality taxonomy in this database.');
        }

        // the star children must be gone from the root — that is the whole point
        $this->assertEmpty(
            $typeChildren->filter(fn ($name) => str_contains((string) $name, '⭐'))->all(),
            'star ratings are options now; they must not be children of the hotels root'
        );

        $classified = DB::table('users')
            ->whereIn('category_child_id', $typeChildren->keys())
            ->count();

        $this->assertGreaterThan(
            0,
            $classified,
            'hotels must sit under an accommodation type — a detach that stranded them would show up here'
        );
    }

    /** The grade a migrated hotel carried must survive on the option axis. */
    public function test_the_migrated_hotel_kept_its_grade(): void
    {
        $gradeGroupId = DB::table('option_groups')->where('name_ar', 'تصنيف الإقامة')->value('id');

        if (! $gradeGroupId) {
            $this->markTestSkipped('The accommodation-grade group is absent.');
        }

        $graded = DB::table('option_user as ou')
            ->join('options as o', 'o.id', '=', 'ou.option_id')
            ->where('o.group_id', $gradeGroupId)
            ->count();

        $this->assertGreaterThan(0, $graded, 'a hotel moved off the star children must keep its grade as an option');
    }
}
