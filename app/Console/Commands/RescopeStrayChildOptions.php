<?php

namespace App\Console\Commands;

use App\Models\CategoryChildOption;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Unfile option rows that name a root their child does not sit under.
 *
 * `category_child_option.category_id` is 0 for «under every root» and a root id
 * for «under that root alone». A row naming a root the child has since left is
 * reachable by nothing: `CategoryChildOptionScope::idsFor($child, $root)` is
 * only ever called with a root the child IS under, so the row is neither
 * offered to a merchant nor listed on any screen — while still counting on the
 * admin's badge and still being returned by anything that reads by child alone.
 *
 * Eighteen of them existed when this was written, and seventeen are the same
 * mistake: `bim:fold-child` unlinks the loser from its roots and deletes its
 * service wiring, but never touched its option rows. So «انترنت كافيه»،
 * «بينج بونج»، «نادي صحي» and the two gypsum children each kept a handful of
 * rows pointing at the root they had just been retired out of.
 *
 * The eighteenth is a live child: «استوديوهات» #271 sits under «فنون و ترفية»
 * and had «كاش» and «تقسيط» filed under «المحلات أو أونلاين». That is why it
 * read as the one child on the platform with no descriptive axis at all — it
 * had one, filed where nothing could reach it.
 *
 * ── Rescoped, not deleted ────────────────────────────────────────────────────
 *
 * The row moves to ALL_ROOTS rather than being dropped, because a child's
 * options are its VOCABULARY and this taxonomy keeps those: the eighty rootless
 * children are the remodels' undo record, and «unlinked ≠ unused». Wiring is
 * swept, rows are kept. A retired child ends up saying what it always said,
 * filed under no root — which is exactly what the child itself now is.
 *
 * For a live child the move is also the repair: shared means «under every root
 * it sits beneath», so #271 gets its payment axis back under the root it is
 * actually in.
 *
 * A row already present at ALL_ROOTS is dropped rather than duplicated — the
 * pair is unique, and the shared row already says everything the stray one did.
 *
 * Dry by default. `--apply` is the only thing that writes.
 */
class RescopeStrayChildOptions extends Command
{
    protected $signature = 'bim:rescope-stray-options
        {--child=* : Limit to these child ids (default: every child with a stray row)}
        {--apply : Do it. Without this nothing is written}';

    protected $description = 'Move option rows naming a root their child has left to ALL_ROOTS';

    public function handle(): int
    {
        $strays = DB::table('category_child_option as cco')
            ->join('category_children_master as c', 'c.id', '=', 'cco.child_id')
            ->join('categories as cat', 'cat.id', '=', 'cco.category_id')
            ->leftJoin('options as o', 'o.id', '=', 'cco.option_id')
            ->where('cco.category_id', '>', CategoryChildOption::ALL_ROOTS)
            ->when($this->option('child'), fn ($q) => $q->whereIn('cco.child_id', $this->option('child')))
            ->whereNotExists(fn ($q) => $q->from('category_parent_child as pc')
                ->whereColumn('pc.child_id', 'cco.child_id')
                ->whereColumn('pc.parent_id', 'cco.category_id'))
            ->orderBy('cco.child_id')
            ->get([
                'cco.child_id', 'cco.category_id', 'cco.option_id',
                'c.name_ar as child', 'cat.name_ar as root', 'o.name_ar as option',
            ]);

        if ($strays->isEmpty()) {
            $this->info('لا توجد صفوف خيارات تحت جذور تركتها أبناؤها.');

            return self::SUCCESS;
        }

        $rows = [];
        $moved = 0;
        $dropped = 0;

        foreach ($strays->groupBy('child_id') as $childId => $group) {
            $childId = (int) $childId;

            // Whether the child still sits anywhere. A retired child keeps its
            // vocabulary as the record; a live one gets it back in play.
            $roots = DB::table('category_parent_child')->where('child_id', $childId)
                ->join('categories', 'categories.id', '=', 'category_parent_child.parent_id')
                ->pluck('categories.name_ar');

            $shared = DB::table('category_child_option')
                ->where('child_id', $childId)
                ->where('category_id', CategoryChildOption::ALL_ROOTS)
                ->pluck('option_id')
                ->map(fn ($id) => (int) $id)
                ->flip();

            $toMove = $group->reject(fn ($row) => $shared->has((int) $row->option_id));
            $toDrop = $group->filter(fn ($row) => $shared->has((int) $row->option_id));

            $moved += $toMove->count();
            $dropped += $toDrop->count();

            $rows[] = [
                $group->first()->child . " #{$childId}",
                $roots->isEmpty() ? 'متقاعد' : $roots->implode('، '),
                $group->pluck('root')->unique()->implode('، '),
                $toMove->count(),
                $toDrop->count(),
            ];

            if (! $this->option('apply')) {
                continue;
            }

            DB::transaction(function () use ($childId, $toMove, $toDrop) {
                foreach ($toMove as $row) {
                    DB::table('category_child_option')
                        ->where('child_id', $childId)
                        ->where('category_id', (int) $row->category_id)
                        ->where('option_id', (int) $row->option_id)
                        ->update(['category_id' => CategoryChildOption::ALL_ROOTS]);
                }

                foreach ($toDrop as $row) {
                    DB::table('category_child_option')
                        ->where('child_id', $childId)
                        ->where('category_id', (int) $row->category_id)
                        ->where('option_id', (int) $row->option_id)
                        ->delete();
                }
            });
        }

        $this->table(['child', 'roots now', 'filed under', 'rescoped', 'dropped as duplicate'], $rows);
        $this->line("{$moved} صفًا إلى «كل الجذور» و{$dropped} صفًا مكرّرًا حُذف.");

        if (! $this->option('apply')) {
            $this->newLine();
            $this->warn('Nothing was written. Re-run with --apply.');
        }

        return self::SUCCESS;
    }
}
