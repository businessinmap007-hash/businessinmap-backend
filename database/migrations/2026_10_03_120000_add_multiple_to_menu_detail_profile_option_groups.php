<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A descriptive group can allow one choice (the style: modern OR classic) or
 * several (the wood: beech AND MDF) — the admin says which, per kind, in
 * «أشكال المنيو». On by default: what already existed allowed several.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_detail_profile_option_groups', function (Blueprint $table) {
            $table->boolean('multiple')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('menu_detail_profile_option_groups', function (Blueprint $table) {
            $table->dropColumn('multiple');
        });
    }
};
