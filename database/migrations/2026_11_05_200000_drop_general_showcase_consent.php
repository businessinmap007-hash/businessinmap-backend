<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner, 2026-10-20: the consent to show a project / plan lives INSIDE the publish request (one approval covers all its stages),
 * not in a general setting. Removes what the first attempt added: the general-consent table, the per-month confirmation and the
 * two bookkeeping columns that only the general consent needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('showcase_consents');

        foreach (['body_composition_reports' => ['client_confirmed_at'], 'project_showcases' => ['decided_via', 'withdrawn_by'], 'plan_showcases' => ['decided_via', 'withdrawn_by']] as $table => $columns) {
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropColumn($column));
                }
            }
        }
    }

    public function down(): void
    {
        // the first attempt is not coming back
    }
};
