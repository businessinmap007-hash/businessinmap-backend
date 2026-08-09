<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Re-files a child under the root a customer would actually look in.
 *
 *     php artisan db:seed --class=ChildRootMovesSeeder
 *
 * See data/child_root_moves.php for what moves and why. This is deliberately
 * NOT a remodel: nothing is retired, nothing is merged, no vocabulary changes.
 * The child keeps its options, its services and its accounts — only the root it
 * hangs from changes.
 *
 * The wiring is MOVED, not re-created. `category_platform_services`,
 * `category_service_configs` and `category_child_service_fees` are all keyed on
 * (root, child, service), so each row simply changes root — which keeps the
 * stored config and its `config_source` intact. Re-creating them through the
 * writer would have stamped every one with this seeder's name and thrown away
 * whichever admin screen had last touched it.
 *
 * Idempotent: once the child hangs from the target root, a re-run does nothing.
 */
class ChildRootMovesSeeder extends Seeder
{
    /** Every table keyed on (category_id, child_id, platform_service_id). */
    private const WIRING = [
        'category_platform_services',
        'category_service_configs',
        'category_child_service_fees',
    ];

    public function run(): void
    {
        $moves = require __DIR__ . '/data/child_root_moves.php';

        DB::transaction(function () use ($moves) {
            $this->command?->info('Child root moves:');

            foreach ($moves as $move) {
                $this->apply($move);
            }
        });
    }

    /** @param array<string,string> $move */
    private function apply(array $move): void
    {
        $name = $move['child_name_ar'];

        $childId = (int) DB::table('category_children_master')->where('name_ar', $name)->value('id');
        $from = (int) DB::table('categories')->where('slug', $move['from_root_slug'])->value('id');
        $to = (int) DB::table('categories')->where('slug', $move['to_root_slug'])->value('id');

        if ($childId <= 0 || $from <= 0 || $to <= 0) {
            $this->command?->warn("  ! «{$name}»: الابن أو أحد الجذرين غير موجود — تُخطّي.");

            return;
        }

        $hangsFromSource = DB::table('category_parent_child')
            ->where('parent_id', $from)->where('child_id', $childId)->exists();

        if (! $hangsFromSource) {
            $this->command?->line("  - «{$name}» ليس تحت «{$move['from_root_slug']}» — لا شيء ليُنقل.");

            return;
        }

        DB::table('category_parent_child')->updateOrInsert(
            ['parent_id' => $to, 'child_id' => $childId],
            ['updated_at' => now()]
        );

        DB::table('category_parent_child')
            ->where('parent_id', $from)->where('child_id', $childId)->delete();

        $moved = $this->moveWiring($childId, $from, $to);

        // Nobody may be left pointing at a root the child no longer hangs from.
        $accounts = DB::table('users')
            ->where('category_child_id', $childId)
            ->where('category_id', $from)
            ->update(['category_id' => $to]);

        $this->command?->line("  - «{$name}» #{$childId} : {$move['from_root_slug']} → {$move['to_root_slug']}");
        $this->command?->line("      صفوف ربط نُقلت : {$moved} · حسابات نُقلت : {$accounts}");
        $this->command?->line("      السبب : {$move['why']}");
    }

    private function moveWiring(int $childId, int $from, int $to): int
    {
        $moved = 0;

        foreach (self::WIRING as $table) {
            $rows = DB::table($table)
                ->where('category_id', $from)
                ->where('child_id', $childId)
                ->get(['id', 'platform_service_id']);

            foreach ($rows as $row) {
                // (root, child, service) is UNIQUE on all three tables. If the
                // target root already carries this service for this child, the
                // row standing there wins and the source row goes — moving it
                // would violate the key, and overwriting would discard whatever
                // the target had been configured with.
                $occupied = DB::table($table)
                    ->where('category_id', $to)
                    ->where('child_id', $childId)
                    ->where('platform_service_id', (int) $row->platform_service_id)
                    ->exists();

                if ($occupied) {
                    DB::table($table)->where('id', $row->id)->delete();

                    continue;
                }

                DB::table($table)->where('id', $row->id)->update([
                    'category_id' => $to,
                    'updated_at' => now(),
                ]);

                $moved++;
            }
        }

        return $moved;
    }
}
