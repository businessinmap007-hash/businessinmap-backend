<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Purges the legacy root-level rows in category_platform_services — those with
 * child_id NULL or 0. They were created by the retired CategoryPlatformServiceSeeder
 * (removed 2026-07-13) for hotel/restaurant/sports before the model became strictly
 * child-level. Every live read path keys on child_id > 0 (owner panel, offers
 * enablement rule, services-bulk), so these rows are dead weight — on the dev DB
 * the only survivors were 4 rows for مطعم (category_id 235: booking/menu/delivery
 * + an inactive business_offers).
 *
 * Idempotent: a no-op where none remain. Leaves child-level rows untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('category_platform_services')) {
            return;
        }

        DB::table('category_platform_services')
            ->whereNull('child_id')
            ->orWhere('child_id', 0)
            ->delete();
    }

    public function down(): void
    {
        // Intentionally irreversible: these were stale legacy artifacts with no
        // child context; recreating them would reintroduce the bug this fixes.
    }
};
