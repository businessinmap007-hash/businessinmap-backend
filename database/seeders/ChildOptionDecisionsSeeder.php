<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildOptionDecisions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Enforces the owner's hand curation over every option seeder.
 *
 *     php artisan db:seed --class=ChildOptionDecisionsSeeder
 *
 * Five of the forty-one seeders that write `category_child_option` now consult
 * `ChildOptionDecisions` before granting — the broad ones, which are the ones
 * that assign a whole group by keyword and did all the damage. The other
 * thirty-six write narrow, curated lists and were left alone rather than
 * rewritten, because editing thirty-six seeders to fix a problem five of them
 * cause is how a slice becomes a rewrite.
 *
 * This runs LAST and closes that gap from the other end: whatever anybody
 * granted during the chain, a withdrawal removes again. Consulting up front is
 * still worth doing — it keeps each seeder honest on its own, which is what its
 * own idempotency test measures — but this is what makes the guarantee hold for
 * the whole chain.
 *
 * It only ever deletes rows that a withdrawal names. It cannot invent one.
 */
class ChildOptionDecisionsSeeder extends Seeder
{
    public function run(): void
    {
        $decisions = DB::table(ChildOptionDecisions::TABLE)
            ->get(['child_id', 'category_id', 'option_id', 'kind']);

        if ($decisions->isEmpty()) {
            $this->command?->info('Child option decisions: لا يوجد قرار مسجَّل.');

            return;
        }

        $removed = $restored = 0;

        DB::transaction(function () use ($decisions, &$removed, &$restored) {
            foreach ($decisions as $row) {
                $childId = (int) $row->child_id;
                $rootId = (int) $row->category_id;
                $optionId = (int) $row->option_id;

                if ((string) $row->kind === ChildOptionDecisions::PINNED) {
                    $restored += $this->restore($childId, $rootId, $optionId);

                    continue;
                }

                $query = DB::table('category_child_option')
                    ->where('child_id', $childId)
                    ->where('option_id', $optionId);

                // A withdrawal under one root may not delete the shared row —
                // that would strip the option from every other root as a side
                // effect, the exact bug per-root rows exist to end. The shared
                // row is split first: handed explicitly to the child's other
                // roots, then dropped.
                if ($rootId > 0) {
                    $this->splitSharedRow($childId, $rootId, $optionId);

                    $query->where('category_id', $rootId);
                }

                $removed += $query->delete();
            }
        });

        $this->command?->info('Child option decisions:');
        $this->command?->line('  - قرارات مسجَّلة : ' . $decisions->count());
        $this->command?->line("  - روابط أُزيلت (سحب) : {$removed}");
        $this->command?->line("  - روابط أُعيدت (تثبيت) : {$restored}");
    }

    /**
     * Put a pinned option back if a seeder dropped it.
     *
     * A shared row already covers this root, so «reaches this root» is the test,
     * not «has a row at this exact scope» — inserting a root-scoped duplicate
     * beside a shared one would grow the table for nothing and make the two
     * disagree about what the child offers.
     */
    private function restore(int $childId, int $rootId, int $optionId): int
    {
        $reaches = DB::table('category_child_option')
            ->where('child_id', $childId)
            ->where('option_id', $optionId)
            ->when(
                $rootId > 0,
                fn ($q) => $q->whereIn('category_id', [ChildOptionDecisions::ALL_ROOTS, $rootId]),
                fn ($q) => $q->where('category_id', ChildOptionDecisions::ALL_ROOTS)
            )
            ->exists();

        if ($reaches) {
            return 0;
        }

        DB::table('category_child_option')->insertOrIgnore([
            'child_id' => $childId,
            'category_id' => $rootId,
            'option_id' => $optionId,
            'reorder' => 0,
        ]);

        return 1;
    }

    private function splitSharedRow(int $childId, int $rootId, int $optionId): void
    {
        $shared = DB::table('category_child_option')
            ->where('child_id', $childId)
            ->where('category_id', ChildOptionDecisions::ALL_ROOTS)
            ->where('option_id', $optionId)
            ->exists();

        if (! $shared) {
            return;
        }

        $otherRoots = DB::table('category_parent_child')
            ->where('child_id', $childId)
            ->where('parent_id', '!=', $rootId)
            ->pluck('parent_id')
            ->map(fn ($id) => (int) $id);

        $rows = $otherRoots->map(fn ($other) => [
            'child_id' => $childId,
            'category_id' => $other,
            'option_id' => $optionId,
            'reorder' => 0,
        ])->values()->all();

        if ($rows !== []) {
            DB::table('category_child_option')->insertOrIgnore($rows);
        }

        DB::table('category_child_option')
            ->where('child_id', $childId)
            ->where('category_id', ChildOptionDecisions::ALL_ROOTS)
            ->where('option_id', $optionId)
            ->delete();
    }
}
