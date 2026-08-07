<?php

namespace Database\Seeders;

use App\Models\PlatformService;
use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Re-apply the owner-approved per-child booking kinds, and nothing else.
 *
 * `ServiceKindsCollapseSeeder` also writes this assignment, but it does so on
 * the way through a much larger job: it rebuilds the branch, re-keys every
 * booking and menu type, deactivates whatever the data file does not name and
 * prunes it. That is the right tool once; it is far too wide a blade when the
 * only thing that drifted is which kinds four children carry.
 *
 * This runs the assignment alone. It writes through ChildServiceWriter so the
 * rest of each config — requires_bookable_item, item_groups, catalog_source —
 * is merged, not rebuilt, and it touches only children named in
 * `service_kinds.php`. A child it does not name is left exactly as found.
 *
 * Why it exists: on 2026-08-07 22:04 a bulk save flattened مستشفى، عيادة، مركز
 * طبي، نادي صحي and معمل تحاليل onto one identical five-kind list — the bulk
 * screen showed a single type picker for the whole batch and wrote it to every
 * child. The screen has been fixed (CategoryServiceBulkController::typesTouched),
 * and this puts back what it flattened.
 */
class BookingChildKindsSeeder extends Seeder
{
    public function run(): void
    {
        $assigned = (require database_path('seeders/data/service_kinds.php'))['booking']['children'] ?? [];

        if (empty($assigned)) {
            $this->command?->warn('No per-child booking kinds are declared — nothing to apply.');

            return;
        }

        $serviceId = (int) PlatformService::query()
            ->where('key', PlatformService::KEY_BOOKING)
            ->value('id');

        if ($serviceId <= 0) {
            $this->command?->warn('The booking service is missing — nothing to apply.');

            return;
        }

        $writer = app(ChildServiceWriter::class);
        $restored = 0;
        $alreadyRight = 0;

        DB::transaction(function () use ($assigned, $serviceId, $writer, &$restored, &$alreadyRight) {
            foreach ($assigned as $childId => $kinds) {
                $childId = (int) $childId;
                $kinds = array_values(array_map('strval', $kinds));

                /*
                 * A child sits under more than one root — «دعاية وإعلان» has a
                 * config under both companies and offices — and the assignment
                 * is keyed by child, so every root it lives under gets it.
                 */
                $roots = DB::table('category_service_configs')
                    ->where('child_id', $childId)
                    ->where('platform_service_id', $serviceId)
                    ->pluck('category_id');

                foreach ($roots as $rootId) {
                    $rootId = (int) $rootId;
                    $stored = $writer->storedConfig($rootId, $childId, $serviceId);

                    if (($stored['allowed_item_types'] ?? []) === $kinds) {
                        $alreadyRight++;

                        continue;
                    }

                    $writer->enable(
                        rootId: $rootId,
                        childId: $childId,
                        serviceId: $serviceId,
                        configPatch: ['allowed_item_types' => $kinds],
                        source: 'booking_child_kinds'
                    );

                    $restored++;
                }
            }
        });

        $this->command?->info("Booking kinds: {$restored} restored, {$alreadyRight} already correct.");

        $filled = $this->fillEmptyFromBranches($serviceId, $writer);

        if ($filled > 0) {
            $this->command?->info("Booking kinds: {$filled} unbounded config(s) bounded from the declared branches.");
        }
    }

    /**
     * An empty `allowed_item_types` is not «nothing» — it is «everything».
     *
     * Both readers treat an empty list as «no restriction»
     * (ResolvesOwnerCatalog, CategoryBServiceSupport::allowsItemType), which was
     * harmless while a branch was a coarse group. After the kinds collapse it
     * means the child may offer all eleven kinds, so a pharmacy saved with
     * nothing ticked came out able to take a hotel stay and a restaurant table.
     *
     * Only children the approved `booking_child_branches.php` already names are
     * bounded, and only through the approved branch→kind map — nothing is
     * invented here. A child with an empty list and no declared branch is left
     * alone and reported.
     */
    private function fillEmptyFromBranches(int $serviceId, ChildServiceWriter $writer): int
    {
        $spec = (require database_path('seeders/data/service_kinds.php'))['booking'] ?? [];
        $map = $spec['map'] ?? [];
        $file = database_path('seeders/data/' . ($spec['child_branches'] ?? ''));

        if (empty($map) || ! is_file($file)) {
            return 0;
        }

        // [child name_ar => [kind keys]], from the declared branches.
        $declared = [];

        foreach (require $file as $children) {
            foreach ($children as $childName => $branchKeys) {
                $kinds = collect($branchKeys)
                    ->map(fn ($key) => $map[(string) $key] ?? null)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if (! empty($kinds)) {
                    $declared[(string) $childName] = $kinds;
                }
            }
        }

        $filled = 0;

        $rows = DB::table('category_service_configs as c')
            ->join('category_children_master as ch', 'ch.id', '=', 'c.child_id')
            ->where('c.platform_service_id', $serviceId)
            ->where('c.is_active', 1)
            ->get(['c.category_id', 'c.child_id', 'ch.name_ar', 'c.config']);

        foreach ($rows as $row) {
            $config = json_decode((string) $row->config, true) ?: [];

            if (! empty($config['allowed_item_types'])) {
                continue;
            }

            $kinds = $declared[(string) $row->name_ar] ?? null;

            if ($kinds === null) {
                $this->command?->warn(
                    "«{$row->name_ar}» offers booking with no kinds and none is declared for it — left as is."
                );

                continue;
            }

            $writer->enable(
                rootId: (int) $row->category_id,
                childId: (int) $row->child_id,
                serviceId: $serviceId,
                configPatch: ['allowed_item_types' => $kinds],
                source: 'booking_child_kinds'
            );

            $filled++;
        }

        return $filled;
    }
}
