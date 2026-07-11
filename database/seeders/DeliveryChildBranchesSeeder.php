<?php

namespace Database\Seeders;

use App\Models\CategoryPlatformService;
use App\Models\CategoryServiceConfig;
use App\Models\PlatformService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Re-runnable child→branch delivery configuration.
 *
 * Applies the approved delivery-branch layout (docs/delivery-branches-taxonomy.md)
 * to every category child listed in data/delivery_child_branches.php: activates
 * the Delivery service link and stores config.item_groups + the branch-expanded
 * config.allowed_item_types — the same shape the services-bulk screen writes.
 *
 * Requires DeliveryBranchesSeeder to have run first (branches must exist).
 *
 * Idempotent and additive: it never deactivates anything and preserves any other
 * config keys (has_delivery, delivery_type, …). Fees are not touched — they are
 * managed separately in services-bulk. Children matched by root slug + name_ar,
 * so duplicate names within a root are all covered.
 */
class DeliveryChildBranchesSeeder extends Seeder
{
    /** Platform service key this seeder configures. */
    protected function serviceKey(): string
    {
        return 'delivery';
    }

    /** Data file with the approved [root slug => [child name => branch keys]] map. */
    protected function dataFile(): string
    {
        return __DIR__ . '/data/delivery_child_branches.php';
    }

    /** Behaviour defaults for a config row that doesn't exist yet. */
    protected function newConfigDefaults(): array
    {
        return [
            'has_delivery' => true,
            'delivery_type' => 'distance',
            'max_radius_km' => 0,
            'supports_scheduled_delivery' => false,
        ];
    }

    public function run(): void
    {
        $service = PlatformService::where('key', $this->serviceKey())->first();

        if (! $service) {
            $this->command?->warn("Service '{$this->serviceKey()}' not found — run PlatformServiceSeeder first.");

            return;
        }

        $serviceId = (int) $service->id;

        $map = require $this->dataFile();

        // branch key => [id, member type keys of the delivery service]
        $branches = DB::table('platform_service_item_groups')->pluck('id', 'key')->all();

        $typesByGroup = DB::table('platform_service_item_group_type as gt')
            ->join('platform_service_item_types as t', 't.id', '=', 'gt.item_type_id')
            ->where('t.platform_service_id', $serviceId)
            ->get(['gt.group_id', 't.key'])
            ->groupBy('group_id')
            ->map(fn ($rows) => $rows->pluck('key')->map(fn ($k) => (string) $k)->all());

        $applied = 0;
        $missingRoots = [];
        $missingChildren = [];
        $missingBranches = [];

        foreach ($map as $rootSlug => $children) {
            $root = DB::table('categories')
                ->where('parent_id', 0)
                ->where('slug', $rootSlug)
                ->first(['id', 'name_ar']);

            if (! $root) {
                $missingRoots[] = $rootSlug;
                continue;
            }

            foreach ($children as $childName => $branchKeys) {
                $groupIds = [];
                $typeKeys = [];

                foreach ($branchKeys as $key) {
                    if (! isset($branches[$key])) {
                        $missingBranches[$key] = true;
                        continue;
                    }

                    $groupId = (int) $branches[$key];
                    $groupIds[] = $groupId;
                    $typeKeys = array_merge($typeKeys, $typesByGroup->get($groupId, []));
                }

                $groupIds = array_values(array_unique($groupIds));
                $typeKeys = array_values(array_unique($typeKeys));

                if (empty($groupIds)) {
                    continue;
                }

                $childIds = DB::table('category_parent_child as pc')
                    ->join('category_children_master as ch', 'ch.id', '=', 'pc.child_id')
                    ->where('pc.parent_id', (int) $root->id)
                    ->where('ch.name_ar', $childName)
                    ->pluck('ch.id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                if (empty($childIds)) {
                    $missingChildren[] = "{$rootSlug} → {$childName}";
                    continue;
                }

                foreach ($childIds as $childId) {
                    $this->applyChild((int) $root->id, $childId, $serviceId, $groupIds, $typeKeys);
                    $applied++;
                }
            }
        }

        $this->command?->info("{$this->serviceKey()} child branches applied: {$applied}");

        if (! empty($missingRoots)) {
            $this->command?->warn('Missing roots: ' . implode(', ', $missingRoots));
        }

        if (! empty($missingChildren)) {
            $this->command?->warn('Missing children: ' . implode('، ', $missingChildren));
        }

        if (! empty($missingBranches)) {
            $this->command?->warn('Missing branches (run DeliveryBranchesSeeder): ' . implode(', ', array_keys($missingBranches)));
        }
    }

    private function applyChild(int $rootId, int $childId, int $serviceId, array $groupIds, array $typeKeys): void
    {
        DB::transaction(function () use ($rootId, $childId, $serviceId, $groupIds, $typeKeys) {
            $link = CategoryPlatformService::query()->firstOrNew([
                'category_id' => $rootId,
                'child_id' => $childId,
                'platform_service_id' => $serviceId,
            ]);

            if (! $link->exists || ! $link->is_active) {
                $sortOrder = (int) ($link->sort_order ?: 0);

                if ($sortOrder <= 0) {
                    $sortOrder = 1 + (int) CategoryPlatformService::query()
                        ->where('category_id', $rootId)
                        ->where('child_id', $childId)
                        ->max('sort_order');
                }

                $link->fill(['is_active' => 1, 'sort_order' => $sortOrder])->save();
            }

            $config = CategoryServiceConfig::query()->firstOrNew([
                'category_id' => $rootId,
                'child_id' => $childId,
                'platform_service_id' => $serviceId,
            ]);

            // Preserve existing behaviour keys; only own the branch selection.
            $data = is_array($config->config) ? $config->config : [];

            if (! $config->exists) {
                $data += $this->newConfigDefaults();
            }

            $data['item_groups'] = $groupIds;
            $data['allowed_item_types'] = $typeKeys;

            $config->fill([
                'config' => $data,
                'is_active' => 1,
                'sort_order' => (int) ($config->sort_order ?: 1),
            ])->save();
        });
    }
}
