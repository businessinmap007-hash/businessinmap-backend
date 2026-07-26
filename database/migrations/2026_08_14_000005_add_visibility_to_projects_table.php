<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How widely a project may be followed. `private` (default): only the business,
 * the contracted customer, and approved followers. `public`: any signed-in user
 * may follow it and see the coarse map + completion percentages (never the
 * detailed evidence — that stays gated on the business's approval).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('visibility', 12)->default('private')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('visibility');
        });
    }
};
