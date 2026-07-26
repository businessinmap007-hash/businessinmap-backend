<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One bar on the timeline: a stage or task inside a project. `parent_id` nests
 * a task under a stage (one level of grouping); ordering between tasks comes
 * from the dependency graph (project_task_dependencies), so a task's earliest
 * start is derived, not stored.
 *
 * `requires_photo` gates completion on camera evidence — the default for a
 * build/manufacturing stage where "done" must be provable with a live photo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('parent_id')->nullable()->index();

            $table->string('title');
            $table->text('notes')->nullable();

            // pending | in_progress | blocked | done
            $table->string('status', 20)->default('pending')->index();

            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();

            $table->unsignedTinyInteger('progress')->default(0);

            // A stage's "done" needs a camera-captured photo before it counts.
            $table->boolean('requires_photo')->default(true);

            $table->integer('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_tasks');
    }
};
