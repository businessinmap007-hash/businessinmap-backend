<?php

use App\Support\Maintenance\PreCairoTimestamps;
use Illuminate\Database\Migrations\Migration;

/**
 * Rows written while the platform still ran on UTC carry UTC digits in created_at / updated_at / deleted_at; from the
 * switch to Cairo's clock on, new rows carry Cairo digits. This moves the old ones onto Cairo's clock (offset looked up
 * per row from the tz database) so the two read the same — see PreCairoTimestamps. Run once; nothing but those three
 * columns is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        PreCairoTimestamps::apply();
    }

    public function down(): void
    {
        // not reversible: the cutoff cannot tell a moved row from one written in Cairo digits afterwards
    }
};
