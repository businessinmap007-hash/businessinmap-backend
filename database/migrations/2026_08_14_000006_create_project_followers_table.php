<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A person following a project's progress. The customer REQUESTS to follow; the
 * business approves and grants an access level:
 *   - summary:  the coarse map + completion percentages only (no details);
 *   - detailed: the full stage-by-stage view with camera evidence.
 * Pending/rejected requests see nothing until approved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_followers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('user_id')->index();

            // pending | approved | rejected
            $table->string('status', 12)->default('pending');
            // summary | detailed
            $table->string('access_level', 12)->default('summary');

            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_followers');
    }
};
