<?php

namespace Tests\Feature;

use App\Models\PlatformService;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Retail was fully built — 9 branches, 75 item types, a 579-product catalog —
 * and reached nobody: the service row itself was switched off, so
 * CatalogListingController::retailScope() returned null and the «منتجاتي»
 * screen was blocked for every child wired to it. Three whole roots
 * (ملابس واكسسوارات، شركات، مصانع) were never in the map at all, because it is
 * keyed per ROOT and their children had been classified only under
 * معارض/المحلات.
 *
 * @see \Database\Seeders\RetailChildBranchesSeeder
 * @see \Database\Seeders\data\retail_child_branches
 */
class RetailChildLinkTest extends TestCase
{
    /**
     * These run seeders. Without this trait they ran them against the LIVE dev
     * database and kept the writes — which is how «عيادة» lost eight merchants'
     * specialties and «صيدلية» lost «حقن» during a full-suite run.
     */
    use DatabaseTransactions;

    private function serviceId(): int
    {
        return (int) DB::table('platform_services')->where('key', PlatformService::KEY_RETAIL)->value('id');
    }

    /** @return array<string,mixed> the approved root => child => branches map */
    private function map(): array
    {
        return require database_path('seeders/data/retail_child_branches.php');
    }

    /** @return array<int,int> child ids under a root carrying that exact name */
    private function childIds(string $rootSlug, string $name): array
    {
        return DB::table('category_parent_child as pc')
            ->join('category_children_master as m', 'm.id', '=', 'pc.child_id')
            ->join('categories as c', 'c.id', '=', 'pc.parent_id')
            ->where('c.slug', $rootSlug)
            ->where('m.name_ar', $name)
            ->pluck('m.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * The one switch that made all of it dead. Every config and every link below
     * is unreachable while the service row says no — retailScope() checks
     * PlatformService.is_active, not just the category link.
     */
    public function test_the_retail_service_is_switched_on(): void
    {
        $this->assertSame(
            1,
            (int) DB::table('platform_services')->where('key', PlatformService::KEY_RETAIL)->value('is_active'),
            'retail is off — every retail config below is unreachable'
        );
    }

    /** Both halves, for every child in the approved map. */
    public function test_every_mapped_child_is_offered_the_service(): void
    {
        $serviceId = $this->serviceId();

        foreach ($this->map() as $rootSlug => $children) {
            foreach ($children as $name => $branches) {
                foreach ($this->childIds($rootSlug, $name) as $childId) {
                    $this->assertTrue(
                        DB::table('category_platform_services')
                            ->where('child_id', $childId)
                            ->where('platform_service_id', $serviceId)
                            ->where('is_active', 1)
                            ->exists(),
                        "«{$name}» ({$rootSlug}) has no active retail link"
                    );

                    $this->assertTrue(
                        DB::table('category_service_configs')
                            ->where('child_id', $childId)
                            ->where('platform_service_id', $serviceId)
                            ->where('is_active', 1)
                            ->exists(),
                        "«{$name}» ({$rootSlug}) has a link but no config"
                    );
                }
            }
        }
    }

    /**
     * The trap that keeps «حلويات» out of the new roots: a child is handed its
     * branch's WHOLE type list, so a branch with nothing relevant in it gives
     * the merchant an empty product picker and no way to tell why.
     */
    public function test_no_child_is_given_an_empty_branch(): void
    {
        $serviceId = $this->serviceId();

        $branchIds = DB::table('platform_service_item_groups')
            ->where('platform_service_id', $serviceId)
            ->pluck('id', 'key');

        foreach ($this->map() as $rootSlug => $children) {
            foreach ($children as $name => $branches) {
                foreach ($branches as $key) {
                    $this->assertTrue($branchIds->has($key), "unknown branch «{$key}» for «{$name}»");

                    $this->assertGreaterThan(
                        0,
                        DB::table('platform_service_item_group_type')->where('group_id', $branchIds[$key])->count(),
                        "«{$name}» ({$rootSlug}) was given «{$key}», which carries no item type at all"
                    );
                }
            }
        }
    }

    /**
     * The retail contract: an item type key IS a product_category_children slug
     * — that mirror is the only thing joining a merchant's allowed types to the
     * shared catalog, and there is no join table to fall back on.
     *
     * @see docs/retail-branches-taxonomy.md
     */
    public function test_every_allowed_type_reaches_the_catalog(): void
    {
        $slugs = DB::table('product_category_children')->whereNull('deleted_at')->pluck('slug')->all();

        $types = DB::table('category_service_configs')
            ->where('platform_service_id', $this->serviceId())
            ->where('is_active', 1)
            ->pluck('config')
            ->flatMap(fn ($c) => (json_decode((string) $c, true) ?: [])['allowed_item_types'] ?? [])
            ->unique();

        $this->assertNotEmpty($types);

        foreach ($types as $key) {
            $this->assertContains($key, $slugs, "retail type «{$key}» has no catalog child — the picker will be empty");
        }
    }

    /**
     * Retail is for goods held in stock. A courier, a freight company or an
     * import agent sells work, and belongs on booking — putting them on retail
     * would hand them a product catalogue they can never fill.
     */
    public function test_a_service_child_was_not_put_on_retail(): void
    {
        $serviceId = $this->serviceId();

        $services = ['مندوب', 'شركة', 'شحن بري وبحري وجوى', 'نقل دولي', 'استيراد وتصدير', 'إدارة صفحات'];

        foreach ($services as $name) {
            $ids = DB::table('category_children_master')->where('name_ar', $name)->pluck('id');

            foreach ($ids as $id) {
                $this->assertFalse(
                    DB::table('category_platform_services')
                        ->where('child_id', $id)
                        ->where('platform_service_id', $serviceId)
                        ->where('is_active', 1)
                        ->exists(),
                    "«{$name}» sells work, not stock — it must not be on retail"
                );
            }
        }
    }

    /** The whole clothing root had no selling surface of any kind. */
    public function test_the_clothing_root_can_now_list(): void
    {
        $serviceId = $this->serviceId();

        $kids = DB::table('category_parent_child as pc')
            ->join('categories as c', 'c.id', '=', 'pc.parent_id')
            ->where('c.slug', 'cloth-accessories')
            ->pluck('pc.child_id');

        $this->assertNotEmpty($kids);

        foreach ($kids as $childId) {
            $this->assertTrue(
                DB::table('category_platform_services')
                    ->where('child_id', $childId)
                    ->where('platform_service_id', $serviceId)
                    ->where('is_active', 1)
                    ->exists(),
                "clothing child #{$childId} still has nothing to sell with"
            );
        }
    }

    /** Re-running must not add a second link or config for anybody. */
    public function test_the_seeder_is_idempotent(): void
    {
        $count = fn () => [
            DB::table('category_service_configs')->count(),
            DB::table('category_platform_services')->count(),
        ];

        $before = $count();

        DB::beginTransaction();

        try {
            (new \Database\Seeders\RetailChildBranchesSeeder)->run();

            $this->assertSame($before, $count());
        } finally {
            DB::rollBack();
        }
    }

    /**
     * A child this map does not name still gets a retail config — from
     * `ChildRootMovesSeeder::adoptRootShape()`, which copies the ROOT'S
     * MAJORITY branch. Under المحلات that majority is أثاث ومفروشات, so
     * «مكملات غذائية» arrived from الرياضة and was handed furniture,
     * chandeliers, carpets and mattresses. Twelve types, not one of them a
     * supplement, while the move's own reason had said «بجوار عطور وأدوات
     * تجميل».
     *
     * Silence in this file is not neutral. That is what this pins.
     */
    public function test_the_supplements_shop_is_on_the_health_branch(): void
    {
        $rootId = (int) DB::table('categories')->where('slug', 'shops-online')->value('id');
        $childId = (int) DB::table('category_children_master')->where('name_ar', 'مكملات غذائية')->value('id');
        $retailId = (int) DB::table('platform_services')->where('key', 'retail')->value('id');

        $config = json_decode((string) DB::table('category_service_configs')
            ->where('category_id', $rootId)->where('child_id', $childId)
            ->where('platform_service_id', $retailId)->where('is_active', 1)->value('config'), true) ?: [];

        $branch = (int) DB::table('platform_service_item_groups')->where('key', 'beauty_health_retail')->value('id');

        $this->assertSame([$branch], $config['item_groups'] ?? [], 'the supplements shop is on the wrong branch');

        // …and the branch names the trade, which is this file's own rule: a
        // child may only be given a branch that HAS a matching item type.
        $this->assertContains('supplements', $config['allowed_item_types'] ?? []);

        $this->assertNotContains('chandeliers_lighting', $config['allowed_item_types'] ?? []);
    }

    /** @return array<string,array<int,string>> the approved trade => types narrowing */
    private function narrowing(): array
    {
        return require database_path('seeders/data/retail_child_types.php');
    }

    /** @return array<int,object> every switched-on retail config, with its root and child name */
    private function liveConfigs(): array
    {
        return DB::table('category_service_configs as c')
            ->join('categories as r', 'r.id', '=', 'c.category_id')
            ->join('category_children_master as m', 'm.id', '=', 'c.child_id')
            ->where('c.platform_service_id', $this->serviceId())
            ->where('c.is_active', 1)
            ->get(['r.slug as root', 'm.name_ar as name', 'c.config'])
            ->all();
    }

    /**
     * A branch is a shelf, not a shop.
     *
     * applyChild() expands a branch WHOLE, which is right the day the branch is
     * created and wrong the moment two trades share it. أثاث ومفروشات carries
     * twelve types over eleven trades, so a سجاد shop was offered mattresses,
     * chandeliers and china; jewelry carries two, and each of its two children
     * was offered the other's metal. Thirty-eight children under المحلات had no
     * vocabulary of their own — this was why. They HAD product types. Not
     * theirs.
     *
     * @see \Database\Seeders\data\retail_child_types
     */
    public function test_a_trade_is_not_offered_its_neighbours_shelf(): void
    {
        $narrowing = $this->narrowing();

        foreach ($this->liveConfigs() as $row) {
            if (! isset($narrowing[$row->name])) {
                continue;
            }

            $held = json_decode((string) $row->config, true)['allowed_item_types'] ?? [];
            $extra = array_diff($held, $narrowing[$row->name]);

            $this->assertEmpty(
                $extra,
                "«{$row->name}» ({$row->root}) is still offered " . implode('، ', $extra)
            );
        }
    }

    /**
     * The narrowing INTERSECTS, and an empty intersection must keep the whole
     * branch rather than write nothing — because nothing does not mean nothing.
     * An empty allowed_item_types reads as «everything» in both readers, so a
     * typo'd or off-branch entry here would hand the child the entire service
     * instead of restricting it, which is the exact inversion of the intent.
     *
     * @see \Database\Seeders\BoundUnboundedConfigsSeeder
     */
    public function test_no_narrowing_leaves_a_child_with_nothing(): void
    {
        foreach ($this->liveConfigs() as $row) {
            $this->assertNotEmpty(
                json_decode((string) $row->config, true)['allowed_item_types'] ?? [],
                "«{$row->name}» ({$row->root}) has an empty type list, which reads as EVERY type"
            );
        }
    }

    /** A type named in the narrowing that no branch carries is a silent no-op. */
    public function test_every_narrowed_type_exists(): void
    {
        $known = collect(require database_path('seeders/data/retail_taxonomy.php'))
            ->flatMap(fn ($branch) => array_keys($branch['types']))
            ->all();

        foreach ($this->narrowing() as $name => $types) {
            foreach ($types as $key) {
                $this->assertContains($key, $known, "«{$name}» names «{$key}», which is not a retail item type");
            }
        }
    }

    /**
     * Silence in the branch map is not neutral — a child it does not name still
     * gets a config from elsewhere (adoptRootShape, ServiceReinstatementSeeder)
     * and the narrowing cannot reach it. That is how the supplements shop got
     * furniture, and how a car showroom kept motorcycles after the reinstatement
     * that created it.
     */
    public function test_every_live_retail_config_is_named_by_the_map(): void
    {
        $map = $this->map();

        $unmapped = [];

        foreach ($this->liveConfigs() as $row) {
            if (isset($map[$row->root][$row->name])) {
                continue;
            }

            $unmapped[] = "{$row->root} → {$row->name}";
        }

        $this->assertSame([], $unmapped, 'retail configs no branch map names: ' . implode('، ', $unmapped));
    }

    /** A child's Menu link must survive being given Retail as well. */
    public function test_the_supermarket_keeps_its_menu(): void
    {
        $child = (int) DB::table('category_children_master')->where('name_ar', 'سوبر ماركت')->value('id');

        if (! $child) {
            $this->markTestSkipped('No «سوبر ماركت» child.');
        }

        $menuId = (int) DB::table('platform_services')->where('key', PlatformService::KEY_MENU)->value('id');

        DB::beginTransaction();

        try {
            (new \Database\Seeders\RetailChildBranchesSeeder)->run();

            $this->assertTrue(
                DB::table('category_platform_services')
                    ->where('child_id', $child)->where('platform_service_id', $menuId)->where('is_active', 1)->exists(),
                'the retail seeder took the supermarket off Menu'
            );
        } finally {
            DB::rollBack();
        }
    }
}
