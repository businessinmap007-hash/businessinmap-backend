<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Taxonomy Lab — an isolated rebuild sandbox.
 *
 * The live services taxonomy (platform_services + item_types + item_groups) and
 * the legacy option classification (options + option_groups + category_child_option)
 * grew tangled: overlapping branches, ~8.9k child↔option links, screens too dense
 * to reason about. Rather than surgery on the live tables, we clone the *atoms*
 * into parallel `_new` tables and rebuild the *groupings* from a clean slate,
 * step by step, in a dedicated admin section. The live tables stay untouched and
 * serve as the reference until we deliberately swap.
 *
 * Structure only here — the copy (atoms + child links) runs via the re-runnable
 * `taxonomy-lab:seed` command so the sandbox can be reset at any time. The three
 * grouping tables are created empty on purpose: they are what we rebuild.
 *
 * We use `CREATE TABLE ... LIKE` so each clone mirrors its source column-for-column
 * and index-for-index; LIKE deliberately does NOT copy foreign keys, so we then
 * re-point the FKs at the `_new` parents (FK constraint names must be globally
 * unique, which LIKE-copied ones would not be).
 */
return new class extends Migration
{
    /** clone table => source table, in FK-safe creation order (parents first). */
    private array $clones = [
        'platform_services_new'             => 'platform_services',
        'platform_service_item_types_new'   => 'platform_service_item_types',
        'platform_service_item_groups_new'  => 'platform_service_item_groups',
        'platform_service_item_group_type_new' => 'platform_service_item_group_type',
        'option_groups_new'                 => 'option_groups',
        'options_new'                       => 'options',
        'category_child_option_new'         => 'category_child_option',
    ];

    public function up(): void
    {
        foreach ($this->clones as $clone => $source) {
            if (! Schema::hasTable($clone) && Schema::hasTable($source)) {
                DB::statement("CREATE TABLE `{$clone}` LIKE `{$source}`");
            }
        }

        // Re-point foreign keys at the `_new` parents. Wrapped individually so a
        // partial re-run (table already present) doesn't abort the whole batch.
        $this->addForeignKeys();
    }

    private function addForeignKeys(): void
    {
        $fks = [
            // clone table, local column, referenced table, on-delete
            ['platform_service_item_types_new', 'platform_service_id', 'platform_services_new', 'CASCADE'],
            ['platform_service_item_groups_new', 'platform_service_id', 'platform_services_new', 'CASCADE'],
            ['platform_service_item_group_type_new', 'group_id', 'platform_service_item_groups_new', 'CASCADE'],
            ['platform_service_item_group_type_new', 'item_type_id', 'platform_service_item_types_new', 'CASCADE'],
            ['options_new', 'group_id', 'option_groups_new', 'SET NULL'],
            ['category_child_option_new', 'child_id', 'category_children_master', 'CASCADE'],
            ['category_child_option_new', 'option_id', 'options_new', 'CASCADE'],
        ];

        foreach ($fks as [$table, $column, $refTable, $onDelete]) {
            $name = "{$table}_{$column}_fk";
            if (! Schema::hasTable($table) || ! Schema::hasTable($refTable)) {
                continue;
            }
            if ($this->foreignKeyExists($table, $name)) {
                continue;
            }
            DB::statement(
                "ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` "
                . "FOREIGN KEY (`{$column}`) REFERENCES `{$refTable}` (`id`) ON DELETE {$onDelete}"
            );
        }
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (array_keys($this->clones) as $clone) {
            Schema::dropIfExists($clone);
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
