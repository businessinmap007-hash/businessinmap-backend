<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A record of what the owner took away by hand.
 *
 * The option seeders are ADD-ONLY, which was deliberate — an add-only seeder can
 * never destroy curation. But it cannot tell «this was never granted» from «the
 * owner unticked it», so on every run it handed back exactly what he had just
 * removed, and his answer was «انسحب البذرة، اتبع تنظيمي اليدوي».
 *
 * A seeder cannot obey that without being told. This table is the telling: one
 * row per (child, root, option) that must not be granted again. It is the
 * mirror image of `category_child_option` and is scoped the same way —
 *
 *   category_id = 0   withdrawn under EVERY root the child stands under
 *   category_id > 0   withdrawn under that root alone
 *
 * — so a café may refuse freight terms under شركات and keep them under مصانع,
 * the same disagreement per-root option rows exist to express.
 *
 * Nothing here is permanent. Re-ticking an option in the admin clears its
 * withdrawal, because an explicit grant is the owner overruling his own earlier
 * removal; that is what makes a wrong row cost one click instead of a migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_child_option_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('child_id');
            $table->unsignedBigInteger('category_id')->default(0);
            $table->unsignedBigInteger('option_id');

            // Where the row came from, because the two provenances are not
            // equally trustworthy. `admin` is a removal actually observed
            // through the bulk screen. `baseline` is the one-off capture of the
            // divergence that already existed on 2026-08-10, inferred from «the
            // seeder declares it and the database does not hold it» — sound for
            // seeders known to have run, and a guess for anything else.
            $table->string('source', 32)->default('admin');

            $table->timestamps();

            $table->unique(['child_id', 'category_id', 'option_id'], 'cco_withdrawal_unique');
            $table->index('option_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_child_option_withdrawals');
    }
};
