<?php

namespace App\Services\Catalog;

use App\Models\ServiceOptionGroupPlacement as Placement;
use Illuminate\Support\Collection;

/**
 * What an option group does inside a service, resolved for one child.
 *
 * A child's own row beats the service-wide default for the SAME (group, item
 * type); anything the child did not override still comes from the default.
 * An override with is_active = false hides the group for that child.
 */
final class ServiceOptionPlacements
{
    /**
     * @return Collection<int,Placement> effective, active placements
     */
    public function for(int $serviceId, int $childId, ?string $surface = null, ?string $itemType = null): Collection
    {
        $rows = Placement::query()
            ->where('platform_service_id', $serviceId)
            ->whereIn('child_id', array_unique([Placement::ALL_CHILDREN, max($childId, 0)]))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $effective = $rows
            ->sortBy(fn (Placement $p) => (int) $p->child_id) // default first, override last
            ->keyBy(fn (Placement $p) => $p->option_group_id . '|' . $p->item_type_key)
            ->filter(fn (Placement $p) => $p->is_active);

        return $effective
            ->when($surface !== null, fn ($c) => $c->filter(fn (Placement $p) => in_array($surface, (array) $p->surfaces, true)))
            ->when($itemType !== null, fn ($c) => $c->filter(fn (Placement $p) => $p->item_type_key === '' || $p->item_type_key === $itemType))
            ->sortBy(fn (Placement $p) => [$p->sort_order, $p->id])
            ->values();
    }
}
