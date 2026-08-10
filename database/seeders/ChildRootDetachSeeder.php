<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Takes a child off a root it does not belong under.
 *
 *     php artisan db:seed --class=ChildRootDetachSeeder
 *
 * See data/child_root_detachments.php for the list and the reasoning. The
 * difference from ChildRootMovesSeeder is the whole point: a move knows where
 * the child goes, this only knows where it must stop being. Sometimes that
 * retires the trade, because the root it is leaving is the only one it had.
 *
 * The order each entry follows, and why each step is where it is:
 *
 *   1. ACCOUNTS FIRST. A merchant left pointing at a root its child no longer
 *      hangs from vanishes from every screen at once, and no later step can
 *      notice. If an entry holds accounts and names no destination, the whole
 *      entry is refused — loudly, and nothing is touched.
 *   2. the pivot row goes — that row IS the undo record;
 *   3. the wiring for THAT root goes, and the config is only deactivated: the
 *      platform's debris rule, unchanged (OrphanChildLinksCleanupSeeder);
 *   4. the option rows go only if the child now hangs from nothing. A child
 *      that still stands elsewhere keeps every word those roots gave it.
 *
 * Idempotent: a second run reports zero of everything.
 */
class ChildRootDetachSeeder extends Seeder
{
    public function run(): void
    {
        $data = require __DIR__ . '/data/child_root_detachments.php';

        DB::transaction(function () use ($data) {
            $this->command?->info('Child root detachments:');

            foreach ($data as $entry) {
                $this->apply($entry);
            }
        });
    }

    /** @param array<string,mixed> $entry */
    private function apply(array $entry): void
    {
        $name = (string) $entry['child_name_ar'];
        $slug = (string) $entry['root_slug'];

        $rootId = (int) DB::table('categories')->where('slug', $slug)->value('id');
        $childId = (int) DB::table('category_parent_child as p')
            ->join('category_children_master as c', 'c.id', '=', 'p.child_id')
            ->where('p.parent_id', $rootId)->where('c.name_ar', $name)
            ->value('c.id');

        if ($rootId <= 0 || $childId <= 0) {
            $this->command?->line("  - «{$name}» ليس تحت «{$slug}» — لا شيء ليُحذف.");

            return;
        }

        $moved = $this->rehome($entry, $childId, $rootId);

        if ($moved === null) {
            return; // refused; rehome() has already said why
        }

        DB::table('category_parent_child')->where('parent_id', $rootId)->where('child_id', $childId)->delete();

        DB::table('category_service_configs')
            ->where('category_id', $rootId)->where('child_id', $childId)
            ->update(['is_active' => 0, 'updated_at' => now()]);

        foreach (['category_platform_services', 'category_child_service_fees'] as $table) {
            DB::table($table)->where('category_id', $rootId)->where('child_id', $childId)->delete();
        }

        $stillRooted = DB::table('category_parent_child')->where('child_id', $childId)->exists();

        if ($stillRooted) {
            // Only what this root gave it. The shared rows (category_id 0) and
            // every other root's rows are that root's business, not this one's.
            $options = DB::table('category_child_option')
                ->where('child_id', $childId)->where('category_id', $rootId)->delete();
        } else {
            $options = DB::table('category_child_option')->where('child_id', $childId)->delete();
        }

        $this->command?->line("  - «{$name}» #{$childId} × «{$slug}»"
            . ($stillRooted ? '' : '  (لم يعد تحت أي جذر)'));
        $this->command?->line("      حسابات نُقلت : {$moved} · روابط خيارات أُزيلت : {$options}");
        $this->command?->line("      السبب : {$entry['why']}");
    }

    /**
     * @param  array<string,mixed>  $entry
     * @return int|null accounts moved, or null when the entry is refused
     */
    private function rehome(array $entry, int $childId, int $rootId): ?int
    {
        $accounts = DB::table('users')
            ->where('category_child_id', $childId)->where('category_id', $rootId)->count();

        if ($accounts === 0) {
            return 0;
        }

        $target = $entry['reassign_to'] ?? null;

        if ($target === null) {
            $this->command?->warn("  ! «{$entry['child_name_ar']}» تحت «{$entry['root_slug']}» عليه {$accounts} حسابًا "
                . 'ولا وجهة معلنة — لم يُحذف.');

            return null;
        }

        $targetId = (int) DB::table('category_parent_child as p')
            ->join('category_children_master as c', 'c.id', '=', 'p.child_id')
            ->where('p.parent_id', $rootId)->where('c.name_ar', $target)
            ->value('c.id');

        if ($targetId <= 0) {
            $this->command?->warn("  ! الوجهة «{$target}» ليست تحت «{$entry['root_slug']}» — لم يُحذف.");

            return null;
        }

        return DB::table('users')
            ->where('category_child_id', $childId)->where('category_id', $rootId)
            ->update(['category_child_id' => $targetId, 'updated_at' => now()]);
    }
}
