<?php

namespace App\Console\Commands;

use App\Services\Catalog\ChildOptionDecisions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Undo one bulk options save that hit a whole root at once.
 *
 * «هناك خطا حدث عند تعديل خيارات زراعية وحيوانية واخدت كل خيارات الرياضة بشكل
 * غريب». At 2026-08-13 23:38:25 every one of the seven children under «زراعية
 * وحيوانية» was handed the same twenty swimming-club options — سباحة، غوص،
 * ساونا، جاكوزي، مدرب شخصي — and its own vocabulary was pulled out in the same
 * instant: tractors, ploughs, drip irrigation, poultry states, livestock kinds.
 *
 * A save like that is fully recorded, which is what makes it reversible. The
 * decisions ledger writes one row per option at the moment of the save:
 *
 *   pinned      → what this save GRANTED   (undo: take it back off)
 *   withdrawn   → what this save REMOVED   (undo: put it back)
 *
 * Both directions are read from that ledger rather than guessed, and both the
 * ledger rows and the links are undone together — a decision left behind is a
 * standing order to the seeders, so reverting the links alone would leave the
 * grant blocked and the removal permanent.
 *
 * Dry by default. `--apply` is the only thing that writes.
 */
class RevertChildOptionSave extends Command
{
    protected $signature = 'bim:revert-option-save
        {--root= : The category (root) id the save was scoped to}
        {--at= : The exact decision timestamp, e.g. "2026-08-13 23:38:25"}
        {--child=* : Limit to these child ids (default: every child touched)}
        {--restore-only : Put back what was removed and un-grant nothing}
        {--apply : Undo it. Without this nothing is written}';

    protected $description = 'Undo a bulk child-options save using the decisions ledger';

    public function handle(): int
    {
        $rootId = (int) $this->option('root');
        $at = trim((string) $this->option('at'));

        if ($rootId <= 0 || $at === '') {
            $this->error('Both --root and --at are required.');

            return self::INVALID;
        }

        $decisions = DB::table(ChildOptionDecisions::TABLE)
            ->where('category_id', $rootId)
            ->where('created_at', $at)
            ->when($this->option('child'), fn ($q) => $q->whereIn('child_id', $this->option('child')))
            ->get(['id', 'child_id', 'option_id', 'kind']);

        if ($decisions->isEmpty()) {
            $this->warn("No decisions recorded under root {$rootId} at «{$at}».");

            return self::SUCCESS;
        }

        $names = DB::table('category_children_master')
            ->whereIn('id', $decisions->pluck('child_id')->unique())
            ->pluck('name_ar', 'id');

        $rows = [];
        $totals = ['ungrant' => 0, 'restore' => 0];

        /*
         * Un-granting is not always the undo.
         *
         * Under «زراعية وحيوانية» every granted option was foreign, so taking
         * them all back off restored the truth. Under «الرياضة» it is not: the
         * same save gave نادي صحي and أكاديمية رياضية their FIRST vocabulary
         * and withdrew nothing from them, so a blanket un-grant would empty
         * them, and سباحة on حمام سباحة is simply correct.
         *
         * `--restore-only` is the half that is unambiguous everywhere: put back
         * what was taken. Which children should also SHED what they were given
         * is a reading of the trade, and belongs to the owner.
         */
        $restoreOnly = (bool) $this->option('restore-only');

        foreach ($decisions->groupBy('child_id') as $childId => $group) {
            $granted = $restoreOnly
                ? collect()
                : $group->where('kind', ChildOptionDecisions::PINNED)->pluck('option_id');
            $removed = $group->where('kind', ChildOptionDecisions::WITHDRAWN)->pluck('option_id');

            $totals['ungrant'] += $granted->count();
            $totals['restore'] += $removed->count();

            $rows[] = [
                $names[$childId] ?? "#{$childId}",
                $granted->count(),
                $removed->count(),
            ];

            if (! $this->option('apply')) {
                continue;
            }

            DB::transaction(function () use ($rootId, $childId, $granted, $removed, $group, $restoreOnly) {
                // What the save granted goes back off — under THIS root only,
                // so a grant the option legitimately holds elsewhere stands.
                if ($granted->isNotEmpty()) {
                    DB::table('category_child_option')
                        ->where('child_id', $childId)
                        ->where('category_id', $rootId)
                        ->whereIn('option_id', $granted)
                        ->delete();
                }

                // What it removed comes back, at the same root scope.
                foreach ($removed->chunk(200) as $chunk) {
                    DB::table('category_child_option')->insertOrIgnore(
                        $chunk->map(fn ($optionId) => [
                            'child_id' => $childId,
                            'category_id' => $rootId,
                            'option_id' => (int) $optionId,
                            'reorder' => 0,
                        ])->values()->all()
                    );
                }

                // And the ledger rows themselves. A «pinned» left behind tells
                // every replace-style seeder to keep the swimming pool; a
                // «withdrawn» left behind tells every add-only seeder never to
                // hand the tractors back.
                //
                // On a restore-only pass the pins stay: they still describe a
                // grant that stands, and clearing them would let the next
                // seeder run drop it.
                $clear = $restoreOnly
                    ? $group->where('kind', ChildOptionDecisions::WITHDRAWN)
                    : $group;

                DB::table(ChildOptionDecisions::TABLE)->whereIn('id', $clear->pluck('id'))->delete();
            });
        }

        $this->table(['child', 'un-grant', 'restore'], $rows);
        $this->line(sprintf(
            '%d options to un-grant, %d to restore, across %d children',
            $totals['ungrant'], $totals['restore'], count($rows)
        ));

        if (! $this->option('apply')) {
            $this->warn('Nothing was written. Re-run with --apply.');
        }

        return self::SUCCESS;
    }
}
