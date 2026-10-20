<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Projects, third round — the big job (owner, 2026-10-20: a five-floor building with a basement, three flats a floor; an
 * engineering office supervising it; delivery date + a grace month agreed with the customer):
 *
 *  - a job has a PLACE («الدور الثالث / شقة ٢»), a WEIGHT in the project's progress, and free DETAILS; a stage that has
 *    children is a group whose progress comes from them;
 *  - a stage carries measurable ITEMS (columns: 24, concrete: 120 m³, bricks…) — planned vs done — and every change to one is
 *    logged, so the customer reads the history;
 *  - an AGREEMENT: the plan as both sides accepted it (immutable versions: stages, quantities, delivery date, grace), which a
 *    dispute can rely on; an extension or a scope change is a CHANGE REQUEST the customer approves, and each approval
 *    writes a new version;
 *  - starting a top-level stage needs the customer's recorded approval (that is also when its fee is taken); the time it
 *    waited for him is logged;
 *  - the project remembers when it was finished, for the rating.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('project_tasks', 'zone')) {
                $table->string('zone', 120)->nullable();
            }
            if (! Schema::hasColumn('project_tasks', 'weight')) {
                $table->unsignedInteger('weight')->default(0);              // 0 = its duration in days
            }
            if (! Schema::hasColumn('project_tasks', 'details')) {
                $table->text('details')->nullable();
            }
            if (! Schema::hasColumn('project_tasks', 'start_status')) {
                $table->string('start_status', 10)->nullable();             // requested | approved | declined
                $table->timestamp('start_requested_at')->nullable();
                $table->timestamp('start_decided_at')->nullable();
                $table->string('start_note', 500)->nullable();
            }
        });

        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'grace_until')) {
                $table->date('grace_until')->nullable();                    // the end of the agreed grace
            }
            if (! Schema::hasColumn('projects', 'completed_at')) {
                $table->timestamp('completed_at')->nullable();
            }
            if (! Schema::hasColumn('projects', 'customer_wait_seconds')) {
                $table->unsignedBigInteger('customer_wait_seconds')->default(0);   // time stages waited for his start approval
            }
        });

        if (! Schema::hasTable('project_task_items')) {
            Schema::create('project_task_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('task_id')->index();
                $table->string('name', 120);
                $table->string('unit', 30)->nullable();
                $table->decimal('planned', 14, 2)->default(0);
                $table->decimal('done', 14, 2)->default(0);
                $table->string('notes', 255)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('project_item_logs')) {
            Schema::create('project_item_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_id')->index();
                $table->unsignedBigInteger('task_id')->index();
                $table->unsignedBigInteger('item_id')->nullable();
                $table->unsignedBigInteger('user_id');
                $table->string('field', 12);                                // created | name | unit | planned | done | deleted
                $table->string('old_value', 120)->nullable();
                $table->string('new_value', 120)->nullable();
                $table->string('reason', 255)->nullable();
                $table->unsignedBigInteger('change_request_id')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('project_agreements')) {
            Schema::create('project_agreements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_id')->index();
                $table->unsignedSmallInteger('version');
                $table->string('status', 12)->default('proposed');          // proposed | agreed | superseded | rejected
                $table->date('due_on');
                $table->date('grace_until');
                $table->json('snapshot');                                   // the plan as proposed: stages, places, items
                $table->string('content_hash', 64);
                $table->unsignedBigInteger('proposed_by');
                $table->timestamp('business_agreed_at')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->timestamp('customer_agreed_at')->nullable();
                $table->string('customer_note', 500)->nullable();
                $table->unsignedBigInteger('change_request_id')->nullable();   // the request that produced this version, if any
                $table->timestamps();

                $table->unique(['project_id', 'version']);
            });
        }

        if (! Schema::hasTable('project_change_requests')) {
            Schema::create('project_change_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_id')->index();
                $table->string('type', 12);                                 // extension | scope
                $table->json('payload');
                $table->string('note', 500)->nullable();
                $table->string('status', 10)->default('pending');           // pending | approved | rejected
                $table->unsignedBigInteger('requested_by');
                $table->unsignedBigInteger('decided_by')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->string('decision_note', 500)->nullable();
                $table->timestamps();
            });
        }

        // the universal rating learns «late» (a finished job past its agreed date + grace)
        Schema::table('user_operation_ratings', function (Blueprint $table) {
            if (! Schema::hasColumn('user_operation_ratings', 'late_count')) {
                $table->unsignedInteger('late_count')->default(0);
            }
        });

        // what the platform takes for a project: when the plan is agreed (once) and when each top-level stage starts, from both
        // sides — off until the owner prices it in the control panel
        $now = now();
        foreach (['project_plan_agreed', 'project_stage_started'] as $event) {
            foreach (['business', 'client'] as $role) {
                DB::table('platform_event_fees')->updateOrInsert(
                    ['event_key' => $event, 'role' => $role],
                    ['enabled' => false, 'calc_type' => 'fixed', 'amount' => 0, 'currency' => 'EGP', 'created_at' => $now, 'updated_at' => $now],
                );
            }
        }
    }

    public function down(): void
    {
        Schema::table('user_operation_ratings', fn (Blueprint $t) => $t->dropColumn('late_count'));
        Schema::dropIfExists('project_change_requests');
        Schema::dropIfExists('project_agreements');
        Schema::dropIfExists('project_item_logs');
        Schema::dropIfExists('project_task_items');
        Schema::table('projects', fn (Blueprint $t) => $t->dropColumn(['grace_until', 'completed_at', 'customer_wait_seconds']));
        Schema::table('project_tasks', fn (Blueprint $t) => $t->dropColumn(['zone', 'weight', 'details', 'start_status', 'start_requested_at', 'start_decided_at', 'start_note']));
    }
};
