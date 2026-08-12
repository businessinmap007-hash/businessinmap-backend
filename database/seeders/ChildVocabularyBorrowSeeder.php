<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildOptionDecisions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Lend an existing vocabulary to a sibling trade that answers the same axis.
 *
 * Borrow, never clone: this seeder writes no option row and no group. It reads
 * what a DONOR child already holds in a named group and gives the same option
 * ids to the recipients in `data/child_vocabulary_borrows.php`. That is the
 * whole of it, and it is why the operation is safe enough to re-run.
 *
 * Three refusals, in this order:
 *
 * 1. **A withdrawal binds.** If the owner unticked an option on a recipient,
 *    it stays untickled — hand curation beats the file, always. See
 *    [[seeder-withdrawal-record]]: an add-only seeder that ignores the record
 *    silently restores everything he took away, one run later.
 * 2. **A narrowing binds.** `child_option_scopes.php` may say a recipient
 *    answers only part of a group.
 * 3. **What it already holds is left alone** — including a per-root row, which
 *    a shared row would duplicate rather than replace.
 *
 * Add-only by design. Nothing here removes a link: a borrow that turns out
 * wrong is undone by unticking it on the bulk screen, which then binds this
 * seeder for good.
 */
class ChildVocabularyBorrowSeeder extends Seeder
{
    public function run(): void
    {
        $borrows = require database_path('seeders/data/child_vocabulary_borrows.php');
        $blocked = app(ChildOptionDecisions::class)->blockedByChild();
        $scopes = require database_path('seeders/data/child_option_scopes.php');

        $linked = 0;
        $withdrawn = 0;
        $narrowed = 0;
        $already = 0;

        foreach ($borrows as $borrow) {
            $group = (string) $borrow['group'];
            $donor = (int) $borrow['from'];

            $groupId = DB::table('option_groups')->where('name_ar', $group)->value('id');

            if (! $groupId) {
                $this->command?->warn("  «{$group}» does not exist — skipped");

                continue;
            }

            // What the DONOR holds, not what the group contains: the donor may
            // itself have been narrowed, and lending more than it says would
            // widen the platform behind the owner's back.
            $options = DB::table('category_child_option as co')
                ->join('options as o', 'o.id', '=', 'co.option_id')
                ->where('co.child_id', $donor)
                ->where('o.group_id', $groupId)
                ->distinct()
                ->pluck('o.id')
                ->map(fn ($id) => (int) $id);

            if ($options->isEmpty()) {
                $this->command?->warn("  #{$donor} holds nothing of «{$group}» — skipped");

                continue;
            }

            foreach ($borrow['to'] as $childId) {
                $childId = (int) $childId;
                $allowed = $scopes[$group][$childId] ?? null;

                foreach ($options as $optionId) {
                    if (isset($blocked[$childId][$optionId])) {
                        $withdrawn++;

                        continue;
                    }

                    // Absent from the map means «no narrowing», never «narrow
                    // to nothing» — the reading every other seeder gives it.
                    if ($allowed !== null && ! in_array($optionId, $allowed, true)) {
                        $narrowed++;

                        continue;
                    }

                    $holds = DB::table('category_child_option')
                        ->where('child_id', $childId)
                        ->where('option_id', $optionId)
                        ->exists();

                    if ($holds) {
                        $already++;

                        continue;
                    }

                    DB::table('category_child_option')->insert([
                        'child_id' => $childId,
                        // Shared: a borrowed axis is about the TRADE, and a
                        // furniture workshop works in beech under every root it
                        // stands beneath.
                        'category_id' => 0,
                        'option_id' => $optionId,
                        'reorder' => 0,
                    ]);

                    $linked++;
                }
            }
        }

        $this->command?->info(
            "Borrowed vocabularies: {$linked} linked · {$already} already held · "
            . "{$narrowed} outside a narrowing · {$withdrawn} refused by a withdrawal"
        );
    }
}
