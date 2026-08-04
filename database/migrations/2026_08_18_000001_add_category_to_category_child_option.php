<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A child's options become answerable per ROOT.
 *
 * `category_child_option` was keyed by child alone while a child row is shared
 * across roots, so «آثاث» carried ONE option set for مصانع، معارض، ورش، شركات
 * at once — the union, whether or not it made sense under each. A furniture
 * FACTORY answers «خامات» and «طاقة إنتاج»; a furniture SHOWROOM answers
 * «تقسيط» and «توصيل ورفع». There was no way to say so.
 *
 * `category_id` = 0 means "under every root this child sits beneath" — which is
 * exactly what every existing row means today, so the backfill is a no-op and
 * every seeder that inserts without the column keeps its current meaning. A row
 * with a real category_id belongs to that root alone.
 *
 * The admin screen only writes a per-root row when a root actually DIVERGES:
 * dropping a shared option under one root deletes the shared row and re-grants
 * it explicitly to the others. Nothing is materialised until it has to be.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('category_child_option', 'category_id')) {
            Schema::table('category_child_option', function (Blueprint $table) {
                $table->unsignedBigInteger('category_id')->default(0)->after('child_id');
            });
        }

        // Two identical uniques on (child_id, option_id) were left by earlier
        // migrations. Both have to go: the pair is no longer unique — the same
        // option may be granted to a child under several roots as its own row.
        foreach (['category_child_option_unique', 'cco_child_option_unique'] as $name) {
            if ($this->hasIndex($name)) {
                DB::statement("ALTER TABLE `category_child_option` DROP INDEX `{$name}`");
            }
        }

        if (! $this->hasIndex('cco_child_category_option_unique')) {
            DB::statement(
                'ALTER TABLE `category_child_option`
                 ADD UNIQUE `cco_child_category_option_unique` (`child_id`, `category_id`, `option_id`)'
            );
        }

        if (! $this->hasIndex('cco_child_category_index')) {
            DB::statement(
                'ALTER TABLE `category_child_option`
                 ADD INDEX `cco_child_category_index` (`child_id`, `category_id`)'
            );
        }
    }

    public function down(): void
    {
        // Collapse back to one row per (child, option) before the old unique can
        // be restored, keeping the lowest id of each pair.
        DB::statement(
            'DELETE a FROM category_child_option a
             JOIN category_child_option b
               ON a.child_id = b.child_id AND a.option_id = b.option_id AND a.id > b.id'
        );

        foreach (['cco_child_category_option_unique', 'cco_child_category_index'] as $name) {
            if ($this->hasIndex($name)) {
                DB::statement("ALTER TABLE `category_child_option` DROP INDEX `{$name}`");
            }
        }

        if (! $this->hasIndex('cco_child_option_unique')) {
            DB::statement(
                'ALTER TABLE `category_child_option`
                 ADD UNIQUE `cco_child_option_unique` (`child_id`, `option_id`)'
            );
        }

        if (Schema::hasColumn('category_child_option', 'category_id')) {
            Schema::table('category_child_option', function (Blueprint $table) {
                $table->dropColumn('category_id');
            });
        }
    }

    private function hasIndex(string $name): bool
    {
        return ! empty(DB::select("SHOW INDEX FROM `category_child_option` WHERE Key_name = ?", [$name]));
    }
};
