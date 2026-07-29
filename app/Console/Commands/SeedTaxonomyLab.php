<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fills (or resets) the Taxonomy Lab sandbox from the live tables.
 *
 * Copies the ATOMS faithfully — the services, their item types, the options
 * themselves, and the ~8.9k child↔option links — so nothing is lost. The
 * GROUPINGS are deliberately left empty: item-groups (branches), the type↔group
 * membership pivot, and option-groups are what we rebuild by hand, step by step,
 * in the lab UI. Options are copied ungrouped (group_id NULL) for the same reason.
 *
 * Idempotent: re-running truncates the `_new` tables and re-copies, so the lab
 * can be reset to a pristine mirror at any time without touching the live data.
 */
class SeedTaxonomyLab extends Command
{
    protected $signature = 'taxonomy-lab:seed {--keep-groups : Do not clear the grouping tables (keep hand-built groups)}';

    protected $description = 'Copy the live services/options atoms into the Taxonomy Lab (_new) sandbox; groupings stay empty to be rebuilt.';

    /** every _new table the migration creates */
    private const REQUIRED = [
        'platform_services_new',
        'platform_service_item_types_new',
        'platform_service_item_groups_new',
        'platform_service_item_group_type_new',
        'option_groups_new',
        'options_new',
        'category_child_option_new',
    ];

    public function handle(): int
    {
        foreach (self::REQUIRED as $t) {
            if (! Schema::hasTable($t)) {
                $this->error("Missing sandbox table `{$t}`. Run migrations first.");
                return self::FAILURE;
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            // Grouping tables: children before parents when clearing.
            if (! $this->option('keep-groups')) {
                DB::table('platform_service_item_group_type_new')->truncate();
                DB::table('platform_service_item_groups_new')->truncate();
                DB::table('option_groups_new')->truncate();
            }

            // Atoms + links: clear then re-copy (links before options, options before none).
            DB::table('category_child_option_new')->truncate();
            DB::table('platform_service_item_types_new')->truncate();
            DB::table('options_new')->truncate();
            DB::table('platform_services_new')->truncate();

            // Faithful column-for-column copies (LIKE guarantees identical layout).
            DB::statement('INSERT INTO platform_services_new SELECT * FROM platform_services');
            DB::statement('INSERT INTO platform_service_item_types_new SELECT * FROM platform_service_item_types');
            DB::statement('INSERT INTO options_new SELECT * FROM options');

            // Rebuild grouping from scratch: options start ungrouped.
            DB::table('options_new')->update(['group_id' => null]);

            // Child↔option links are business associations, not groupings — preserve them.
            DB::statement('INSERT INTO category_child_option_new SELECT * FROM category_child_option');
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->info('Taxonomy Lab seeded.');
        $this->table(
            ['table', 'rows'],
            collect(self::REQUIRED)->map(fn ($t) => [$t, number_format(DB::table($t)->count())])->all()
        );

        return self::SUCCESS;
    }
}
