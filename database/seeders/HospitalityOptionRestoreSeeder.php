<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildOptionDecisions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Restores the two hospitality children emptied by an accidental save.
 *
 *     php artisan db:seed --class=HospitalityOptionRestoreSeeder
 *
 * See data/hospitality_option_restore.php for what each child gets and why the
 * base is derived from its intact siblings rather than invented.
 *
 * ADDITIVE, and that is the whole safety of it: it never withdraws an option, so
 * running it over a child an admin has since curated cannot undo his work. The
 * only thing it deletes is a root-scoped row that duplicates a shared one, which
 * is not an option, it is bookkeeping.
 *
 * ── And it asks the ledger first, since 2026-08-23 ──────────────────────────
 *
 * This used to add «the worst it can do is hand back something he removed on
 * purpose, which the card removes again in one click». That stopped being an
 * acceptable worst case the day the removals started being recorded. On
 * 2026-08-20 the owner went down the six kinds of stay and took «ملاءمة
 * المكان» off four of them and «إطلالة الوحدة» off the Nile boat — the room's
 * facilities answer both, and a cruiser has no fixed view. This seeder would
 * have handed all five back on the next run, and the run after that.
 *
 * `ChildOptionDecisions::filter()` is one call and it makes the restore mean
 * what its name says: put back what was lost, not what was given up.
 */
class HospitalityOptionRestoreSeeder extends Seeder
{
    public function run(): void
    {
        $data = require __DIR__ . '/data/hospitality_option_restore.php';

        DB::transaction(function () use ($data) {
            $rootId = (int) DB::table('categories')->where('slug', $data['root_slug'])->value('id');

            if ($rootId <= 0) {
                $this->command?->warn("  ! الجذر «{$data['root_slug']}» غير موجود.");

                return;
            }

            $base = $this->baseOptionIds($data);

            $this->command?->info('Hospitality restore:');

            foreach ($data['children'] as $name) {
                $childId = (int) DB::table('category_children_master')->where('name_ar', $name)->value('id');

                if ($childId <= 0) {
                    $this->command?->warn("  ! «{$name}» غير موجود — تُخطّي.");

                    continue;
                }

                $added = $this->grantShared($childId, $base);
                $collapsed = $this->collapseScoped($childId);

                $total = DB::table('category_child_option')->where('child_id', $childId)->count();

                $this->command?->line("  - «{$name}» #{$childId} : أُعيد {$added} · صفوف مقيّدة طُويت {$collapsed} · الإجمالي {$total}");
            }
        });
    }

    /** @return \Illuminate\Support\Collection<int,int> */
    private function baseOptionIds(array $data)
    {
        $ids = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->whereIn('g.name_ar', $data['base_groups'])
            ->pluck('o.id');

        return $ids
            ->merge(DB::table('options')->whereIn('name_ar', $data['base_options'])->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int,int>  $optionIds
     *
     * Compared against the SHARED rows alone. Comparing against every row was a
     * bug with teeth: an option that existed only as a root-scoped row counted
     * as «already have», so no shared copy was written — and then
     * collapseScoped() deleted the scoped row and the option was simply gone.
     * That is how the first run left مرافق الإقامة with five of its ten.
     */
    private function grantShared(int $childId, $optionIds): int
    {
        $have = DB::table('category_child_option')->where('child_id', $childId)
            ->where('category_id', 0)
            ->pluck('option_id')->map(fn ($id) => (int) $id)->all();

        // What he withdrew by hand is not «lost» — it is decided. Asked at
        // root 0, the ledger blocks a withdrawal recorded under ANY root,
        // which is right: these rows are written shared.
        $allowed = app(ChildOptionDecisions::class)->filter($childId, 0, $optionIds);

        $rows = collect($allowed)
            ->reject(fn ($id) => in_array($id, $have, true))
            ->map(fn ($id) => [
                'child_id' => $childId,
                'category_id' => 0,
                'option_id' => $id,
                'reorder' => 0,
            ])->values()->all();

        $added = 0;

        foreach (array_chunk($rows, 200) as $chunk) {
            $added += DB::table('category_child_option')->insertOrIgnore($chunk);
        }

        return $added;
    }

    /**
     * A child hanging from ONE root has nothing to scope an option against, so a
     * root-scoped row is only ever noise there — and a row that duplicates a
     * shared one makes the child look like it carries the option twice.
     */
    private function collapseScoped(int $childId): int
    {
        if (DB::table('category_parent_child')->where('child_id', $childId)->count() > 1) {
            return 0;
        }

        $scoped = DB::table('category_child_option')
            ->where('child_id', $childId)->where('category_id', '>', 0)
            ->pluck('option_id')->map(fn ($id) => (int) $id)->unique();

        if ($scoped->isEmpty()) {
            return 0;
        }

        $this->grantShared($childId, $scoped);

        return DB::table('category_child_option')
            ->where('child_id', $childId)->where('category_id', '>', 0)->delete();
    }
}
