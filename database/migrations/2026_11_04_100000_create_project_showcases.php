<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «نتائج موثَّقة» (owner, 2026-10-20): a business shows a project's progress as a marketing post, but only after the customer
 * agrees, and only with what he confirmed — no names, no address, no unit numbers. One row per project; the post is made when
 * the customer approves and switched off when either side withdraws.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_showcases')) {
            return;
        }

        Schema::create('project_showcases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->unique();
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('post_id')->nullable()->index();
            $table->string('public_title', 191);
            $table->string('note', 500)->nullable();
            $table->boolean('show_photos')->default(false);
            $table->string('status', 16)->default('requested'); // requested | approved | declined | withdrawn
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_showcases');
    }
};
