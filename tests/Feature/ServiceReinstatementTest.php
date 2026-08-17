<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Every row in data/service_reinstatements.php is a named finding: a child that
 * could not sell, beside siblings that could, with the donor whose config it
 * takes. Never a heuristic — an admin who switched something off on purpose is
 * entitled to have that survive.
 *
 * The class of defect this guards is the worst kind the taxonomy produces,
 * because nothing surfaces it: the merchant registers, appears in search, can be
 * delivered from, and has no way to say what he sells.
 */
class ServiceReinstatementTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array<int,array<string,mixed>> */
    private function entries(): array
    {
        return require database_path('seeders/data/service_reinstatements.php');
    }

    private function rootId(string $slug): int
    {
        return (int) DB::table('categories')->where('slug', $slug)->value('id');
    }

    private function childId(string $nameAr, int $rootId): int
    {
        return (int) DB::table('category_parent_child as p')
            ->join('category_children_master as c', 'c.id', '=', 'p.child_id')
            ->where('p.parent_id', $rootId)->where('c.name_ar', $nameAr)
            ->value('c.id');
    }

    /** Every declared reinstatement is live, wired on both rows, and bounded. */
    public function test_every_declared_service_is_actually_reachable(): void
    {
        foreach ($this->entries() as $entry) {
            $rootId = $this->rootId($entry['root_slug']);
            $childId = $this->childId($entry['child_name_ar'], $rootId);
            $serviceId = (int) DB::table('platform_services')->where('key', $entry['service_key'])->value('id');

            $label = "«{$entry['child_name_ar']}» × {$entry['root_slug']} · {$entry['service_key']}";

            $this->assertGreaterThan(0, $childId, "{$label}: the child does not stand under that root");

            $this->assertTrue(
                DB::table('category_platform_services')->where('category_id', $rootId)
                    ->where('child_id', $childId)->where('platform_service_id', $serviceId)
                    ->where('is_active', 1)->exists(),
                "{$label}: the link is off"
            );

            // A service reaches a merchant through TWO rows that must agree.
            $this->assertTrue(
                DB::table('category_service_configs')->where('category_id', $rootId)
                    ->where('child_id', $childId)->where('platform_service_id', $serviceId)
                    ->where('is_active', 1)->exists(),
                "{$label}: the config is off, so the link leads nowhere"
            );
        }
    }

    /**
     * The donor is what makes this safe: the child arrives offering a shape
     * somebody under that root already chose, not one this file invented.
     */
    public function test_each_child_took_its_donor_shape(): void
    {
        foreach ($this->entries() as $entry) {
            $rootId = $this->rootId($entry['root_slug']);
            $serviceId = (int) DB::table('platform_services')->where('key', $entry['service_key'])->value('id');

            $config = fn (int $childId) => json_decode((string) DB::table('category_service_configs')
                ->where('category_id', $rootId)->where('child_id', $childId)
                ->where('platform_service_id', $serviceId)->where('is_active', 1)->value('config'), true) ?: [];

            $sorted = function (array $c) {
                $types = $c['allowed_item_types'] ?? [];
                sort($types);

                return $types;
            };

            // A donor may stand under a different root from its recipient —
            // «شحن بري وبحري وجوى» moved to «شحن وتوصيل» on 2026-08-16 and is
            // still the right shape for «معدات ثقيلة», because what is copied
            // is a carrier's and not a root's.
            $donorRootId = isset($entry['copy_from_root_slug'])
                ? $this->rootId($entry['copy_from_root_slug'])
                : $rootId;

            $donorConfigAt = fn (int $childId) => json_decode((string) DB::table('category_service_configs')
                ->where('category_id', $donorRootId)->where('child_id', $childId)
                ->where('platform_service_id', $serviceId)->where('is_active', 1)->value('config'), true) ?: [];

            $mineConfig = $config($this->childId($entry['child_name_ar'], $rootId));
            $donorConfig = $donorConfigAt($this->childId($entry['copy_from_child_ar'], $donorRootId));

            $mine = $sorted($mineConfig);
            $donor = $sorted($donorConfig);

            $this->assertNotSame(
                [],
                $mine,
                "«{$entry['child_name_ar']}» got «{$entry['service_key']}» with an unbounded picker"
            );

            /*
             * The donor gives the SHELF, not the final list.
             *
             * This compared the two type lists outright until 2026-08-11, which
             * was the same thing while every child took its branch whole. It is
             * not any more: data/retail_child_types.php narrows a trade to the
             * part of its shelf it sells, so «أسماك» takes مجمدات and معلبات
             * from a donor — «مواد غذائية» — that legitimately carries all 22.
             * Demanding equality would mean either a fish factory offering baby
             * care, or a food wholesaler narrowed to fish.
             *
             * What must still hold is that the child did not invent a shelf:
             * same branch, and its own list a SUBSET of what the donor carries.
             */
            $branch = fn (array $c) => collect($c['item_groups'] ?? [])->sort()->values()->all();

            $this->assertSame(
                $branch($donorConfig),
                $branch($mineConfig),
                "«{$entry['child_name_ar']}» is on a different branch from its donor «{$entry['copy_from_child_ar']}»"
            );

            $this->assertSame(
                [],
                array_values(array_diff($mine, $donor)),
                "«{$entry['child_name_ar']}» offers what its donor «{$entry['copy_from_child_ar']}» does not: "
                    . implode('، ', array_diff($mine, $donor))
            );
        }
    }

    /** The specific finding worth naming: a shipping office can publish a trip. */
    public function test_the_shipping_office_can_publish_a_trip(): void
    {
        $rootId = $this->rootId('shipping-delivery');
        $schedules = (int) DB::table('platform_services')->where('key', 'schedules')->value('id');

        foreach (['شركة', 'مكتب', 'مندوب'] as $name) {
            $this->assertTrue(
                DB::table('category_platform_services')->where('category_id', $rootId)
                    ->where('child_id', $this->childId($name, $rootId))
                    ->where('platform_service_id', $schedules)->where('is_active', 1)->exists(),
                "«{$name}» cannot publish a trip leg"
            );
        }
    }

    /**
     * The finding that closed the board: «معدات ثقيلة» is a CARRIER.
     *
     * It was held back from the third pass looking for a retail donor, and there
     * is none because it sells no goods. Its own delivery shape and its one
     * `line` group — «مركبات النقل والركاب», a fleet — both said carrier, so it
     * publishes a leg like the two siblings beside it.
     */
    public function test_the_heavy_hauler_can_publish_a_trip_like_its_siblings(): void
    {
        $schedules = (int) DB::table('platform_services')->where('key', 'schedules')->value('id');

        /*
         * «نقل دولي» folded into «شحن بري وبحري وجوى» on 2026-08-12 (owner) —
         * one trade under two names, and «دولي» is a scope it already answers.
         *
         * The donor then left «شركات» for «شحن وتوصيل» on 2026-08-16, so the
         * pair is asserted under a root each rather than one: the recipient is
         * still a heavy hauler filed with the companies, and the donor is now
         * beside the carriers where it belongs. Reading both at «شركات» is what
         * this test did, and it is exactly the assumption a root move breaks.
         */
        foreach ([
            'معدات ثقيلة' => 'companies',
            'شحن بري وبحري وجوى' => 'shipping-delivery',
        ] as $name => $slug) {
            $rootId = $this->rootId($slug);

            $this->assertTrue(
                DB::table('category_platform_services')->where('category_id', $rootId)
                    ->where('child_id', $this->childId($name, $rootId))
                    ->where('platform_service_id', $schedules)->where('is_active', 1)->exists(),
                "«{$name}» cannot publish a trip leg"
            );
        }
    }

    /**
     * The rule the whole file exists to serve, asserted as a rule.
     *
     * A standing that offers no selling surface is the defect nothing surfaces:
     * the merchant registers, appears in search, can be delivered from, and has
     * no way to say what he sells. `delivery` and `business_offers` do not count
     * — the first is how goods travel and the second is how a business is found.
     *
     * Scoped to standings that HOLD ACCOUNTS on purpose. The owner builds
     * taxonomy live in the admin, and a child he created ten minutes ago and has
     * not wired yet is a job in progress, not a defect. A child with merchants
     * already standing on it is neither ambiguous nor survivable.
     */
    public function test_no_merchant_stands_on_a_child_that_can_sell_nothing(): void
    {
        $selling = DB::table('platform_services')
            ->whereIn('key', ['menu', 'retail', 'booking', 'schedules'])->pluck('id');

        $standings = DB::table('category_parent_child as p')
            ->join('category_children_master as c', 'c.id', '=', 'p.child_id')
            ->join('categories as r', 'r.id', '=', 'p.parent_id')
            ->select('p.parent_id', 'p.child_id', 'c.name_ar', 'r.name_ar as root_name')
            ->get();

        $mute = [];

        foreach ($standings as $s) {
            if (DB::table('users')->where('category_child_id', $s->child_id)->doesntExist()) {
                continue;
            }

            $canSell = DB::table('category_platform_services')
                ->where('category_id', $s->parent_id)->where('child_id', $s->child_id)
                ->whereIn('platform_service_id', $selling)->where('is_active', 1)->exists();

            if (! $canSell) {
                $mute[] = "«{$s->name_ar}» × {$s->root_name}";
            }
        }

        $this->assertSame([], $mute, 'merchants stand on children with no way to sell: ' . implode('، ', $mute));
    }

    /**
     * The fifth pass, and a different class from the rest of this file: these
     * children could already sell, off a menu. What they could not do was list
     * a catalog product while the child beside them, selling the identical
     * goods, could.
     *
     * The bar is deliberately higher than «the majority has it», which is noise
     * on its own — two dozen service companies under شركات lack retail and
     * should. The rule is the one the third pass learned: THE DONOR'S BRANCH
     * MUST FIT THE TRADE.
     *
     * @dataProvider catalogPairs
     */
    public function test_a_shop_lists_the_same_catalog_as_the_shop_beside_it(string $rootSlug, string $child, string $donor): void
    {
        $rootId = $this->rootId($rootSlug);
        $retail = (int) DB::table('platform_services')->where('key', 'retail')->value('id');

        $config = fn (string $name) => json_decode((string) DB::table('category_service_configs')
            ->where('category_id', $rootId)->where('child_id', $this->childId($name, $rootId))
            ->where('platform_service_id', $retail)->where('is_active', 1)->value('config'), true) ?: [];

        $mine = $config($child);

        $this->assertNotSame([], $mine, "«{$child}» still cannot list a product");

        $this->assertSame(
            $config($donor)['item_groups'] ?? [],
            $mine['item_groups'] ?? [],
            "«{$child}» was given a branch «{$donor}» does not use"
        );
    }

    /** @return array<string,array{0:string,1:string,2:string}> */
    public static function catalogPairs(): array
    {
        return [
            /*
             * «سيارات» ← «معرض سيارات» under معارض stood here from 2026-08-10:
             * the one child of 28 without retail, and 12 merchants who could not
             * list the car they were selling.
             *
             * It is gone on 2026-08-17 because the pair is gone — «خليه معرض
             * سيارات ونفذ الطى والنقل». #53 was folded into #188 and retired,
             * and #188 moved معارض → سيارات carrying the retail service the
             * reinstatement had given it. A pair needs two children under one
             * root and there is now one child under another root, so the case
             * this guarded cannot recur. The repair held; it is not deleted for
             * being wrong.
             */
            // One trade at three sizes; only the middle one had the catalog.
            'هايبر' => ['shops-online', 'هايبر ماركت', 'سوبر ماركت'],
            'مني' => ['shops-online', 'مني ماركت', 'سوبر ماركت'],
        ];
    }

    /** Re-running writes nothing. */
    public function test_the_seeder_is_idempotent(): void
    {
        $count = fn () => [
            DB::table('category_platform_services')->where('is_active', 1)->count(),
            DB::table('category_service_configs')->where('is_active', 1)->count(),
        ];

        $before = $count();

        $this->artisan('db:seed', ['--class' => 'ServiceReinstatementSeeder', '--no-interaction' => true])->run();

        $this->assertSame($before, $count());
    }
}
