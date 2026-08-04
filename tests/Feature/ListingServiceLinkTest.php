<?php

namespace Tests\Feature;

use App\Models\PlatformService;
use Illuminate\Support\Facades\DB;
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

    /** A restaurant's food types must survive the seeder untouched. */
    public function test_a_food_child_keeps_its_menu(): void
    {
        $restaurant = (int) DB::table('category_children_master')->where('name_ar', 'مطعم')->value('id');

        if (! $restaurant) {
            $this->markTestSkipped('No «مطعم» child.');
        }

        $before = $this->allowed($restaurant);

        $this->assertNotEmpty($before);

        DB::beginTransaction();

        try {
            (new \Database\Seeders\ListingServiceLinkSeeder)->run();

            $this->assertSame($before, $this->allowed($restaurant), 'the seeder replaced a menu instead of merging');
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
