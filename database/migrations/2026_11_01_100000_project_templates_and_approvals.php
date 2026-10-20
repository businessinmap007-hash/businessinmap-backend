<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Project tracking, second round (owner, 2026-10-20):
 *
 *  - stage TEMPLATES per trade, so a business starts from a ready plan instead of an empty page: `project_templates` holds the
 *    plan, `project_template_stages` its stages in order, and `project_template_children` says which trades (category children)
 *    are offered it — a template with no trade rows is general and offered to every business that has the service;
 *  - the customer's APPROVAL of a stage: a stage can ask for it (`requires_approval`); when it is completed it waits
 *    (`approval_status` pending) until the customer approves it or sends it back with a note.
 *
 * Who is responsible for a stage is NOT a column: it goes through the assignments table (see AssignmentService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('project_tasks', 'requires_approval')) {
                $table->boolean('requires_approval')->default(false);
            }
            if (! Schema::hasColumn('project_tasks', 'approval_status')) {
                $table->string('approval_status', 10)->nullable();   // pending | approved | rejected
            }
            if (! Schema::hasColumn('project_tasks', 'approval_note')) {
                $table->string('approval_note', 500)->nullable();
            }
            if (! Schema::hasColumn('project_tasks', 'approval_at')) {
                $table->timestamp('approval_at')->nullable();
            }
        });

        if (! Schema::hasTable('project_templates')) {
            Schema::create('project_templates', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('title_ar', 120);
                $table->string('title_en', 120);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('project_template_stages')) {
            Schema::create('project_template_stages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('template_id');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->string('title_ar', 200);
                $table->string('title_en', 200);
                $table->unsignedSmallInteger('duration_days')->default(1);
                $table->boolean('requires_photo')->default(true);
                $table->boolean('requires_approval')->default(false);
                $table->timestamps();

                $table->index(['template_id', 'sort_order'], 'pts_template_order');
            });
        }

        if (! Schema::hasTable('project_template_children')) {
            Schema::create('project_template_children', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('template_id');
                $table->unsignedBigInteger('child_id');

                $table->unique(['template_id', 'child_id'], 'ptc_one');
                $table->index('child_id', 'ptc_child');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_template_children');
        Schema::dropIfExists('project_template_stages');
        Schema::dropIfExists('project_templates');
        Schema::table('project_tasks', fn (Blueprint $t) => $t->dropColumn(['requires_approval', 'approval_status', 'approval_note', 'approval_at']));
    }
};
