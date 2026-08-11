<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;

/**
 * Booking counterpart of DeliveryChildBranchesSeeder: re-applies the approved
 * child→branch layout for the Booking service from data/booking_child_branches.php.
 * Requires BookingBranchesSeeder to have run first (branches must exist).
 * Same guarantees: idempotent, additive, preserves other config keys
 * (booking behaviour flags, catalog_source, …).
 *
 * ServiceKindsCollapseSeeder moved all 294 booking item types onto the eleven
 * kinds under `booking_kinds` and switched the twelve branches this file names
 * — clinic, hotel, restaurant_table, sports, halls_events … — off, empty. Run
 * standalone, the inherited expansion therefore wrote an EMPTY
 * allowed_item_types onto SIXTY-FOUR booking configs, and empty does not read
 * as «nothing», it reads as «everything»: a clinic that could take a hotel
 * stay, which is the precise failure BoundUnboundedConfigsSeeder exists to
 * clean up after.
 *
 * A full seed hid it — the collapse runs eight lines later and re-derives the
 * kind from the branch. Nothing hid it from anyone running this seeder alone.
 */
class BookingChildBranchesSeeder extends DeliveryChildBranchesSeeder
{
    protected function serviceKey(): string
    {
        return 'booking';
    }

    /**
     * The branch names a kind, and a child may outrank its branch.
     *
     * «clinic» maps to «حجز موعد», but عيادة was given كشف، متابعة، استشارة
     * أونلاين and زيارة منزلية by name — so translating the branch alone would
     * flatten the four specialised kinds back onto one, which is exactly what
     * the collapse's own docblock warns against.
     */
    protected function translateTypes(array $branchKeys, array $typeKeys, int $childId): array
    {
        $spec = (require __DIR__ . '/data/service_kinds.php')['booking'];

        if (isset($spec['children'][$childId])) {
            return $spec['children'][$childId];
        }

        $kinds = array_values(array_unique(array_filter(
            array_map(fn ($key) => $spec['map'][$key] ?? null, $branchKeys)
        )));

        return $kinds ?: $typeKeys;
    }

    /** The eleven kinds live under one branch now; the old twelve are off. */
    protected function translateGroups(array $branchKeys, array $groupIds): array
    {
        $spec = (require __DIR__ . '/data/service_kinds.php')['booking'];

        if (! array_intersect($branchKeys, array_keys($spec['map'] ?? []))) {
            return $groupIds;
        }

        $id = (int) DB::table('platform_service_item_groups')
            ->where('platform_service_id', DB::table('platform_services')->where('key', 'booking')->value('id'))
            ->where('key', $spec['branch']['key'])
            ->value('id');

        return $id ? [$id] : $groupIds;
    }

    protected function dataFile(): string
    {
        return __DIR__ . '/data/booking_child_branches.php';
    }

    protected function newConfigDefaults(): array
    {
        return [
            'booking_modes' => [],
            'item_family' => null,
            'requires_bookable_item' => true,
            'requires_start_end' => true,
            'supports_quantity' => false,
            'supports_guest_count' => false,
            'supports_extras' => false,
            'required_fields' => [],
        ];
    }
}
