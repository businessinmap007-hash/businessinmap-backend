<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subjective star reviews (slice 2), shown beneath the objective percentages.
 * A review is ALWAYS tied to a real, completed operation between the two
 * parties — you can only rate a counterparty you actually dealt with, which
 * blocks reputation attacks from strangers with no prior dealing.
 *
 * Also adds the running star aggregate to user_operation_ratings so the summary
 * stays a single-row read (average derived from sum/count).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('operation_type', 30);  // booking | order
            $table->unsignedBigInteger('operation_id');
            $table->unsignedBigInteger('rater_id'); // who gives the stars
            $table->unsignedBigInteger('ratee_id'); // who receives them
            $table->string('ratee_role', 20);       // client | business
            $table->unsignedTinyInteger('stars');   // 1..5
            $table->text('comment')->nullable();
            $table->timestamps();

            // One review per rater per operation (updatable).
            $table->unique(['operation_type', 'operation_id', 'rater_id'], 'operation_review_unique');
            $table->index(['ratee_id', 'ratee_role']);
        });

        Schema::table('user_operation_ratings', function (Blueprint $table) {
            $table->unsignedInteger('review_count')->default(0)->after('disputed_count');
            $table->unsignedBigInteger('review_stars_sum')->default(0)->after('review_count');
        });
    }

    public function down(): void
    {
        Schema::table('user_operation_ratings', function (Blueprint $table) {
            $table->dropColumn(['review_count', 'review_stars_sum']);
        });

        Schema::dropIfExists('operation_reviews');
    }
};
