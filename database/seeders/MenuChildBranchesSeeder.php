<?php

namespace Database\Seeders;

/**
 * Menu counterpart of DeliveryChildBranchesSeeder: re-applies the approved
 * child→branch layout for the Menu service from data/menu_child_branches.php.
 * Requires MenuBranchesSeeder to have run first. Same guarantees: idempotent,
 * additive, preserves other config keys.
 */
class MenuChildBranchesSeeder extends DeliveryChildBranchesSeeder
{
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
