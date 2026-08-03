<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards the split of «أنماط خدمة وتجارية» into eight single-question groups
 * and the service scope that went with it.
 *
 * @see \Database\Seeders\ChildOptionGroupsSeeder
 * @see \Database\Seeders\ChildServiceScopeSeeder
 */
class ChildOptionRedistributionTest extends TestCase
{
    /** The grab-bag is emptied, not re-filled by a later screen save. */
    public function test_the_commerce_grab_bag_holds_no_options(): void
    {
        $groupId = DB::table('option_groups')->where('name_ar', 'أنماط خدمة وتجارية')->value('id');

        if (! $groupId) {
            $this->markTestSkipped('The grab-bag group is gone entirely, which is also fine.');
        }

        $this->assertSame(
            0,
            DB::table('options')->where('group_id', $groupId)->count(),
            'the 24 options were split into eight groups; anything back in here is a regression'
        );
    }

    /** A craftsman is never asked whether he exports or sells wholesale. */
    public function test_field_trades_are_not_offered_wholesale_or_export(): void
    {
        $tradeScope = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', 'نطاق التعامل')
            ->pluck('o.id');

        $this->assertNotEmpty($tradeScope, 'the trade-scope group must exist');

        // نقاش, سباك and كهربائي sell labour; none of them import anything
        $offenders = DB::table('category_child_option')
            ->whereIn('child_id', [206, 227, 89])
            ->whereIn('option_id', $tradeScope)
            ->count();

        $this->assertSame(0, $offenders, 'a painter, a plumber and an electrician have no trade scope to declare');
    }

    /** Every option a business already chose is still offered by its child. */
    public function test_no_merchant_selection_was_orphaned(): void
    {
        $orphans = DB::table('option_user as ou')
            ->join('users as u', 'u.id', '=', 'ou.user_id')
            ->whereNotNull('u.category_child_id')
            ->whereNotExists(function ($q) {
                $q->from('category_child_option as co')
                    ->whereColumn('co.child_id', 'u.category_child_id')
                    ->whereColumn('co.option_id', 'ou.option_id');
            })
            ->count();

        $this->assertSame(0, $orphans, 'redistribution must never strip an option a merchant had already ticked');
    }

    /** A hotel declares its facilities; the grab-bag never described one. */
    public function test_hotels_carry_facilities_and_not_factory_terms(): void
    {
        $hotel = DB::table('category_children_master')->where('name_ar', 'فندق')->value('id');

        if (! $hotel) {
            $this->markTestSkipped('No hospitality taxonomy in this database.');
        }

        $groups = DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', $hotel)
            ->distinct()
            ->pluck('g.name_ar');

        $this->assertContains('مرافق الإقامة', $groups->all());
        $this->assertNotContains('نطاق التعامل', $groups->all(), 'a hotel does not export');
    }

    /** An active service that allows no item type lets a business sell nothing. */
    public function test_no_active_service_config_allows_nothing(): void
    {
        $empty = DB::table('category_service_configs as c')
            ->join('platform_services as s', 's.id', '=', 'c.platform_service_id')
            ->join('category_children_master as ch', 'ch.id', '=', 'c.child_id')
            ->where('c.is_active', 1)
            ->where('s.is_active', 1)
            ->get(['ch.name_ar', 's.key', 'c.config'])
            ->filter(function ($row) {
                $cfg = json_decode($row->config ?: '{}', true) ?: [];

                return empty($cfg['allowed_item_types']);
            })
            ->map(fn ($r) => "{$r->name_ar}/{$r->key}");

        $this->assertEmpty(
            $empty->all(),
            'these children have a live service they cannot list anything under: ' . $empty->implode('، ')
        );
    }

    /**
     * Discovery matches a business by its own classification AND a price row
     * for the same child, so a price left on a detached child is invisible.
     *
     * @see \Database\Seeders\StrandedPriceChildSeeder
     */
    public function test_no_price_row_points_at_a_child_with_no_root(): void
    {
        $linked = DB::table('category_parent_child')->distinct()->pluck('child_id');

        $stranded = DB::table('business_service_prices as p')
            ->join('users as u', 'u.id', '=', 'p.business_id')
            ->whereNotIn('p.child_id', $linked)
            ->whereIn('u.category_child_id', $linked)
            ->count();

        $this->assertSame(0, $stranded, 'a price on a rootless child is money the customer can never reach');
    }

    /** The root that held 638 businesses and sold nothing. */
    public function test_the_limousine_child_can_sell_something(): void
    {
        $configs = DB::table('category_service_configs as c')
            ->join('platform_services as s', 's.id', '=', 'c.platform_service_id')
            ->where('c.child_id', 169)
            ->where('c.is_active', 1)
            ->where('s.is_active', 1)
            ->pluck('c.config');

        if ($configs->isEmpty()) {
            $this->markTestSkipped('The limousine child is absent from this database.');
        }

        $types = $configs->flatMap(fn ($c) => json_decode($c, true)['allowed_item_types'] ?? []);

        $this->assertNotEmpty($types, 'خدمة ليموزين must have at least one sellable item type');
    }
}
