<?php

namespace Database\Seeders;

use App\Models\PlatformService;
use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * An empty `allowed_item_types` is not «nothing» — it is «everything».
 *
 * Both readers take a missing list as «no restriction»: ResolvesOwnerCatalog
 * filters with `->when(! empty($restricted))`, and
 * CategoryBServiceSupport::allowsItemType() returns true outright. So a child
 * saved with nothing ticked does not lose the service — it gains every type the
 * service has. «صيدلية» came out of one such save able to take a hotel stay,
 * and «خدمة ليموزين» able to run every delivery mechanism on the platform.
 *
 * This bounds those, and only those. A config that already names its types is
 * never touched, so it cannot overwrite what a merchant or the owner curated —
 * the failure mode that made the branch seeders dangerous to re-run.
 *
 * Everything it writes comes from an approved declaration:
 *
 *   booking  — the branch→KIND map in service_kinds.php, never a branch
 *              expansion. The collapse put all eleven kinds in one branch, so
 *              expanding «أنواع الحجز» would hand every child all of them,
 *              which is the flattening this whole line of work exists to stop.
 *   others   — the child's declared branches expanded to their live types,
 *              which is what those branches mean.
 *
 * `retail` is deliberately absent: its list is the 1:1 mirror onto
 * product_category_children.slug that scopes the shared catalog, not a branch
 * expansion, and guessing it from branches would unplug products.
 *
 * A child with an empty list and no declaration is reported, never invented for.
 */
class BoundUnboundedConfigsSeeder extends Seeder
{
    /** service key => the approved child→branch file that speaks for it. */
    private const SOURCES = [
        PlatformService::KEY_BOOKING => 'booking_child_branches.php',
        PlatformService::KEY_DELIVERY => 'delivery_child_branches.php',
        PlatformService::KEY_MENU => 'menu_child_branches.php',
    ];

    public function run(): void
    {
        $writer = app(ChildServiceWriter::class);
        $bound = 0;
        $unclaimed = [];

        $rows = DB::table('category_service_configs as c')
            ->join('platform_services as s', 's.id', '=', 'c.platform_service_id')
            ->join('category_children_master as ch', 'ch.id', '=', 'c.child_id')
            ->where('c.is_active', 1)
            ->where('s.is_active', 1)
            ->get(['c.category_id', 'c.child_id', 'ch.name_ar', 's.id as service_id', 's.key as service_key', 'c.config']);

        foreach ($rows as $row) {
            $config = json_decode((string) $row->config, true) ?: [];

            if (! empty($config['allowed_item_types'])) {
                continue;
            }

            $types = $this->declaredTypesFor(
                (string) $row->service_key,
                (int) $row->service_id,
                (string) $row->name_ar
            );

            if (empty($types)) {
                $unclaimed[] = "{$row->name_ar}/{$row->service_key}";

                continue;
            }

            $writer->enable(
                rootId: (int) $row->category_id,
                childId: (int) $row->child_id,
                serviceId: (int) $row->service_id,
                configPatch: ['allowed_item_types' => $types],
                source: 'bound_unbounded'
            );

            $bound++;
        }

        $this->command?->info("Unbounded configs: {$bound} bounded from their declared branches.");

        foreach (array_unique($unclaimed) as $name) {
            $this->command?->warn("«{$name}» allows everything and declares nothing — left as is.");
        }
    }

    /**
     * @return array<int,string> the type keys the child's declaration implies
     */
    private function declaredTypesFor(string $serviceKey, int $serviceId, string $childName): array
    {
        $branchKeys = $this->declaredBranches($serviceKey, $childName);

        if (empty($branchKeys)) {
            return [];
        }

        if ($serviceKey === PlatformService::KEY_BOOKING) {
            $map = (require database_path('seeders/data/service_kinds.php'))['booking']['map'] ?? [];

            return collect($branchKeys)
                ->map(fn ($key) => $map[$key] ?? null)
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return DB::table('platform_service_item_group_type as gt')
            ->join('platform_service_item_types as t', 't.id', '=', 'gt.item_type_id')
            ->join('platform_service_item_groups as g', 'g.id', '=', 'gt.group_id')
            ->whereIn('g.key', $branchKeys)
            ->where('t.platform_service_id', $serviceId)
            ->where('t.is_active', 1)
            ->pluck('t.key')
            ->map(fn ($key) => trim((string) $key))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The files are keyed by root slug then child name_ar. A child name is
     * unique enough across the four files that the root slug adds nothing here
     * — and reading every root means a child that moved home is still found.
     *
     * @return array<int,string>
     */
    private function declaredBranches(string $serviceKey, string $childName): array
    {
        $file = database_path('seeders/data/' . (self::SOURCES[$serviceKey] ?? ''));

        if (! isset(self::SOURCES[$serviceKey]) || ! is_file($file)) {
            return [];
        }

        foreach (require $file as $children) {
            if (isset($children[$childName])) {
                return array_map('strval', $children[$childName]);
            }
        }

        return [];
    }
}
