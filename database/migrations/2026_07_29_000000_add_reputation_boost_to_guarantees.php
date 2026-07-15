<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reputation-based coverage boost (rating slice 3).
 *
 * On top of a guarantee level's `active_coverage_amount`, a user with an
 * excellent OPERATION rating unlocks a higher `boost_coverage_amount` — the
 * platform underwrites the extra headroom only for provably good behaviour.
 * The boost is recomputed on every operation and drops the moment the rating
 * falls below the level's thresholds, so it is never a permanent grant.
 *
 * Policy at rollout: boost = active_coverage_amount * 1.25 (conservative +25%),
 * gated on >= 5 completed operations, success_rate >= 90%, dispute_rate <= 5%.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guarantee_levels', function (Blueprint $table) {
            // Null / 0 boost coverage = boost disabled for this level.
            $table->decimal('boost_coverage_amount', 12, 2)->nullable()->after('active_coverage_amount');
            $table->unsignedInteger('boost_min_operations')->nullable()->after('boost_coverage_amount');
            $table->decimal('boost_min_success_rate', 5, 2)->nullable()->after('boost_min_operations');
            $table->decimal('boost_max_dispute_rate', 5, 2)->nullable()->after('boost_min_success_rate');
        });

        Schema::table('user_guarantees', function (Blueprint $table) {
            $table->boolean('is_boosted')->default(false)->after('current_coverage_amount');
        });

        // Backfill a sensible default boost on existing levels (+25%), so the
        // feature is live without hand-editing every level. Admins can retune.
        DB::table('guarantee_levels')
            ->where('active_coverage_amount', '>', 0)
            ->update([
                'boost_coverage_amount' => DB::raw('ROUND(active_coverage_amount * 1.25, 2)'),
                'boost_min_operations' => 5,
                'boost_min_success_rate' => 90.00,
                'boost_max_dispute_rate' => 5.00,
            ]);
    }

    public function down(): void
    {
        Schema::table('guarantee_levels', function (Blueprint $table) {
            $table->dropColumn([
                'boost_coverage_amount',
                'boost_min_operations',
                'boost_min_success_rate',
                'boost_max_dispute_rate',
            ]);
        });

        Schema::table('user_guarantees', function (Blueprint $table) {
            $table->dropColumn('is_boosted');
        });
    }
};
