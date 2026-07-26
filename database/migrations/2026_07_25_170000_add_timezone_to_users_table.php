<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A business's own timezone, so its opening hours are evaluated where the shop
 * actually is. Null = the platform timezone (the case for a single-country
 * deployment). Used by the displayed "is_open_now"; the bulk search filter
 * still evaluates in the platform timezone (see BusinessHoursService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone', 64)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
