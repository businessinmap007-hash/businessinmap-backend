<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A business writes its name twice; a customer writes it once.
 *
 * 605 of 1,704 businesses (35%) carry a Latin-only name — «Black Box For
 * Playstation and Computer Service», «panda» — and search matched `users.name`
 * alone, so every one of them was invisible to a customer typing Arabic. The
 * reverse held too. A merchant cannot be asked to guess which script his
 * customers use; he is asked for both.
 *
 * `name` is kept as the canonical, never-null display name that the whole
 * codebase already reads, and `name_en` is added beside it. Splitting `name`
 * into a nullable pair instead would have meant auditing every one of the
 * hundreds of places that print it, for no gain.
 *
 * A CLIENT keeps one box that takes either script. A person's name is not a
 * translation of itself, and asking a customer to type it twice buys nothing —
 * nobody searches for customers.
 *
 * The backfill only moves what is already unambiguous: a Latin-only business
 * name IS the English name, so it is copied across. `name` is left untouched —
 * a display name may never become null — and the owner replaces it with the
 * Arabic when he next edits his profile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name_en', 191)->nullable()->after('name');
            $table->index('name_en');
        });

        // REGEXP, not a PHP loop: 1,704 rows is nothing, but the same migration
        // has to run against a production table one day.
        DB::table('users')
            ->where('type', 'business')
            ->whereNull('name_en')
            ->whereRaw("name REGEXP '[A-Za-z]'")
            ->whereRaw("name NOT REGEXP '[ء-ي]'")
            ->update(['name_en' => DB::raw('name')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['name_en']);
            $table->dropColumn('name_en');
        });
    }
};
