<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The other half of «اتبع تنظيمي اليدوي».
 *
 * The withdrawals table recorded what the owner took away, and the seeders
 * stopped handing it back. It did nothing for the symmetric case: an option he
 * ADDS by hand is still dropped by the replace-style seeders on their next run,
 * because it is in the database and not in their declaration.
 *
 * Both are the same fact — a hand decision about (child, root, option) — so they
 * belong in one table with a `kind`, and the table stops being called
 * «withdrawals» the moment it also holds the opposite.
 *
 *   withdrawn   he took it away; no seeder may grant it
 *   pinned      he put it there; no seeder may drop it
 *
 * A pair can never hold both for the same triple: `record()` and `pin()` each
 * delete the other kind first, because ticking and unticking are a toggle and
 * the last one wins.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category_child_option_withdrawals', function (Blueprint $table) {
            $table->string('kind', 16)->default('withdrawn')->after('option_id');
        });

        // Every row written before this point was a withdrawal, which is what
        // the column already defaults to — nothing to backfill.
        Schema::rename('category_child_option_withdrawals', 'category_child_option_decisions');

        Schema::table('category_child_option_decisions', function (Blueprint $table) {
            $table->dropUnique('cco_withdrawal_unique');
            $table->unique(['child_id', 'category_id', 'option_id', 'kind'], 'cco_decision_unique');
        });
    }

    public function down(): void
    {
        Schema::table('category_child_option_decisions', function (Blueprint $table) {
            $table->dropUnique('cco_decision_unique');
        });

        // A pin has no meaning in the old shape and its triple may collide with
        // a withdrawal's once `kind` is gone, so pins go rather than corrupt the
        // unique index.
        DB::table('category_child_option_decisions')->where('kind', 'pinned')->delete();

        Schema::rename('category_child_option_decisions', 'category_child_option_withdrawals');

        Schema::table('category_child_option_withdrawals', function (Blueprint $table) {
            $table->dropColumn('kind');
            $table->unique(['child_id', 'category_id', 'option_id'], 'cco_withdrawal_unique');
        });
    }
};
