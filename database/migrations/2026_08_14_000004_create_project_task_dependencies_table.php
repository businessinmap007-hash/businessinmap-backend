<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finish-to-start ordering between tasks: `task_id` cannot start until
 * `depends_on_id` finishes. This is the graph the timeline walks to compute
 * each task's earliest start/finish, its slack, and the critical path.
 *
 * The service refuses a cycle before writing, so the graph stays a DAG.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id')->index();
            $table->unsignedBigInteger('depends_on_id')->index();
            $table->timestamps();

            $table->unique(['task_id', 'depends_on_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_task_dependencies');
    }
};
