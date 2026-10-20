<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «نتائج موثَّقة» for training and nutrition plans, and a GENERAL consent (owner, 2026-10-20):
 *  - `plan_showcases`: a trainer shows a plan's results as a post — anonymous, only the monthly results the client confirmed;
 *  - `body_composition_reports.client_confirmed_at`: the client's confirmation of each month's result (the "stage succeeded");
 *  - `showcase_consents`: one row per person — «I allow my projects / training plans / nutrition plans to be shown» — so he is not
 *    asked every time; a request the consent covers is approved at once (recorded `decided_via = general`);
 *  - `withdrawn_by` on both showcase tables: what the customer took down, a general consent never puts back.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plan_showcases')) {
            Schema::create('plan_showcases', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('training_plan_id')->unique();
                $table->unsignedBigInteger('trainer_id')->index();
                $table->unsignedBigInteger('post_id')->nullable()->index();
                $table->string('public_title', 191);
                $table->string('note', 500)->nullable();
                $table->string('status', 16)->default('requested'); // requested | approved | declined | withdrawn
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->string('decision_note', 500)->nullable();
                $table->unsignedBigInteger('decided_by')->nullable();
                $table->string('decided_via', 12)->nullable();   // request | general
                $table->string('withdrawn_by', 12)->nullable();  // customer | business
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('showcase_consents')) {
            Schema::create('showcase_consents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->boolean('projects')->default(false);
                $table->boolean('project_photos')->default(false);
                $table->boolean('training')->default(false);
                $table->boolean('nutrition')->default(false);
                $table->timestamps();
            });
        }

        Schema::table('project_showcases', function (Blueprint $table) {
            if (! Schema::hasColumn('project_showcases', 'decided_via')) {
                $table->string('decided_via', 12)->nullable();
            }
            if (! Schema::hasColumn('project_showcases', 'withdrawn_by')) {
                $table->string('withdrawn_by', 12)->nullable();
            }
        });

        Schema::table('body_composition_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('body_composition_reports', 'client_confirmed_at')) {
                $table->timestamp('client_confirmed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('body_composition_reports', fn (Blueprint $t) => $t->dropColumn('client_confirmed_at'));
        Schema::table('project_showcases', fn (Blueprint $t) => $t->dropColumn(['decided_via', 'withdrawn_by']));
        Schema::dropIfExists('showcase_consents');
        Schema::dropIfExists('plan_showcases');
    }
};
