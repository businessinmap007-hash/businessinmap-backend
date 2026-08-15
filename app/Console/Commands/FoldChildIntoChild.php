<?php

namespace App\Console\Commands;

use App\Services\CategoryChildOptionScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fold one taxonomy child into another and retire the empty one.
 *
 * Retiring is **not** deleting. `category_children_master` has neither
 * `is_active` nor `deleted_at`, and this taxonomy's own convention — the eighty
 * rootless children left by earlier remodels — is that a child with no root is
 * retired while its row stays as the undo record. So the last step here unlinks
 * rather than removes, and the fold can be read backwards afterwards.
 *
 * Five things move, and missing any one of them leaves a half-fold:
 *
 *   1. merchants        `users.category_child_id`
 *   2. options          the union, per root, through CategoryChildOptionScope
 *   3. service links    `category_platform_services`
 *   4. service configs  `category_service_configs` — only where the keeper has none
 *   5. the roots        unlinked from the loser, which is the retirement
 *
 * What it CANNOT do is strip the loser's name out of the seeder data files.
 * Those are keyed by `name_ar`, and a name left in one of them puts the child
 * back on the next run. The command names the files it found so the edit is
 * not forgotten.
 *
 * Dry by default. `--apply` is the only thing that writes.
 */
class FoldChildIntoChild extends Command
{
    protected $signature = 'bim:fold-child
        {--from=* : Child ids to fold away (repeatable)}
        {--into= : The child id that survives}
        {--rename= : Rename the survivor to this Arabic name}
        {--rename-en= : …and this English name}
        {--apply : Do it. Without this nothing is written}';

    protected $description = 'Fold children into one, moving merchants/options/services, and retire the empties';

    public function __construct(private readonly CategoryChildOptionScope $scope)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $keeperId = (int) $this->option('into');
        $loserIds = collect($this->option('from'))->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($keeperId <= 0 || $loserIds->isEmpty()) {
            $this->error('Both --into and at least one --from are required.');

            return self::INVALID;
        }

        if ($loserIds->contains($keeperId)) {
            $this->error('A child cannot be folded into itself.');

            return self::INVALID;
        }

        $names = DB::table('category_children_master')
            ->whereIn('id', $loserIds->push($keeperId))
            ->pluck('name_ar', 'id');

        if (! isset($names[$keeperId])) {
            $this->error("No child #{$keeperId}.");

            return self::FAILURE;
        }

        $loserIds = $loserIds->reject(fn ($id) => $id === $keeperId)->values();
        $keeperRoots = $this->rootsOf($keeperId);

        $this->line("keeper: «{$names[$keeperId]}» #{$keeperId}  roots: " . $keeperRoots->implode(', '));
        $this->newLine();

        $rows = [];

        foreach ($loserIds as $loserId) {
            if (! isset($names[$loserId])) {
                $this->warn("No child #{$loserId} — skipped.");

                continue;
            }

            $rows[] = $this->fold($loserId, $keeperId, $names, $keeperRoots);
        }

        $this->table(['folded', 'merchants', 'options added', 'links', 'configs', 'roots unlinked'], $rows);

        if ($this->option('rename')) {
            $this->line(sprintf(
                '%s «%s» → «%s»',
                $this->option('apply') ? 'renamed' : 'would rename',
                $names[$keeperId],
                $this->option('rename')
            ));

            if ($this->option('apply')) {
                DB::table('category_children_master')->where('id', $keeperId)->update(array_filter([
                    'name_ar' => (string) $this->option('rename'),
                    'name_en' => $this->option('rename-en') ? (string) $this->option('rename-en') : null,
                    'updated_at' => now(),
                ]));
            }
        }

        $this->seederWarning($loserIds->map(fn ($id) => $names[$id] ?? '')->filter());

        if (! $this->option('apply')) {
            $this->newLine();
            $this->warn('Nothing was written. Re-run with --apply.');
        }

        return self::SUCCESS;
    }

    /** @return \Illuminate\Support\Collection<int,int> */
    private function rootsOf(int $childId)
    {
        return DB::table('category_parent_child')->where('child_id', $childId)
            ->pluck('parent_id')->map(fn ($id) => (int) $id)->unique()->values();
    }

    /** @return array<int,mixed> */
    private function fold(int $loserId, int $keeperId, $names, $keeperRoots): array
    {
        $merchants = DB::table('users')->where('category_child_id', $loserId)->count();
        $loserRoots = $this->rootsOf($loserId);

        // Options are per root, so the union is taken per root the KEEPER sits
        // under — carrying a loser's option into a root the keeper is not in
        // would grant it to nobody and clutter the table.
        $add = [];

        foreach ($keeperRoots as $rootId) {
            $mine = $this->scope->idsFor($keeperId, $rootId);
            $theirs = $loserRoots->contains($rootId) || $loserRoots->isEmpty()
                ? $this->scope->idsFor($loserId, $rootId)
                : $this->scope->idsFor($loserId, 0);

            $missing = collect($theirs)->diff($mine)->values();

            if ($missing->isNotEmpty()) {
                $add[$rootId] = $missing;
            }
        }

        $addedCount = collect($add)->flatten()->count();

        $links = DB::table('category_platform_services')->where('child_id', $loserId)->count();
        $configs = DB::table('category_service_configs')->where('child_id', $loserId)->count();

        if ($this->option('apply')) {
            DB::transaction(function () use ($loserId, $keeperId, $add, $keeperRoots) {
                DB::table('users')->where('category_child_id', $loserId)
                    ->update(['category_child_id' => $keeperId]);

                foreach ($add as $rootId => $optionIds) {
                    // Through the scope so each grant is PINNED — a fold is a
                    // hand decision, and a replace-style seeder must not drop
                    // what the fold carried over.
                    $this->scope->grantFor($keeperId, (int) $rootId, $optionIds);
                }

                // A service the keeper does not offer at all comes across;
                // where both offer it, the keeper's own configuration wins,
                // because it is the one its merchants already sell against.
                foreach ($keeperRoots as $rootId) {
                    $this->carryServices($loserId, $keeperId, (int) $rootId);
                }

                // The retirement: no root, so nothing lists it — and the row
                // stays as the record of what was folded.
                DB::table('category_parent_child')->where('child_id', $loserId)->delete();

                // Its service wiring goes with it. Unlinking the root alone
                // left five retired children still holding live links and
                // configs — wiring reachable by anything that reads by
                // `child_id` without joining a root, which the discovery and
                // owner paths do. A child nothing can reach must be able to
                // sell nothing; the config rows are not the undo record, the
                // master row is.
                DB::table('category_service_configs')->where('child_id', $loserId)->delete();
                DB::table('category_platform_services')->where('child_id', $loserId)->delete();
                DB::table('category_child_service_fees')->where('child_id', $loserId)->delete();
            });
        }

        return [$names[$loserId] ?? "#{$loserId}", $merchants, $addedCount, $links, $configs, $loserRoots->count()];
    }

    private function carryServices(int $loserId, int $keeperId, int $rootId): void
    {
        $keeperHas = DB::table('category_platform_services')
            ->where('child_id', $keeperId)->where('category_id', $rootId)
            ->pluck('platform_service_id');

        $rows = DB::table('category_platform_services')
            ->where('child_id', $loserId)->where('category_id', $rootId)
            ->whereNotIn('platform_service_id', $keeperHas)
            ->get();

        foreach ($rows as $row) {
            DB::table('category_platform_services')->insertOrIgnore([
                'category_id' => $rootId,
                'child_id' => $keeperId,
                'platform_service_id' => $row->platform_service_id,
                'is_active' => $row->is_active,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $config = DB::table('category_service_configs')
                ->where('child_id', $loserId)->where('category_id', $rootId)
                ->where('platform_service_id', $row->platform_service_id)
                ->first();

            if ($config) {
                DB::table('category_service_configs')->insertOrIgnore([
                    'category_id' => $rootId,
                    'child_id' => $keeperId,
                    'platform_service_id' => $row->platform_service_id,
                    'config' => $config->config,
                    'is_active' => $config->is_active,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /** A name left in a seeder file puts the child back on the next run. */
    private function seederWarning($names): void
    {
        if ($names->isEmpty()) {
            return;
        }

        $found = [];

        foreach (glob(database_path('seeders/data/*.php')) ?: [] as $file) {
            $body = (string) file_get_contents($file);

            foreach ($names as $name) {
                if ($name !== '' && str_contains($body, $name)) {
                    $found[basename($file)][] = $name;
                }
            }
        }

        if (! $found) {
            return;
        }

        $this->newLine();
        $this->warn('These seeder files still name a folded child — strip them or the next run restores it:');

        foreach ($found as $file => $hits) {
            $this->line('  ' . $file . ' → ' . implode('، ', array_unique($hits)));
        }
    }
}
