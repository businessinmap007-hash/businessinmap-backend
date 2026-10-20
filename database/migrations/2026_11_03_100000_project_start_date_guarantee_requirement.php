<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projects, fourth round (owner, 2026-10-20):
 *  - the agreed plan has a START date in the future («today is 20-10-2026, execution starts 20-11-2026» — the month in between is
 *    the negotiation), kept in every agreement version;
 *  - the business may require the customer to hold a guarantee of at least a given level (e.g. silver) before work on the plan
 *    begins: `projects.required_guarantee_level` is the level's code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'required_guarantee_level')) {
                $table->string('required_guarantee_level', 20)->nullable();
            }
        });

        Schema::table('project_agreements', function (Blueprint $table) {
            if (! Schema::hasColumn('project_agreements', 'starts_on')) {
                $table->date('starts_on')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_agreements', fn (Blueprint $t) => $t->dropColumn('starts_on'));
        Schema::table('projects', fn (Blueprint $t) => $t->dropColumn('required_guarantee_level'));
    }
};
