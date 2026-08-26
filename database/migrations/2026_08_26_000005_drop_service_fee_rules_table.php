<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * BIM-3.5 dynamic fee rules, cancelled outright — المالك، 2026-08-26: «الغى
 * القواعد الديناميكة تماما». No dormant table; the engine and every
 * supporting class are deleted in the same change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('service_fee_rules');
    }

    public function down(): void
    {
        // Deliberately not recreated — the owner asked for a full, permanent
        // removal, not a disable.
    }
};
