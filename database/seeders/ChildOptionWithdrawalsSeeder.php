<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildOptionWithdrawals;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Enforces the owner's hand curation over every option seeder.
 *
 *     php artisan db:seed --class=ChildOptionWithdrawalsSeeder
 *
 * Five of the forty-one seeders that write `category_child_option` now consult
 * `ChildOptionWithdrawals` before granting — the broad ones, which are the ones
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
class ChildOptionWithdrawalsSeeder extends Seeder
{
    public function run(): void
    {
        $withdrawals = DB::table(ChildOptionWithdrawals::TABLE)
            ->get(['child_id', 'category_id', 'option_id']);

        if ($withdrawals->isEmpty()) {
            $this->command?->info('Child option withdrawals: لا يوجد سحب مسجَّل.');

            return;
        }

        $removed = 0;

        DB::transaction(function () use ($withdrawals, &$removed) {
            foreach ($withdrawals as $row) {
                $rootId = (int) $row->category_id;

                $query = DB::table('category_child_option')
                    ->where('child_id', (int) $row->child_id)
                    ->where('option_id', (int) $row->option_id);

                // A withdrawal under one root may not delete the shared row —
                // that would strip the option from every other root as a side
                // effect, the exact bug per-root rows exist to end. The shared
                // row is split first: handed explicitly to the child's other
                // roots, then dropped.
                if ($rootId > 0) {
                    $this->splitSharedRow((int) $row->child_id, $rootId, (int) $row->option_id);

                    $query->where('category_id', $rootId);
                }

                $removed += $query->delete();
            }
        });

        $this->command?->info('Child option withdrawals:');
        $this->command?->line('  - سحوبات مسجَّلة : ' . $withdrawals->count());
        $this->command?->line("  - روابط أُزيلت : {$removed}");
    }

    private function splitSharedRow(int $childId, int $rootId, int $optionId): void
    {
        $shared = DB::table('category_child_option')
            ->where('child_id', $childId)
            ->where('category_id', ChildOptionWithdrawals::ALL_ROOTS)
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
            ->where('category_id', ChildOptionWithdrawals::ALL_ROOTS)
            ->where('option_id', $optionId)
            ->delete();
    }
}
