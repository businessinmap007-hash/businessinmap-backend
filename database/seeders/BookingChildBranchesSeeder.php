<?php

namespace Database\Seeders;

/**
 * Booking counterpart of DeliveryChildBranchesSeeder: re-applies the approved
 * child→branch layout for the Booking service from data/booking_child_branches.php.
 * Requires BookingBranchesSeeder to have run first (branches must exist).
 * Same guarantees: idempotent, additive, preserves other config keys
 * (booking behaviour flags, catalog_source, …).
 */
class BookingChildBranchesSeeder extends DeliveryChildBranchesSeeder
{
    protected function serviceKey(): string
    {
        return 'booking';
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
