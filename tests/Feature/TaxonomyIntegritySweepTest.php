<?php

namespace Tests\Feature;

use App\Models\BusinessServicePrice;
use App\Models\OptionGroup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The invariants a full sweep of services and options must keep — the ones no
 * other test held.
 *
 * ServiceWiringIntegrityTest already guards the two-row rule (a service reaches
 * a merchant only when `category_platform_services` and
 * `category_service_configs` agree) and that every allowed item type is a live
 * type of its own service. What follows is everything else the sweep of
 * 2026-08-08 checked and found clean: it is cheap to keep clean and expensive
 * to notice broken, because each of these fails SILENTLY.
 */
class TaxonomyIntegritySweepTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * A kind that demands a named unit but is not on offer is a rule about
     * nothing — and the engine reads the two lists together, so the mismatch
     * shows up as a booking refused for a reason the config never stated.
     */
    public function test_every_unit_requiring_kind_is_a_kind_the_child_offers(): void
    {
        $broken = [];

        foreach (DB::table('category_service_configs')->where('is_active', 1)->get() as $row) {
            $config = json_decode((string) $row->config, true) ?: [];

            $kinds = $this->clean($config['bookable_item_kinds'] ?? []);
            $allowed = $this->clean($config['allowed_item_types'] ?? []);

            // An EMPTY allowed list means every type, not none — so there is
            // nothing to contradict. See ResolvesOwnerCatalog.
            if ($kinds === [] || $allowed === []) {
                continue;
            }

            $outside = array_diff($kinds, $allowed);

            if ($outside) {
                $broken[] = "child {$row->child_id}: " . implode('، ', $outside);
            }
        }

        $this->assertSame([], $broken, "these children demand a unit for a kind they do not offer:\n  " . implode("\n  ", $broken));
    }

    /** An option outside a group can never be priced, filtered or scoped. */
    public function test_every_option_sits_in_a_group_with_a_known_price_role(): void
    {
        $this->assertSame(
            0,
            DB::table('options')->whereNull('group_id')->orWhere('group_id', 0)->count(),
            'an option with no group is invisible to the vocabulary'
        );

        $this->assertSame(
            0,
            DB::table('option_groups')->whereNotIn('price_role', OptionGroup::ROLES)->count(),
            'a group whose role is not line/modifier/descriptive sorts with the tail and is never priced'
        );
    }

    /** A link to a row that is gone is a filter that can never match. */
    public function test_no_child_option_link_dangles(): void
    {
        $this->assertSame(0, DB::table('category_child_option as cco')
            ->leftJoin('options as o', 'o.id', '=', 'cco.option_id')
            ->whereNull('o.id')->count(), 'a link points at an option that no longer exists');

        $this->assertSame(0, DB::table('category_child_option as cco')
            ->leftJoin('category_children_master as c', 'c.id', '=', 'cco.child_id')
            ->whereNull('c.id')->count(), 'a link points at a child that no longer exists');
    }

    /**
     * `business_service_prices.line_option_id` mirrors the `line` row in
     * `offering_options`, because a unique key cannot reach across tables. Only
     * `syncOfferingOptions()` may write it, and this is what catches anything
     * that wrote it anyway — a hand-run tinker session, a seeder, an admin save
     * that bypassed the model.
     */
    public function test_the_priced_line_mirror_agrees_with_the_vocabulary(): void
    {
        $mismatched = DB::table('business_service_prices as p')
            ->leftJoin('offering_options as oo', function ($join) {
                $join->on('oo.offering_id', '=', 'p.id')
                    ->where('oo.offering_type', '=', BusinessServicePrice::class)
                    ->where('oo.role', '=', 'line');
            })
            ->whereRaw('COALESCE(oo.option_id, 0) <> COALESCE(p.line_option_id, 0)')
            ->pluck('p.id');

        $this->assertEmpty(
            $mismatched->all(),
            'these priced rows disagree with their own vocabulary: ' . $mismatched->implode('، ')
        );
    }

    /** @param  mixed  $values @return array<int,string> */
    private function clean($values): array
    {
        return array_values(array_filter(array_map(
            fn ($value) => trim((string) $value),
            (array) $values
        ), fn ($value) => $value !== ''));
    }
}
