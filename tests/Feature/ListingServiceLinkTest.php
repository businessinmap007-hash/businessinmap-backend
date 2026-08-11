<?php

namespace Tests\Feature;

use App\Models\PlatformService;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * `menu_items` is the platform's listing surface, but the service that owns it
 * reached 19 children — all restaurants and groceries — and every item type on
 * it was a food branch. An estate agent listing «شقة — غرفتين — سوبر لوكس» was
 * making listings the taxonomy did not know it could make: it worked only
 * because nothing gates menu_items on the service.
 *
 * @see \Database\Seeders\ListingServiceLinkSeeder
 */
class ListingServiceLinkTest extends TestCase
{
    /**
     * These run seeders. Without this trait they ran them against the LIVE dev
     * database and kept the writes — which is how «عيادة» lost eight merchants'
     * specialties and «صيدلية» lost «حقن» during a full-suite run.
     */
    use DatabaseTransactions;

    private function menuServiceId(): int
    {
        return (int) DB::table('platform_services')->where('key', PlatformService::KEY_MENU)->value('id');
    }

    /** @return array<int,string> the item types a child may list under a root */
    private function allowed(int $childId): array
    {
        $types = [];

        $rows = DB::table('category_service_configs')
            ->where('child_id', $childId)
            ->where('platform_service_id', $this->menuServiceId())
            ->where('is_active', 1)
            ->pluck('config');

        foreach ($rows as $config) {
            $types = array_merge($types, (json_decode((string) $config, true) ?: [])['allowed_item_types'] ?? []);
        }

        return array_values(array_unique($types));
    }

    /** Item types stored for a child, active or not — the merge is what matters. */
    private function storedTypes(int $childId): array
    {
        $types = [];

        foreach (
            DB::table('category_service_configs')
                ->where('child_id', $childId)
                ->where('platform_service_id', $this->menuServiceId())
                ->pluck('config') as $config
        ) {
            $types = array_merge($types, (json_decode((string) $config, true) ?: [])['allowed_item_types'] ?? []);
        }

        return array_values(array_unique($types));
    }

    private function isOffered(int $childId): bool
    {
        return DB::table('category_platform_services')
            ->where('child_id', $childId)
            ->where('platform_service_id', $this->menuServiceId())
            ->where('is_active', 1)
            ->exists();
    }

    /** Each family lists its own kind of thing, and only its own. */
    public function test_every_listing_child_may_list_its_own_kind(): void
    {
        $map = require database_path('seeders/data/listing_service_children.php');

        foreach ($map['types'] as $key => $spec) {
            foreach ($spec['children'] as $childId) {
                $name = DB::table('category_children_master')->where('id', $childId)->value('name_ar');

                $this->assertContains(
                    $key,
                    $this->allowed((int) $childId),
                    "«{$name}» cannot list a {$key}"
                );
            }
        }
    }

    /**
     * The landmine this whole area keeps stepping on: a config says what may be
     * listed, but a business is only OFFERED the service through the other
     * table. One without the other is unreachable, silently.
     */
    public function test_every_listing_child_is_actually_offered_the_service(): void
    {
        $map = require database_path('seeders/data/listing_service_children.php');

        foreach ($map['types'] as $spec) {
            foreach ($spec['children'] as $childId) {
                $name = DB::table('category_children_master')->where('id', $childId)->value('name_ar');

                $this->assertTrue(
                    $this->isOffered((int) $childId),
                    "«{$name}» has a config nobody can reach"
                );
            }
        }
    }

    /** A car showroom is not offered «وحدة عقارية». */
    public function test_a_family_is_not_offered_another_familys_kind(): void
    {
        $map = require database_path('seeders/data/listing_service_children.php');

        $keys = array_keys($map['types']);

        foreach ($map['types'] as $key => $spec) {
            foreach ($spec['children'] as $childId) {
                $allowed = $this->allowed((int) $childId);

                foreach (array_diff($keys, [$key]) as $foreign) {
                    $this->assertNotContains($foreign, $allowed, "child #{$childId} was offered {$foreign}");
                }
            }
        }
    }

    /**
     * …nor is it offered «منيو».
     *
     * The test above compares the three listing kinds against each other, and
     * menu_food is not one of them, so it walked straight through: fifteen
     * configs carried «منيو» beside their real kind, and a furniture factory
     * (35 businesses), a car showroom (9) and an estate agent (16) could each
     * publish a food menu.
     *
     * Two things put it there and neither was wrong on its own. The kinds
     * collapse falls back to menu_food when nothing tells it better, and it was
     * never told about these ten children. Then this seeder MERGES by design —
     * rightly, since a child may hold settings the map knows nothing about — so
     * it added the real kind BESIDE the food rather than instead of it.
     *
     * «مالك عقار» is the proof of both: the one listing child with no prior
     * menu config, nothing to merge with, and it came out clean.
     */
    public function test_a_listing_child_is_not_offered_the_food_menu(): void
    {
        $map = require database_path('seeders/data/listing_service_children.php');
        $default = (require database_path('seeders/data/service_kinds.php'))['menu']['default'];

        foreach ($map['types'] as $spec) {
            foreach ($spec['children'] as $childId) {
                $name = DB::table('category_children_master')->where('id', $childId)->value('name_ar');

                $this->assertNotContains(
                    $default,
                    $this->allowed((int) $childId),
                    "«{$name}» lists furniture, cars or property — it must not also publish a food menu"
                );
            }
        }
    }

    /**
     * The other half, from the food side: «منيو» belongs to the trades that
     * cook. Anyone else holding it is the default speaking, not a decision.
     */
    public function test_the_food_menu_belongs_to_the_kitchens(): void
    {
        $default = (require database_path('seeders/data/service_kinds.php'))['menu']['default'];

        $kitchens = [
            'مطعم', 'مطعم وكافيه', 'كافيه', 'مجمع مطاعم', 'أكل بيتى', 'عربية قهوة ومأكولات',
            // «المخابز والحلويات مطابخ» and «عصائر مطبخ» — the owner's rulings
            // of 2026-08-10. «بن» was ruled the opposite («يبيع حبوب فقط») and
            // is deliberately absent: it held menu_food until 2026-08-11
            // because menu_child_branches.php still called it a drinks seller.
            'مخابز', 'حلويات', 'عصائر',
        ];

        $holders = DB::table('category_service_configs as c')
            ->join('category_children_master as m', 'm.id', '=', 'c.child_id')
            ->where('c.platform_service_id', $this->menuServiceId())
            ->where('c.is_active', 1)
            ->get(['m.name_ar', 'c.config'])
            ->filter(fn ($r) => in_array($default, json_decode((string) $r->config, true)['allowed_item_types'] ?? [], true))
            ->pluck('name_ar')
            ->unique()
            ->values()
            ->all();

        $this->assertSame([], array_values(array_diff($holders, $kitchens)),
            'these do not cook and hold «منيو»: ' . implode('، ', array_diff($holders, $kitchens)));
    }

    /**
     * The collapse retired every food branch's item types onto the five kinds,
     * which left fresh_market, bakery_sweets and supermarket carrying nothing.
     * Run on its own, MenuChildBranchesSeeder therefore wrote an EMPTY type
     * list onto all 19 menu children — and empty reads as EVERY kind, not none.
     * A full seed hid it: the collapse runs six lines later and re-derives the
     * kind from the branch.
     */
    public function test_the_menu_branch_seeder_does_not_blank_a_child(): void
    {
        $before = $this->menuTypeCounts();

        DB::beginTransaction();

        try {
            (new \Database\Seeders\MenuChildBranchesSeeder)->run();

            foreach ($this->menuTypeCounts() as $id => $after) {
                $this->assertGreaterThan(0, $after, "menu config #{$id} came out empty, which means EVERY kind");
                $this->assertSame($before[$id] ?? $after, $after, "menu config #{$id} changed on a re-run");
            }
        } finally {
            DB::rollBack();
        }
    }

    /** @return array<int,int> config id => how many kinds it allows */
    private function menuTypeCounts(): array
    {
        return DB::table('category_service_configs')
            ->where('platform_service_id', $this->menuServiceId())
            ->where('is_active', 1)
            ->get(['id', 'config'])
            ->mapWithKeys(fn ($r) => [(int) $r->id => count(json_decode((string) $r->config, true)['allowed_item_types'] ?? [])])
            ->all();
    }

    /** These are not food and must not sit among it. */
    public function test_the_new_types_sit_in_their_own_branch(): void
    {
        $map = require database_path('seeders/data/listing_service_children.php');

        $branchId = DB::table('platform_service_item_groups')
            ->where('platform_service_id', $this->menuServiceId())
            ->where('key', $map['branch']['key'])
            ->value('id');

        $this->assertNotNull($branchId, 'the listings branch does not exist');

        foreach (array_keys($map['types']) as $key) {
            $typeId = DB::table('platform_service_item_types')
                ->where('platform_service_id', $this->menuServiceId())
                ->where('key', $key)
                ->value('id');

            $this->assertNotNull($typeId, "«{$key}» does not exist");

            $this->assertTrue(
                DB::table('platform_service_item_group_type')
                    ->where('group_id', $branchId)->where('item_type_id', $typeId)->exists(),
                "«{$key}» is filed outside the listings branch"
            );
        }
    }

    /**
     * A restaurant's food kinds must survive the seeder untouched — it merges
     * into a stored config, it never replaces one.
     *
     * Read regardless of `is_active`: whether the owner has the restaurant's
     * menu switched on is his business, and this test is about the merge.
     */
    public function test_a_food_child_keeps_its_menu(): void
    {
        $restaurant = (int) DB::table('category_children_master')->where('name_ar', 'مطعم')->value('id');

        if (! $restaurant) {
            $this->markTestSkipped('No «مطعم» child.');
        }

        $before = $this->storedTypes($restaurant);

        $this->assertNotEmpty($before, 'the restaurant has no stored menu config at all');

        DB::beginTransaction();

        try {
            (new \Database\Seeders\ListingServiceLinkSeeder)->run();

            $this->assertSame($before, $this->storedTypes($restaurant), 'the seeder replaced a menu instead of merging');
        } finally {
            DB::rollBack();
        }
    }

    /** Re-running must not add a second branch, type, config or link. */
    public function test_the_seeder_is_idempotent(): void
    {
        $count = fn () => [
            DB::table('platform_service_item_groups')->count(),
            DB::table('platform_service_item_types')->count(),
            DB::table('category_service_configs')->count(),
            DB::table('category_platform_services')->count(),
        ];

        $before = $count();

        DB::beginTransaction();

        try {
            (new \Database\Seeders\ListingServiceLinkSeeder)->run();

            $this->assertSame($before, $count());
        } finally {
            DB::rollBack();
        }
    }
}
