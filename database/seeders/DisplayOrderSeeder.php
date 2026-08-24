<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * «نقطة تنظيمية: يرجى ترتيب الأبناء عند الظهور أبجديًا، ومجموعات الخدمات إما
 *  أبجديًا أو بالتشابه» — المالك، 2026-08-24.
 *
 *     php artisan db:seed --class=DisplayOrderSeeder
 *
 * ── Why this writes `reorder` instead of changing every screen ──────────────
 *
 * A child is listed by a dozen different queries — the admin index, three
 * pickers, the signup dropdown, the workbench — and most of them already order
 * by `COALESCE(reorder, 999999)`. Sorting each call site by name would be a
 * dozen edits and a thirteenth screen tomorrow that forgets. Numbering
 * `reorder` alphabetically once makes every one of them alphabetical, and
 * leaves the column meaning exactly what its name says: where this row sits
 * when shown.
 *
 * ── The groups keep their tiers ─────────────────────────────────────────────
 *
 * `OptionGroup::inDisplayOrder()` sorts by ROLE first — what is sold, then what
 * qualifies it, then what merely describes — and that ranking is a rule, not a
 * default: a merchant's pricing screen must open on the priced lists. So the
 * alphabet is applied WITHIN each role rather than across all of them.
 *
 * ── Ordered by MySQL, not by PHP ────────────────────────────────────────────
 *
 * The rank is read from the database's own `ORDER BY name_ar`, so the stored
 * order and the order a query without this column would produce are the same
 * sort. Sorting Arabic in PHP with `strcmp` would put «أ» and «ا» in different
 * places from the collation every other screen uses.
 *
 * Idempotent: a second run writes the same numbers.
 */
class DisplayOrderSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $children = $this->numberChildren();
            $groups = $this->numberGroups();

            $this->command?->info('Display order:');
            $this->command?->line("  - أبناء رُقِّموا أبجديًا : {$children}");
            $this->command?->line("  - مجموعات خيارات رُقِّمت داخل كل دور : {$groups}");
        });
    }

    private function numberChildren(): int
    {
        $rows = DB::table('category_children_master')
            ->orderBy('name_ar')
            ->orderBy('id')
            ->pluck('id');

        return $this->write('category_children_master', $rows, 10);
    }

    /**
     * Alphabetical inside each tier, and the tiers stay 10,000 apart so a
     * `line` group can never sort past a `modifier` one on the number alone.
     */
    private function numberGroups(): int
    {
        $n = 0;
        $base = 0;

        foreach (['line', 'modifier', 'descriptive'] as $role) {
            $rows = DB::table('option_groups')
                ->where('price_role', $role)
                ->orderBy('name_ar')
                ->orderBy('id')
                ->pluck('id');

            $n += $this->write('option_groups', $rows, 10, $base);
            $base += 10000;
        }

        // Anything with an unrecognised role sorts after all three, keeping the
        // column total: a NULL here would read as «not numbered yet».
        $rest = DB::table('option_groups')
            ->whereNotIn('price_role', ['line', 'modifier', 'descriptive'])
            ->orderBy('name_ar')
            ->orderBy('id')
            ->pluck('id');

        return $n + $this->write('option_groups', $rest, 10, $base);
    }

    /**
     * @param  \Illuminate\Support\Collection<int,int>  $ids
     * @param  int  $step  gaps, so a row can be nudged between two by hand
     *                     without renumbering the table
     */
    private function write(string $table, $ids, int $step, int $base = 0): int
    {
        $n = 0;
        $rank = $base;

        foreach ($ids as $id) {
            $rank += $step;

            $n += DB::table($table)
                ->where('id', $id)
                ->where(function ($q) use ($rank) {
                    $q->where('reorder', '!=', $rank)->orWhereNull('reorder');
                })
                ->update(['reorder' => $rank, 'updated_at' => now()]);
        }

        return $n;
    }
}
