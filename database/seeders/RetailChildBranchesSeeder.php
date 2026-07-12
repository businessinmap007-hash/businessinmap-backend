<?php

namespace Database\Seeders;

/**
 * Retail counterpart of DeliveryChildBranchesSeeder: re-applies the approved
 * child→branch layout for the Retail service from data/retail_child_branches.php.
 * Requires RetailBranchesSeeder to have run first. Same guarantees: idempotent,
 * additive, preserves other config keys.
 */
class RetailChildBranchesSeeder extends DeliveryChildBranchesSeeder
{
    protected function serviceKey(): string
    {
        return 'retail';
    }

    protected function dataFile(): string
    {
        return __DIR__ . '/data/retail_child_branches.php';
    }

    protected function newConfigDefaults(): array
    {
        return [
            'supports_stock' => true,
        ];
    }
}
