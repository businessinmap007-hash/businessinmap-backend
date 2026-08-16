<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Move a taxonomy child from one root to another, carrying everything that
 * names the old root with it.
 *
 * A child's membership is ONE row in `category_parent_child`, and moving that
 * row by hand is what the admin screen does. Six other tables key on
 * (root, child) and none of them follow:
 *
 *   users.category_id                       the merchants filed under the root
 *   category_child_option.category_id       the per-root option rows
 *   category_child_option_decisions         the withdrawal / pin ledger
 *   category_platform_services.category_id  which services the child sells
 *   category_service_configs.category_id    how each service is configured
 *   category_child_service_fees.category_id the fee overrides
 *
 * Left behind, every one of them points at a root the child no longer stands
 * under, which makes it unreachable — `CategoryChildOptionScope::idsFor()` is
 * only ever called with a root the child IS under, and the service readers join
 * the same way. The child arrives at its new root mute, unwired and unsellable
 * while the old rows sit there looking intact. Nine such service rows were
 * found on 2026-08-16 across three children, left by exactly this.
 *
 * A SHARED option row (`category_id = 0`) is every root's and is not touched.
 *
 * What it cannot do is strip the child's name out of the seeder data files.
 * Those name a child under a ROOT — `booking_child_branches.php` has a block
 * per root, `child_option_groups.php` keys overrides "root-slug:child-id" — so
 * a line left behind re-files it on the next run. The command names the files
 * it found; the edit is still a human's.
 *
 * Dry by default. `--apply` is the only thing that writes.
 */
class MoveChildBetweenRoots extends Command
{
    protected $signature = 'bim:move-child
        {--child= : The child id to move}
        {--from= : Root id or slug it stands under now}
        {--to= : Root id or slug it should stand under}
        {--apply : Do it. Without this nothing is written}';

    protected $description = 'Move a child to another root, carrying its merchants, options, ledger, services and fees';

    /** table => [child column, root column] */
    private const CARRIED = [
        'category_child_option' => ['child_id', 'category_id'],
        'category_child_option_decisions' => ['child_id', 'category_id'],
        'category_child_service_fees' => ['child_id', 'category_id'],
        'category_platform_services' => ['child_id', 'category_id'],
        'category_service_configs' => ['child_id', 'category_id'],
        'users' => ['category_child_id', 'category_id'],
    ];

    public function handle(): int
    {
        $childId = (int) $this->option('child');
        $from = $this->rootId((string) $this->option('from'));
        $to = $this->rootId((string) $this->option('to'));

        if ($childId <= 0 || $from <= 0 || $to <= 0) {
            $this->error('--child, --from and --to are all required, and the roots must exist.');

            return self::INVALID;
        }

        $child = DB::table('category_children_master')->where('id', $childId)->first(['id', 'name_ar']);

        if (! $child) {
            $this->error("No child #{$childId}.");

            return self::INVALID;
        }

        if (! DB::table('category_parent_child')->where('child_id', $childId)->where('parent_id', $from)->exists()) {
            $this->error("«{$child->name_ar}» does not stand under root #{$from}.");

            return self::INVALID;
        }

        if (DB::table('category_parent_child')->where('child_id', $childId)->where('parent_id', $to)->exists()) {
            // Moving INTO a root it already stands under would collapse two
            // memberships into one and silently drop whichever set of scoped
            // rows lost the unique-key race.
            $this->error("«{$child->name_ar}» already stands under root #{$to}; this would be a merge, not a move.");

            return self::INVALID;
        }

        $rows = [];

        foreach (self::CARRIED as $table => [$childCol, $rootCol]) {
            $rows[$table] = DB::table($table)->where($childCol, $childId)->where($rootCol, $from)->count();
        }

        $this->info("«{$child->name_ar}» #{$childId} : root {$from} → {$to}");
        $this->table(
            ['table', 'rows carried'],
            collect($rows)->map(fn ($n, $t) => [$t, $n])->values()->all()
        );

        $shared = DB::table('category_child_option')->where('child_id', $childId)->where('category_id', 0)->count();
        $this->line("  shared option rows left alone (category_id = 0) : {$shared}");

        if (! $this->option('apply')) {
            $this->seederWarning($child->name_ar);
            $this->newLine();
            $this->warn('Nothing was written. Re-run with --apply.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($childId, $from, $to) {
            foreach (self::CARRIED as $table => [$childCol, $rootCol]) {
                DB::table($table)->where($childCol, $childId)->where($rootCol, $from)
                    ->update([$rootCol => $to]);
            }

            DB::table('category_parent_child')->where('child_id', $childId)->where('parent_id', $from)
                ->update(['parent_id' => $to]);
        });

        $this->info('Moved.');
        $this->seederWarning($child->name_ar);

        return self::SUCCESS;
    }

    private function rootId(string $value): int
    {
        if ($value === '') {
            return 0;
        }

        return (int) (ctype_digit($value)
            ? DB::table('categories')->where('id', (int) $value)->value('id')
            : DB::table('categories')->where('slug', $value)->value('id'));
    }

    /** A root block left in a seeder file re-files the child on the next run. */
    private function seederWarning(string $name): void
    {
        $found = [];

        foreach (glob(database_path('seeders/data/*.php')) ?: [] as $file) {
            if (str_contains((string) file_get_contents($file), $name)) {
                $found[] = basename($file);
            }
        }

        if (! $found) {
            return;
        }

        $this->newLine();
        $this->warn('These seeder files name the child — check the ROOT each names it under:');

        foreach ($found as $file) {
            $this->line('  ' . $file);
        }
    }
}
