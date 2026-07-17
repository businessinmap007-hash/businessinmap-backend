<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards the line between an item type and an option.
 *
 * The rule, and the only thing this file really asserts:
 *
 *   Can the merchant put a price on it on its own?
 *     yes → item type  ("قاعة أفراح: 5000")
 *     no  → option     ("من 200 إلى 300 فرد")
 *
 * Before `TaxonomyRedistributionSeeder`, «قاعات ومناسبات» held 39 entries of
 * which 9 were bookable — the other 30 were a hall's capacity, class and a
 * meaningless «مقاس» scale. That is what made the platform feel crowded, and
 * this test exists so it cannot creep back one well-meaning row at a time.
 *
 * Asserts the END STATE, not a delta, because the seeder is idempotent and has
 * already run. Rolls back.
 */
class TaxonomyRedistributionTest extends TestCase
{
    use DatabaseTransactions;

    /** Keys shaped like a dimension rather than a thing you buy. */
    private const DIMENSION_PATTERN = '/^(size_\d+|from_\d+_to_\d+_person|monitorfrom_|\dst_class|\dnd_class|\dth_class|\drd_class)/';

    public function test_no_active_item_type_is_really_a_dimension(): void
    {
        $offenders = DB::table('platform_service_item_types')
            ->where('is_active', 1)
            ->get(['key', 'name_ar'])
            ->filter(fn ($t) => (bool) preg_match(self::DIMENSION_PATTERN, (string) $t->key))
            ->map(fn ($t) => $t->key . ' (' . $t->name_ar . ')')
            ->values();

        $this->assertEmpty(
            $offenders->all(),
            'a capacity/class/size is not something a merchant prices — it belongs in an option group: ' . $offenders->implode(', ')
        );
    }

    public function test_the_halls_branch_only_holds_things_you_can_book(): void
    {
        $group = DB::table('platform_service_item_groups')->where('name_ar', 'like', '%قاعات%')->first();

        if (! $group) {
            $this->markTestSkipped('The halls branch is absent.');
        }

        $types = DB::table('platform_service_item_group_type as gt')
            ->join('platform_service_item_types as t', 't.id', '=', 'gt.item_type_id')
            ->where('gt.group_id', $group->id)
            ->pluck('t.name_ar');

        // 39 before the redistribution, of which 30 were dimensions.
        $this->assertLessThanOrEqual(
            12,
            $types->count(),
            'the halls branch is filling up with non-bookables again: ' . $types->implode(' · ')
        );

        $this->assertContains('قاعة أفراح', $types->all(), 'the branch must still hold the halls themselves');
    }

    public function test_amenities_are_an_option_but_capacity_and_class_are_not(): void
    {
        // Amenities are a business-level yes/no → axis 2 → an option.
        $amenities = DB::table('option_groups')->where('name_ar', 'مرافق ومعدات')->first();
        $this->assertNotNull($amenities, 'the amenities option group must exist');
        $this->assertSame(2, DB::table('options')->where('group_id', $amenities->id)->count());

        // Capacity and class describe one bookable UNIT → axis 3 → they must NOT
        // be options. An earlier pass wrongly made them option groups; that is
        // the mistake this asserts stays fixed.
        foreach (['سعة القاعة', 'فئة القاعة'] as $wrong) {
            $this->assertNull(
                DB::table('option_groups')->where('name_ar', $wrong)->first(),
                "«{$wrong}» is a per-unit dimension — it belongs on bookable_items, not in options"
            );
        }
    }

    public function test_capacity_lives_on_the_bookable_unit(): void
    {
        // The home for capacity is bookable_items.capacity — an existing column,
        // where a filter can be exact rather than a bucket.
        $this->assertTrue(
            \Illuminate\Support\Facades\Schema::hasColumn('bookable_items', 'capacity'),
            'capacity has nowhere to live if this column is gone'
        );

        // No option, anywhere, should be a capacity bucket.
        $buckets = DB::table('options')
            ->where('name_ar', 'like', '%فرد%')
            ->where('name_ar', 'like', 'من %')
            ->count();
        $this->assertSame(0, $buckets, 'a "من X إلى Y فرد" option is a capacity bucket that should be a number on the unit');
    }

    public function test_installment_survives_because_it_is_why_options_exist(): void
    {
        // The payment-mode concept is the canonical attribute: a shop HAS it,
        // nobody buys it. If a cleanup ever deletes this, the cleanup went too
        // far. Pinned to «تقسيط بدون فوائد» rather than the bare «تقسيط» —
        // that plain name was legitimately recategorized into option group 9
        // «عقارات وممتلكات» by an admin using the bulk-editor this session
        // fixed (a real-estate installment term, not the commercial-mode one).
        $this->assertDatabaseHas('options', ['name_ar' => 'تقسيط بدون فوائد', 'group_id' => 12]);
        $this->assertDatabaseHas('options', ['name_ar' => 'دفع مسبق', 'group_id' => 12]);
        $this->assertDatabaseHas('options', ['name_ar' => 'جملة', 'group_id' => 12]);
    }

    public function test_no_specialty_sits_in_the_attributes_group(): void
    {
        $strays = DB::table('options')
            ->where('group_id', 12)
            ->whereIn('name_ar', ['حجز طيران', 'حجز فنادق', 'شغالة', 'دادة أطفال', 'بترول', 'أخشاب', 'spear 1'])
            ->pluck('name_ar');

        $this->assertEmpty(
            $strays->all(),
            'group 12 is attributes only — a bookable service belongs in item types: ' . $strays->implode(', ')
        );
    }

    public function test_no_two_active_item_types_share_a_name_inside_one_service(): void
    {
        $dupes = DB::table('platform_service_item_types')
            ->where('is_active', 1)
            ->get(['platform_service_id', 'name_ar', 'key'])
            ->groupBy(fn ($t) => $t->platform_service_id . '|' . trim((string) $t->name_ar))
            ->filter(fn ($g) => $g->count() > 1)
            ->map(fn ($g) => $g->first()->name_ar . ' → ' . $g->pluck('key')->implode('/'))
            ->values();

        $this->assertEmpty(
            $dupes->all(),
            'the same thing named twice in one service is how the import junk got in: ' . $dupes->implode(', ')
        );
    }

    public function test_no_config_offers_a_retired_item_type(): void
    {
        $dead = DB::table('platform_service_item_types')->where('is_active', 0)->pluck('key')->flip();
        $offenders = [];

        foreach (DB::table('category_service_configs')->get() as $row) {
            $config = json_decode((string) $row->config, true);
            $allowed = is_array($config) ? ($config['allowed_item_types'] ?? []) : [];

            if (! is_array($allowed)) {
                continue;
            }

            foreach ($allowed as $key) {
                if ($dead->has($key)) {
                    $offenders[] = "config#{$row->id}:{$key}";
                }
            }
        }

        // Nothing errors when a config names a retired key — the merchant is
        // simply still offered it. That silence is why this is asserted.
        $this->assertEmpty($offenders, 'configs still offer retired item types: ' . implode(', ', array_slice($offenders, 0, 10)));
    }

    public function test_the_hotels_real_room_types_were_not_swept_away(): void
    {
        // Business 212 («فندق الاندلس», a real 2020 account) prices these. The
        // cleanup deactivated 71 types around them; these had to survive.
        foreach (['single_room', 'double_room', 'suite', 'family_room', 'villa', 'apartment'] as $key) {
            $this->assertDatabaseHas('platform_service_item_types', ['key' => $key, 'is_active' => 1]);
        }
    }

    public function test_every_priced_offering_still_points_at_a_real_item_type(): void
    {
        $known = DB::table('platform_service_item_types')->pluck('key')->flip();

        $dangling = DB::table('business_service_prices')
            ->whereNotNull('bookable_item_type')
            ->where('bookable_item_type', '!=', '')
            ->get(['id', 'business_id', 'bookable_item_type'])
            ->filter(fn ($p) => ! $known->has($p->bookable_item_type))
            ->map(fn ($p) => "price#{$p->id}(biz {$p->business_id}):{$p->bookable_item_type}")
            ->values();

        $this->assertEmpty(
            $dangling->all(),
            'a merge orphaned a real merchant offering: ' . $dangling->implode(', ')
        );
    }
}
