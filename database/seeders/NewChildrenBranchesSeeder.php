<?php

namespace Database\Seeders;

/**
 * Wires the children created by the 2026-08-02 remodels to the booking service
 * — they post-date data/booking_child_branches.php and so had no service link.
 * Inherits the whole merge/idempotency contract from the booking layout seeder;
 * only the data file differs.
 */
class NewChildrenBranchesSeeder extends BookingChildBranchesSeeder
{
    protected function dataFile(): string
    {
        return __DIR__ . '/data/booking_new_children_branches.php';
    }
}
