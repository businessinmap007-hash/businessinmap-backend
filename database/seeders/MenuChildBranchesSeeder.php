<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;

/**
 * Menu counterpart of DeliveryChildBranchesSeeder: re-applies the approved
 * child→branch layout for the Menu service from data/menu_child_branches.php.
 * Requires MenuBranchesSeeder to have run first. Same guarantees: idempotent,
 * additive, preserves other config keys.
 *
 * ServiceKindsCollapseSeeder moved every menu item type onto the five kinds
 * under `menu_kinds`, which left the branches this map names — fresh_market,
 * bakery_sweets, supermarket — carrying no types at all. Run standalone, the
 * inherited expansion therefore wrote an EMPTY allowed_item_types onto all 19
 * menu children, and empty does not read as «nothing», it reads as
 * «everything». A full seed hid it, because the collapse runs six lines later
 * and re-derives the kind from the branch; running this seeder by itself did
 * not.
 *
 * translateTypes() does that translation here instead, from the same map the
 * collapse uses, so this seeder is correct on its own and says the same thing
 * either way.
 */
class MenuChildBranchesSeeder extends DeliveryChildBranchesSeeder
{
    protected function translateTypes(array $branchKeys, array $typeKeys): array
    {
        $map = (require __DIR__ . '/data/service_kinds.php')['menu']['map'] ?? [];

        $kinds = array_values(array_unique(array_filter(
            array_map(fn ($key) => $map[$key] ?? null, $branchKeys)
        )));

        // No branch of this child is in the collapse map — leave whatever the
        // branches carry rather than blank the child.
        return $kinds ?: $typeKeys;
    }

    /**
     * The kinds all live under one branch now, so the old per-food-family
     * branches must not come back either: writing them would put the merchant's
     * picker back under «طازج» and «مخبوزات وحلويات», groups that hold nothing.
     */
    protected function translateGroups(array $branchKeys, array $groupIds): array
    {
        $spec = (require __DIR__ . '/data/service_kinds.php')['menu'];

        if (! array_intersect($branchKeys, array_keys($spec['map'] ?? []))) {
            return $groupIds;
        }

        $id = (int) DB::table('platform_service_item_groups')
            ->where('platform_service_id', DB::table('platform_services')->where('key', 'menu')->value('id'))
            ->where('key', $spec['branch']['key'])
            ->value('id');

        return $id ? [$id] : $groupIds;
    }

    protected function serviceKey(): string
    {
        return 'menu';
    }

    protected function dataFile(): string
    {
        return __DIR__ . '/data/menu_child_branches.php';
    }

    protected function newConfigDefaults(): array
    {
        return [
            'has_variants' => false,
            'has_addons' => false,
            'supports_notes' => false,
            'supports_stock' => false,
        ];
    }
}
