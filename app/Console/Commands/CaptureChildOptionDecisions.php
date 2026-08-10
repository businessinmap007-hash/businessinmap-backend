<?php

namespace App\Console\Commands;

use App\Services\Catalog\ChildOptionDecisions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Captures the divergence that already existed between the broad option seeders
 * and the curated database, once, as the starting withdrawal record.
 *
 *     php artisan taxonomy:capture-withdrawals --dry-run
 *     php artisan taxonomy:capture-withdrawals
 *
 * From today forward every hand removal is recorded as it happens, because the
 * admin's door writes it (`CategoryChildOptionScope`). But everything the owner
 * unticked BEFORE the record existed left no trace: `category_child_option` has
 * no timestamps and there is no audit log, so «never granted» and «granted then
 * removed» are indistinguishable after the fact.
 *
 * What CAN be said is narrower and still enough: for a seeder known to have run,
 * anything it declares and the database does not hold is something the chain has
 * already decided against. That is the inference this makes, and it is why every
 * row it writes is stamped `baseline` rather than `admin` — a later reader must
 * be able to tell a measurement from an observation.
 *
 * The command is idempotent by construction. The seeders it measures consult the
 * withdrawal record themselves, so once a divergence is captured they stop
 * proposing it and the next run finds nothing.
 */
class CaptureChildOptionDecisions extends Command
{
    protected $signature = 'taxonomy:capture-withdrawals {--dry-run : اعرض ما سيُسجَّل دون كتابة}';

    protected $description = 'Record the current seeder-vs-database divergence as the baseline withdrawal set';

    /**
     * The seeders that assign a whole group at once. Narrow remodel seeders are
     * deliberately not measured: they write a short curated list for one trade,
     * and treating a name they never mention as a withdrawal would be inventing
     * a decision nobody made.
     */
    private const BROAD_SEEDERS = [
        \Database\Seeders\LinkCategoryChildrenToOptionsSeeder::class,
        \Database\Seeders\ChildOptionScopeSeeder::class,
        \Database\Seeders\ChildOptionGroupsSeeder::class,
        \Database\Seeders\MenuLineOptionsSeeder::class,
        \Database\Seeders\VehicleOptionGroupsSeeder::class,
    ];

    public function handle(ChildOptionDecisions $withdrawals): int
    {
        $proposed = [];

        foreach (self::BROAD_SEEDERS as $class) {
            $before = $this->snapshot();

            DB::beginTransaction();

            try {
                (new $class)->run();
                $added = array_diff($this->snapshot(), $before);
            } finally {
                // ALWAYS. This measures; it must never be the thing that writes.
                DB::rollBack();
            }

            $short = class_basename($class);
            $this->line(sprintf('  %-38s تقترح %d رابطًا', $short, count($added)));

            foreach ($added as $key) {
                $proposed[$key] = $short;
            }
        }

        if ($proposed === []) {
            $this->info('لا يوجد اختلاف — البذور والقاعدة متفقتان.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('سيُسجَّل سحبًا (' . count($proposed) . ' رابطًا):');

        foreach ($this->describe(array_keys($proposed)) as $line) {
            $this->line('  ' . $line);
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->comment('تشغيل تجريبي — لم يُكتب شيء.');

            return self::SUCCESS;
        }

        $written = 0;

        foreach (array_keys($proposed) as $key) {
            [$childId, $rootId, $optionId] = array_map('intval', explode('|', $key));

            $written += $withdrawals->record($childId, $rootId, [$optionId], 'baseline');
        }

        $this->newLine();
        $this->info("سُجِّل {$written} سحبًا.");

        return self::SUCCESS;
    }

    /** @return array<int,string> "child|root|option" for every link that exists. */
    private function snapshot(): array
    {
        return DB::table('category_child_option')
            ->get(['child_id', 'category_id', 'option_id'])
            ->map(fn ($r) => "{$r->child_id}|{$r->category_id}|{$r->option_id}")
            ->all();
    }

    /**
     * @param  array<int,string>  $keys
     * @return array<int,string>
     */
    private function describe(array $keys): array
    {
        $lines = [];

        foreach ($keys as $key) {
            [$childId, $rootId, $optionId] = array_map('intval', explode('|', $key));

            $child = DB::table('category_children_master')->where('id', $childId)->value('name_ar');
            $option = DB::table('options')->where('id', $optionId)->value('name_ar');
            $group = DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->where('o.id', $optionId)->value('g.name_ar');
            $root = $rootId === 0 ? 'كل الجذور' : DB::table('categories')->where('id', $rootId)->value('name_ar');

            $lines[] = sprintf('#%-4d «%s» × %s — «%s» / %s', $childId, $child, $root, $option, $group);
        }

        sort($lines);

        return $lines;
    }
}
