<?php

namespace Database\Seeders;

use App\Models\PlatformService;
use App\Models\PlatformServiceItemGroup;
use App\Models\PlatformServiceItemType;
use Illuminate\Database\Seeder;

/**
 * Retail branches taxonomy — the Retail counterpart of DeliveryBranchesSeeder /
 * BookingBranchesSeeder.
 *
 * Registers the 8 retail branches (platform_service_item_groups) and their
 * ~53 item types (platform_service_item_types), then files each type into its
 * branch via the group_type pivot. Both the branches and the types are read
 * from data/retail_taxonomy.php — the single source of truth shared with
 * RetailProductTaxonomySeeder, whose product_categories/children mirror these
 * keys 1:1 (branch key == product_categories.slug, type key ==
 * product_category_children.slug). See docs/retail-branches-taxonomy.md.
 *
 * Idempotent and fingerprint-stable: sort orders are derived deterministically
 * from array position (not max()+1), so re-runs produce byte-identical rows.
 *
 * Requires PlatformServiceSeeder to have registered the `retail` service first.
 */
class RetailBranchesSeeder extends Seeder
{
    public function run(): void
    {
        $retail = PlatformService::where('key', PlatformService::KEY_RETAIL)->first();

        if (! $retail) {
            $this->command?->warn('Service "retail" not found — run PlatformServiceSeeder first.');

            return;
        }

        $serviceId = (int) $retail->id;
        $taxonomy = require __DIR__ . '/data/retail_taxonomy.php';

        $branchSort = 0;
        $typeSort = 0;

        foreach ($taxonomy as $branchKey => $branch) {
            $group = PlatformServiceItemGroup::updateOrCreate(
                ['key' => $branchKey],
                [
                    'platform_service_id' => $serviceId,
                    'name_ar' => $branch['name_ar'],
                    'name_en' => $branch['name_en'],
                    'sort_order' => ++$branchSort,
                    'is_active' => 1,
                ]
            );

            $typeIds = [];

            foreach ($branch['types'] as $typeKey => [$ar, $en]) {
                $type = PlatformServiceItemType::updateOrCreate(
                    ['platform_service_id' => $serviceId, 'key' => $typeKey],
                    ['name_ar' => $ar, 'name_en' => $en, 'is_active' => 1, 'sort_order' => ++$typeSort]
                );

                $typeIds[] = (int) $type->id;
            }

            $group->itemTypes()->syncWithoutDetaching($typeIds);
        }

        $this->command?->info("retail branches: " . count($taxonomy) . " groups, {$typeSort} types.");
    }
}
