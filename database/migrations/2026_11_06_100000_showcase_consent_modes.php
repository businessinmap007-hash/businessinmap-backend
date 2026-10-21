<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner, 2026-10-21: the consent to show a project / plan is asked in one of two ways, chosen in the request itself —
 * `once` (one general approval covers every stage) or `each` (the customer approves each main stage of a project, and each month
 * of a plan at its renewal). `showcase_approvals` holds the per-stage / per-month answers of the `each` mode.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['project_showcases', 'plan_showcases'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'consent_mode')) {
                    $t->string('consent_mode', 8)->default('once'); // once | each
                }
            });
        }

        if (! Schema::hasTable('showcase_approvals')) {
            Schema::create('showcase_approvals', function (Blueprint $table) {
                $table->id();
                $table->string('kind', 16);             // project_stage | plan_month
                $table->unsignedBigInteger('showcase_id');
                $table->string('unit_key', 16);         // the main stage's id, or the month «2026-10»
                $table->string('status', 10)->default('pending'); // pending | approved | declined
                $table->timestamp('decided_at')->nullable();
                $table->timestamps();
                $table->unique(['kind', 'showcase_id', 'unit_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('showcase_approvals');
        foreach (['project_showcases', 'plan_showcases'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('consent_mode'));
        }
    }
};
