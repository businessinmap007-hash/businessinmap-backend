<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One fee per (root, child), not one per (root, child, service) — المالك،
 * 2026-08-26: «بدل ما يكون هناك رسوم بوكينج -منيو -دليفري … يكون هناك رسم
 * services بشكل موحد … اختيار البزنس خدمة او اكتر لا يعنى انه سيدفع 5 لبوكينج
 * 4 منيو لكن ستكون 5 للخدمتين او الثلاث الذى اختارهم».
 *
 * `category_child_service_fees` has no create migration in this repo (live
 * schema drift — confirmed via a full repo search before writing this), so
 * this alters the LIVE table directly rather than modifying a prior migration
 * that does not exist.
 *
 * Collapse strategy for the 97 (root,child) pairs that carried more than one
 * distinct per-service fee: keep the row whose (business_fee, client_fee)
 * pair is the MOST COMMON among that pair's own active rows — this matches
 * 73 of 97 exactly (they were already uniform) and gives the other 24 their
 * own majority value rather than an arbitrary pick. Everything else (555 of
 * 652 rows) already had exactly one active row per (root,child) and keeps it
 * unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('category_child_service_fees')) {
            return;
        }

        if (Schema::hasColumn('category_child_service_fees', 'fee_group_id')) {
            // Already migrated (re-run safety).
            return;
        }

        $this->collapseToOneRowPerRootChild();

        Schema::table('category_child_service_fees', function (Blueprint $table) {
            $table->dropForeign('ccsf_service_fk');
            $table->dropUnique('ccsf_root_child_service_unique');
            $table->dropIndex('ccsf_service_idx');
            $table->dropIndex('ccsf_child_service_index');
            $table->dropColumn('platform_service_id');

            $table->foreignId('fee_group_id')->nullable()->after('child_id')
                ->constrained('fee_groups')->nullOnDelete();

            $table->unique(['category_id', 'child_id'], 'ccsf_root_child_unique');
        });
    }

    /**
     * Pick, per (category_id, child_id), the most common active row's fee
     * values, then delete every row for that pair except the id we keep.
     */
    private function collapseToOneRowPerRootChild(): void
    {
        $pairs = DB::table('category_child_service_fees')
            ->select('category_id', 'child_id')
            ->distinct()
            ->get();

        foreach ($pairs as $pair) {
            $rows = DB::table('category_child_service_fees')
                ->where('category_id', $pair->category_id)
                ->where('child_id', $pair->child_id)
                ->orderByRaw('COALESCE(sort_order, 999999) ASC')
                ->orderBy('id')
                ->get();

            if ($rows->count() <= 1) {
                continue;
            }

            $active = $rows->where('is_active', 1);
            $pool = $active->isNotEmpty() ? $active : $rows;

            $bySignature = $pool->groupBy(fn ($r) => implode('|', [
                $r->business_fee_enabled, $r->business_fee_type, $r->business_fee_amount,
                $r->client_fee_enabled, $r->client_fee_type, $r->client_fee_amount,
            ]));

            $winningGroup = $bySignature->sortByDesc(fn ($g) => $g->count())->first();
            $keep = $winningGroup->first();

            DB::table('category_child_service_fees')
                ->where('category_id', $pair->category_id)
                ->where('child_id', $pair->child_id)
                ->where('id', '!=', $keep->id)
                ->delete();
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('category_child_service_fees', 'fee_group_id')) {
            return;
        }

        Schema::table('category_child_service_fees', function (Blueprint $table) {
            $table->dropUnique('ccsf_root_child_unique');
            $table->dropConstrainedForeignId('fee_group_id');

            $table->unsignedBigInteger('platform_service_id')->default(0)->after('child_id');
        });

        // The per-service rows this migration deleted cannot be restored —
        // rolling back only gets the column shape back, not the old data.
    }
};
