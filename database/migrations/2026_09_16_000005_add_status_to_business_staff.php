<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A staff grant used to take effect the instant the owner saved it — no
 * confirmation from the person actually being handed access. Adds the
 * missing acceptance step: new rows are created `pending` and only grant
 * real access once the invited person accepts (see
 * BusinessAccessService::resolveContext()'s status filter).
 *
 * Defaulting the column to `accepted` backfills every EXISTING row as
 * already-accepted (they are already working, nothing to confirm) - only
 * rows created from here on start `pending`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_staff', function (Blueprint $table) {
            $table->string('status', 20)->default('accepted')->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('business_staff', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
