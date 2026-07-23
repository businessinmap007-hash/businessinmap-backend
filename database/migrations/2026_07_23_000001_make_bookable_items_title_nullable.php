<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * bookable_items.title was NOT NULL with no default, but both the web
 * (Business\BookableItemController) and the new API
 * (Api\V2\BusinessBookableItemController) store `trim(title) ?: null` — an
 * empty title becomes null and the insert/update fails. A bookable unit
 * legitimately has just a code (e.g. "TABLE-5") with no title, so the column
 * should be nullable, matching the code's long-standing intent. Raw ALTER so it
 * needs no doctrine/dbal.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `bookable_items` MODIFY `title` VARCHAR(191) NULL');
    }

    public function down(): void
    {
        // Backfill any nulls before restoring NOT NULL so the revert can't fail.
        DB::table('bookable_items')->whereNull('title')->update(['title' => '']);
        DB::statement('ALTER TABLE `bookable_items` MODIFY `title` VARCHAR(191) NOT NULL');
    }
};
